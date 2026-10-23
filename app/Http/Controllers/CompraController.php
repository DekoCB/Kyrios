<?php
// app/Http/Controllers/CompraController.php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Proveedor;
use App\Models\Almacen;
use App\Models\Sucursal;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Categoria;
use App\Services\CompraService;
use App\Services\VarianteService;
use App\Models\Catalogo\Color;
use App\Models\Catalogo\Marca;
use App\Models\Catalogo\UnidadMedida;
use App\Services\CodigoBarrasService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    protected $compraService;
    protected $codigoBarrasService;

    public function __construct(CompraService $compraService, CodigoBarrasService $codigoBarrasService)
    {
        $this->compraService = $compraService;
        $this->codigoBarrasService = $codigoBarrasService;
    }

    public function index(Request $request)
    {
        $query = Compra::with(['proveedor', 'usuario', 'almacen', 'cuentaPorPagar'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($w) use ($q) {
                $w->where('numero_factura', 'like', "%{$q}%")
                  ->orWhere('codigo', 'like', "%{$q}%");
            });
        }

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('tipo_compra')) {
            $query->where('tipo_compra', $request->tipo_compra);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        $compras    = $query->paginate(15)->withQueryString();
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('razon_social')->get();

        return view('compras.index', compact('compras', 'proveedores'));
    }

    public function create()
    {
        $proveedores = Proveedor::where('estado', 'activo')
            ->orderBy('razon_social')
            ->get();
            
        $almacenesCentral = Almacen::where('estado', 'activo')
            ->whereIn('tipo', ['principal', 'deposito', 'temporal'])
            ->orderBy('nombre')
            ->get();

        $almacenesTienda = Almacen::where('estado', 'activo')
            ->where('tipo', 'tienda')
            ->orderBy('nombre')
            ->get();

        $marcas = Marca::where('estado', 'activo')->orderBy('nombre')->get();

        $productos = app(VarianteService::class)->getProductosParaSelectorCompra();

        $colores    = Color::where('estado', 'activo')->orderBy('nombre')->get();
        $categorias = Categoria::activas()->orderBy('nombre')->get();
        $unidades   = UnidadMedida::where('estado', 'activo')->orderBy('nombre')->get();

        // Si venimos de "Recibir mercadería" de un Pedido a Proveedor, precargar
        // proveedor/almacén/productos para no tener que volver a tipearlos.
        $pedidoOrigen = null;
        if (request()->filled('pedido_id')) {
            $pedido = \App\Models\Pedido::with('detalles.producto', 'detalles.variante')
                ->find(request()->integer('pedido_id'));

            if ($pedido && $pedido->estado === 'aprobado' && !$pedido->compra()->exists()) {
                $pedidoOrigen = [
                    'id'           => $pedido->id,
                    'codigo'       => $pedido->codigo,
                    'proveedor_id' => $pedido->proveedor_id,
                    'almacen_id'   => $pedido->almacen_id,
                    'detalles'     => $pedido->detalles->map(fn($d) => [
                        'producto_id'         => $d->producto_id,
                        'variante_id'         => $d->variante_id,
                        'cantidad'            => $d->cantidad,
                        'precio_referencial'  => $d->precio_referencial,
                    ]),
                ];
            }
        }

        return view('compras.create', compact('proveedores', 'almacenesCentral', 'almacenesTienda', 'productos', 'colores', 'marcas', 'categorias', 'unidades', 'pedidoOrigen'));
    }

    public function store(Request $request)
    {
        // Validación extendida y completa (PRIMERO)
        $validated = $request->validate([
            // Datos principales
            'proveedor_id' => 'required|exists:proveedores,id',
            'pedido_id' => 'nullable|exists:pedidos,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'numero_factura' => 'required|string|max:50|unique:compras,numero_factura,NULL,id,proveedor_id,' . $request->proveedor_id,
            'fecha' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha',
            'observaciones' => 'nullable|string',
            
            // Datos financieros
            'forma_pago' => 'required|in:contado,credito',
            'condicion_pago' => 'required_if:forma_pago,credito|nullable|integer|min:1|max:90',
            'tipo_moneda' => 'required|in:PEN,USD',
            'tipo_cambio' => 'required_if:tipo_moneda,USD|nullable|numeric|min:0.001',
            'incluye_igv' => 'boolean',
            'tipo_operacion' => 'required|in:01,02,03,04',
            'descuento_global' => 'nullable|numeric|min:0|max:100',
            'monto_adicional' => 'nullable|numeric|min:0',
            'concepto_adicional' => 'nullable|string|max:255',
            
            // Datos de envío
            'guia_remision' => 'nullable|string|max:50',
            'transportista' => 'nullable|string|max:255',
            'placa_vehiculo' => 'nullable|string|max:10',

            // Tipo de compra e importación
            'tipo_compra' => 'required|in:local,importacion',
            'numero_dua' => 'nullable|string|max:50',
            'numero_manifiesto' => 'nullable|string|max:50',
            'agente_aduanas' => 'nullable|string|max:255',
            'flete_usd' => 'nullable|numeric|min:0',
            'seguro_usd' => 'nullable|numeric|min:0',
            'otros_usd' => 'nullable|numeric|min:0',
            'transporte_local_pen' => 'nullable|numeric|min:0',
            'impuestos_usd' => 'nullable|numeric|min:0',
            'impuestos_pen' => 'nullable|numeric|min:0',
            'percepcion_pen' => 'nullable|numeric|min:0',
            
            // Detalles de productos
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|exists:productos,id',
            'detalles.*.variante_id' => 'nullable|exists:producto_variantes,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'required|numeric|min:0.01',
            'detalles.*.descuento' => 'nullable|numeric|min:0|max:100',
            'detalles.*.codigo_barras' => 'nullable|string|max:50',
            'detalles.*.modelo_id' => 'nullable|exists:modelos,id',
            'detalles.*.color_id'  => 'nullable|exists:colores,id',
            'detalles.*.capacidad' => 'nullable|string|max:50',
        ], [
            'numero_factura.unique' => 'Ya existe una compra con este número de factura para el proveedor seleccionado',
            'detalles.required' => 'Debe agregar al menos un producto',
            'detalles.*.precio_unitario.min' => 'El precio debe ser mayor a 0',
        ]);

        try {
            DB::beginTransaction();

            // Calcular montos
            $subtotal = 0;
            foreach ($validated['detalles'] as $detalle) {
                $precioConDescuento = $detalle['precio_unitario'];
                if (!empty($detalle['descuento'])) {
                    $precioConDescuento = $detalle['precio_unitario'] * (1 - $detalle['descuento'] / 100);
                }
                $subtotal += $detalle['cantidad'] * $precioConDescuento;
            }

            // Aplicar descuento global si existe
            if (!empty($validated['descuento_global'])) {
                $subtotal = $subtotal * (1 - $validated['descuento_global'] / 100);
            }

            // Agregar monto adicional si existe
            if (!empty($validated['monto_adicional'])) {
                $subtotal += $validated['monto_adicional'];
            }

            // Agregar costos de importación si aplica (solo en tipo importacion)
            $esImportacion = ($validated['tipo_compra'] ?? 'local') === 'importacion';
            $fleteUsd          = $esImportacion ? (float)($validated['flete_usd'] ?? 0) : 0;
            $seguroUsd         = $esImportacion ? (float)($validated['seguro_usd'] ?? 0) : 0;
            $otrosUsd          = $esImportacion ? (float)($validated['otros_usd'] ?? 0) : 0;
            $transporteLocalPen = $esImportacion ? (float)($validated['transporte_local_pen'] ?? 0) : 0;
            $impuestosUsd      = $esImportacion ? (float)($validated['impuestos_usd'] ?? 0) : 0;
            $impuestosPen      = $esImportacion ? (float)($validated['impuestos_pen'] ?? 0) : 0;
            $percepcionPen     = $esImportacion ? (float)($validated['percepcion_pen'] ?? 0) : 0;

            // Calcular IGV SOLO sobre los productos (sin incluir CIF)
            $tipoCambio    = (float)($validated['tipo_cambio'] ?? 1);
            $incluyeIgv    = filter_var($validated['incluye_igv'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $precioConIgv  = filter_var($request->input('precio_incluye_igv', false), FILTER_VALIDATE_BOOLEAN);
            $tipoOperacion = $validated['tipo_operacion'] ?? '01';

            $igv   = 0;
            $total = $subtotal;
            if ($tipoOperacion === '01' && $incluyeIgv) {
                if ($precioConIgv) {
                    // Los precios ingresados YA incluyen IGV: extraer la base
                    $subtotalBase = round($subtotal / 1.18, 2);
                    $igv          = round($subtotal - $subtotalBase, 2);
                    $subtotal     = $subtotalBase;
                    $total        = $subtotalBase + $igv;
                } else {
                    $igv   = round($subtotal * 0.18, 2);
                    $total = $subtotal + $igv;
                }
            }

            // Convertir costos CIF a la moneda de la compra y sumar al total
            // (IGV ya fue calculado sobre productos; CIF se agrega por separado)
            $cifUsdTotal = $fleteUsd + $seguroUsd + $otrosUsd + $impuestosUsd;
            $cifPenTotal = $transporteLocalPen + $impuestosPen + $percepcionPen;

            if ($validated['tipo_moneda'] === 'USD' && $tipoCambio > 0) {
                // Compra en USD: CIF_USD va directo, CIF_PEN se divide por TC
                $cifEnMonedaCompra = $cifUsdTotal + $cifPenTotal / $tipoCambio;
            } else {
                // Compra en PEN: CIF_USD se multiplica por TC, CIF_PEN va directo
                $cifEnMonedaCompra = $cifUsdTotal * $tipoCambio + $cifPenTotal;
            }

            $total = round($total + $cifEnMonedaCompra, 2);

            // Total en PEN para referencia (si la compra es en USD)
            if ($validated['tipo_moneda'] === 'USD' && $tipoCambio > 0) {
                $totalPEN = $total * $tipoCambio;
            } else {
                $totalPEN = $total;
            }

            // Preparar datos para el servicio
            $datosCompra = [
                'proveedor_id' => $validated['proveedor_id'],
                'pedido_id' => $validated['pedido_id'] ?? null,
                'user_id' => auth()->id(),
                'almacen_id' => $validated['almacen_id'],
                'numero_factura' => $validated['numero_factura'],
                'fecha' => $validated['fecha'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'forma_pago' => $validated['forma_pago'],
                'condicion_pago' => $validated['forma_pago'] === 'credito' 
                    ? (int)($validated['condicion_pago'] ?? 0) 
                    : null,
                'tipo_moneda' => $validated['tipo_moneda'],
                'tipo_cambio' => $validated['tipo_cambio'] ?? 1,
                'incluye_igv' => $incluyeIgv,
                'subtotal' => $subtotal,
                'igv' => $igv,
                'tipo_operacion' => $validated['tipo_operacion'],
                'total' => $total,
                'total_pen' => $totalPEN,
                'descuento_global' => $validated['descuento_global'] ?? 0,
                'monto_adicional' => $validated['monto_adicional'] ?? 0,
                'concepto_adicional' => $validated['concepto_adicional'] ?? null,
                'guia_remision' => $validated['guia_remision'] ?? null,
                'transportista' => $validated['transportista'] ?? null,
                'placa_vehiculo' => $validated['placa_vehiculo'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'tipo_compra' => $validated['tipo_compra'],
                'numero_dua' => $validated['numero_dua'] ?? null,
                'numero_manifiesto' => $validated['numero_manifiesto'] ?? null,
                'agente_aduanas' => $validated['agente_aduanas'] ?? null,
                'flete_usd' => $fleteUsd,
                'seguro_usd' => $seguroUsd,
                'otros_usd' => $otrosUsd,
                'transporte_local_pen' => $transporteLocalPen,
                'impuestos_usd' => $impuestosUsd,
                'impuestos_pen' => $impuestosPen,
                'percepcion_pen' => $percepcionPen,
            ];

            // Registrar la compra usando el servicio
            $compra = $this->compraService->registrarCompra($datosCompra, $validated['detalles']);

            DB::commit();

            return redirect()
                ->route('compras.show', $compra)
                ->with('success', 'Compra registrada exitosamente. N° Factura: ' . $compra->numero_factura);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Error al registrar la compra: ' . $e->getMessage());
        }
    }

    /**
     * Obtener tipo de cambio actual desde SUNAT (vía api.apis.net.pe)
     */
    public function tipoCambio()
    {
        try {
            $fecha = now()->format('Y-m-d');
            $response = \Illuminate\Support\Facades\Http::timeout(6)
                ->withHeaders(['Accept' => 'application/json'])
                ->get('https://api.apis.net.pe/v1/tipo-cambio-sunat', ['fecha' => $fecha]);

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'compra'  => $data['compra']  ?? null,
                    'venta'   => $data['venta']   ?? null,
                    'fecha'   => $data['fecha']   ?? $fecha,
                ]);
            }
        } catch (\Exception $e) {
            // silenciar y devolver error amigable
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo conectar al servicio de tipo de cambio. Ingresa el valor manualmente.',
        ]);
    }

    public function show(Compra $compra)
    {
        $compra->load(['proveedor', 'usuario', 'almacen', 'detalles.producto', 'detalles.variante.color', 'detalles.color']);

        return view('compras.show', compact('compra'));
    }

    public function edit(Compra $compra)
    {
        // Solo permitir editar si no está anulada
        if ($compra->estado === 'anulado') {
            return redirect()
                ->route('compras.show', $compra)
                ->with('error', 'No se puede editar una compra anulada');
        }

        // Cargar relaciones necesarias
        $compra->load(['detalles.producto', 'almacen.sucursal']);

        $proveedores = Proveedor::where('estado', 'activo')
            ->orderBy('razon_social')
            ->get();

        $sucursales = Sucursal::where('estado', 'activo')
            ->with(['almacenes' => fn($q) => $q->where('estado', 'activo')->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return view('compras.edit', compact('compra', 'proveedores', 'sucursales'));
    }

    public function update(Request $request, Compra $compra)
    {
        // No se puede editar una compra anulada
        if ($compra->estado === 'anulado') {
            return back()->with('error', 'No se puede editar una compra anulada');
        }

        $validated = $request->validate([
            'proveedor_id'              => 'required|exists:proveedores,id',
            'numero_factura'            => 'required|string|max:50',
            'almacen_id'                => 'required|exists:almacenes,id',
            'fecha'                     => 'required|date',
            'forma_pago'                => 'required|in:contado,credito',
            'condicion_pago'            => 'nullable|integer|min:1|max:365',
            'observaciones'             => 'nullable|string',
            'incluye_igv'               => 'nullable|boolean',
            'detalles'                  => 'nullable|array',
            'detalles.*.id'             => 'required_with:detalles|integer|exists:detalle_compras,id',
            'detalles.*.cantidad'       => 'required_with:detalles|integer|min:1',
            'detalles.*.precio_unitario'=> 'required_with:detalles|numeric|min:0.01',
        ], [
            'detalles.*.cantidad.min'        => 'La cantidad debe ser al menos 1.',
            'detalles.*.precio_unitario.min' => 'El precio unitario debe ser mayor a 0.',
        ]);

        // condicion_pago solo aplica a crédito
        if ($validated['forma_pago'] !== 'credito') {
            $validated['condicion_pago'] = null;
        }

        $detalles = $validated['detalles'] ?? [];
        unset($validated['detalles']);

        try {
            $this->compraService->actualizarCabecera($compra, $validated, $detalles);

            return redirect()->route('compras.show', $compra)
                ->with('success', 'Compra actualizada correctamente');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    public function destroyDetalle(Compra $compra, DetalleCompra $detalle)
    {
        if ($compra->estado === 'anulado') {
            return response()->json(['success' => false, 'message' => 'La compra está anulada'], 422);
        }
        if ($detalle->compra_id !== $compra->id) {
            return response()->json(['success' => false, 'message' => 'El producto no pertenece a esta compra'], 422);
        }
        if ($compra->detalles()->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'No puede eliminar el único producto de la compra. Anule la compra completa si es necesario.'], 422);
        }
        try {
            $this->compraService->eliminarDetalle($compra, $detalle);
            return response()->json(['success' => true, 'message' => 'Producto eliminado y stock revertido correctamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Compra $compra)
    {
        try {
            // No se puede eliminar una compra ya anulada
            if ($compra->estado === 'anulado') {
                return back()->with('error', 'No se puede eliminar una compra anulada');
            }

            $this->compraService->eliminarCompra($compra);

            return redirect()
                ->route('compras.index')
                ->with('success', 'Compra eliminada exitosamente');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }


    public function imprimir(Compra $compra)
    {
        // Generar PDF de la compra
        $pdf = \PDF::loadView('compras.pdf', compact('compra'));
        return $pdf->download('compra-' . $compra->numero_factura . '.pdf');
    }

    public function anular(Request $request, Compra $compra)
    {
        try {
            DB::beginTransaction();

            if ($compra->estado === 'anulado') {
                throw new \Exception('La compra ya está anulada');
            }

            $motivo = $request->input('motivo');
            $this->compraService->anularCompra($compra, $motivo);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Compra anulada exitosamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function getProductosPorProveedor($proveedorId)
    {
        // Obtener productos que suele vender este proveedor
        $productos = Producto::whereHas('compras', function($query) use ($proveedorId) {
                $query->where('proveedor_id', $proveedorId);
            })
            ->orWhere('estado', 'activo')
            ->orderBy('nombre')
            ->get();
            
        return response()->json($productos);
    }

    public function verificarFactura(Request $request)
    {
        // Verificar si el número de factura ya existe para el proveedor
        $existe = Compra::where('proveedor_id', $request->proveedor_id)
            ->where('numero_factura', $request->numero_factura)
            ->exists();
            
        return response()->json(['existe' => $existe]);
    }
    // Agrega estos métodos al final de tu CompraController.php

    /**
     * Buscar productos para el modal de selección (AJAX)
     * Incluye variantes agrupadas por producto base.
     */
    public function buscarProductos(Request $request)
    {
        $termino = $request->get('q', '');

        $productos = Producto::with(['marca', 'modelo', 'categoria', 'variantesActivas.color'])
            ->where('estado', 'activo')
            ->where(function($q) use ($termino) {
                $q->where('nombre', 'like', "%{$termino}%")
                  ->orWhere('codigo', 'like', "%{$termino}%")
                  ->orWhereHas('marca',  fn($m) => $m->where('nombre', 'like', "%{$termino}%"))
                  ->orWhereHas('modelo', fn($m) => $m->where('nombre', 'like', "%{$termino}%"))
                  ->orWhereHas('variantesActivas', fn($v) => $v->where('sku', 'like', "%{$termino}%"));
            })
            ->limit(20)
            ->get();

        $result = $productos->map(function($producto) {
            $variantes = $producto->variantesActivas->map(function($v) {
                $stock = (int)$v->stock_actual;

                return [
                    'id'              => $v->id,
                    'sku'             => $v->sku,
                    'color_id'        => $v->color_id,
                    'color_nombre'    => $v->color?->nombre,
                    'color_hex'       => $v->color?->codigo_hex,
                    'capacidad'       => $v->capacidad,
                    'sobreprecio'     => (float)$v->sobreprecio,
                    'stock_actual'    => $stock,
                    'nombre_completo' => $v->nombre_completo,
                    'etiqueta'        => $v->codigo_kyrios ? ($v->codigo_kyrios . ' · ' . $v->nombre_completo) : null,
                ];
            });

            return [
                'id'              => $producto->id,
                'nombre'          => $producto->nombre,
                'codigo'          => $producto->codigo,
                'marca'           => $producto->marca?->nombre,
                'modelo'          => $producto->modelo?->nombre,
                'categoria'       => $producto->categoria?->nombre,
                'categoria_id'    => $producto->categoria_id,
                'tipo_inventario' => $producto->tipo_inventario,
                'marca_id'        => $producto->marca_id,
                'modelo_id'       => $producto->modelo_id,
                'imagen'          => $producto->imagen_url ?? null,
                'tiene_variantes' => $variantes->isNotEmpty(),
                'variantes'       => $variantes,
            ];
        });

        return response()->json($result);
    }

    /**
     * Crear un producto rápido desde el formulario de compra (AJAX)
     */
    public function crearProductoRapido(Request $request)
    {
        $validated = $request->validate([
            'nombre'            => 'required|string|max:255',
            'categoria_id'      => 'required|exists:categorias,id',
            'marca_id'          => 'required|exists:marcas,id',
            'modelo_id'         => 'nullable|exists:modelos,id',
            'color_id'          => 'nullable|exists:colores,id',
            'unidad_medida_id'  => 'required|exists:unidades_medida,id',
            'tiene_variantes'         => 'boolean',
            'variantes'               => 'nullable|array',
            'variantes.*.color_id'    => 'nullable|exists:colores,id',
            'variantes.*.capacidad'   => 'nullable|string|max:100',
            'codigo_barras'           => 'nullable|string|max:100',
        ], [
            'nombre.required'          => 'El nombre del producto es obligatorio.',
            'categoria_id.required'    => 'Selecciona una categoría.',
            'marca_id.required'        => 'Selecciona una marca.',
            'unidad_medida_id.required'=> 'Selecciona una unidad de medida.',
        ]);

        try {
            $producto = Producto::create([
                'codigo'           => Producto::generarCodigo(),
                'nombre'           => $validated['nombre'],
                'categoria_id'     => $validated['categoria_id'],
                'marca_id'         => $validated['marca_id'],
                'modelo_id'        => $validated['modelo_id'] ?? null,
                'color_id'         => $validated['color_id'] ?? null,
                'unidad_medida_id' => $validated['unidad_medida_id'],
                'tipo_inventario'  => 'cantidad',
                'codigo_barras'    => $validated['codigo_barras'] ?? null,
                'estado'           => 'activo',
                'stock_actual'     => 0,
                'stock_minimo'     => 0,
                'stock_maximo'     => 0,
                'creado_por'       => auth()->id(),
            ]);

            // Crear variantes si se enviaron
            $variantesCreadas = [];
            if (!empty($validated['variantes'])) {
                $varianteService = app(\App\Services\VarianteService::class);
                foreach ($validated['variantes'] as $vData) {
                    $variante = $varianteService->obtenerOCrearVariante(
                        $producto,
                        !empty($vData['color_id']) ? (int)$vData['color_id'] : null,
                        !empty($vData['capacidad']) ? $vData['capacidad'] : null,
                        0
                    );
                    $variante->load('color');
                    $variantesCreadas[] = [
                        'id'              => $variante->id,
                        'sku'             => $variante->sku,
                        'color_id'        => $variante->color_id,
                        'color_nombre'    => $variante->color?->nombre,
                        'color_hex'       => $variante->color?->codigo_hex,
                        'capacidad'       => $variante->capacidad,
                        'sobreprecio'     => (float)$variante->sobreprecio,
                        'stock_actual'    => 0,
                        'nombre_completo' => $variante->nombre_completo,
                    ];
                }
            }

            $producto->load('categoria', 'marca', 'modelo');

            return response()->json([
                'success'         => true,
                'id'              => $producto->id,
                'nombre'          => $producto->nombre,
                'tipo_inventario' => $producto->tipo_inventario,
                'categoria'       => $producto->categoria?->nombre,
                'marca'           => $producto->marca?->nombre,
                'marca_id'        => $producto->marca_id,
                'modelo'          => $producto->modelo?->nombre,
                'modelo_id'       => $producto->modelo_id,
                'color_id'        => $producto->color_id,
                'codigo_barras'   => $producto->codigo_barras,
                'tiene_variantes' => count($variantesCreadas) > 0,
                'variantes'       => $variantesCreadas,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
 * Obtener detalles completos de un producto (para selección rápida)
 */
public function getProductoDetalle($id)
{
    try {
        $producto = Producto::with(['marca', 'modelo', 'categoria', 'variantesActivas.color'])
            ->findOrFail($id);

        $variantes = $producto->variantesActivas->map(function($v) {
            $stock = (int)$v->stock_actual;

            return [
                'id'              => $v->id,
                'sku'             => $v->sku,
                'color_id'        => $v->color_id,
                'color_nombre'    => $v->color?->nombre,
                'color_hex'       => $v->color?->codigo_hex,
                'capacidad'       => $v->capacidad,
                'sobreprecio'     => (float)$v->sobreprecio,
                'stock_actual'    => $stock,
                'nombre_completo' => $v->nombre_completo,
                'etiqueta'        => $v->codigo_kyrios ? ($v->codigo_kyrios . ' · ' . $v->nombre_completo) : null,
                'tiene_stock'     => $stock > 0,
            ];
        });

        return response()->json([
            'success'         => true,
            'id'              => $producto->id,
            'nombre'          => $producto->nombre,
            'codigo'          => $producto->codigo,
            'tipo_inventario' => $producto->tipo_inventario,
            'marca_id'        => $producto->marca_id,
            'marca_nombre'    => $producto->marca?->nombre,
            'modelo_id'       => $producto->modelo_id,
            'modelo_nombre'   => $producto->modelo?->nombre,
            'categoria_id'    => $producto->categoria_id,
            'categoria_nombre'=> $producto->categoria?->nombre,
            'tiene_variantes' => $variantes->isNotEmpty(),
            'variantes'       => $variantes,
            'precio_compra'   => (float)($producto->ultimo_costo_compra ?? 0),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error'   => true,
            'message' => 'Error al cargar el producto: ' . $e->getMessage(),
        ], 500);
    }
}

    /**
     * Recalcular prorrateo de una compra existente (útil si se editaron gastos)
     */
    public function recalcularProrrateo(Compra $compra)
    {
        try {
            $compra->load(['detalles.producto', 'detalles.variante']);
            $this->compraService->aplicarProrrateo($compra);

            return redirect()
                ->route('compras.show', $compra)
                ->with('success', 'Prorrateo recalculado correctamente.');
        } catch (\Exception $e) {
            return redirect()
                ->route('compras.show', $compra)
                ->with('error', 'Error al recalcular prorrateo: ' . $e->getMessage());
        }
    }
}