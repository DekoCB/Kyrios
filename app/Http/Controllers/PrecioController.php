<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Models\Almacen;
use App\Models\ProductoPrecio;
use App\Models\ProductoPrecioHistorial;
use App\Models\DetalleCompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrecioController extends Controller
{
    /**
     * Constructor - Solo Admin y Almacenero pueden gestionar precios
     */
    public function __construct()
    {
        $this->middleware('role:Administrador,Almacenero');
    }

    /**
     * Listado de productos con sus precios actuales
     */
    public function index(Request $request)
    {
        // Stats globales (independientes de filtros)
        // Precio 0 se considera "sin precio"
        $totalProductos = Producto::where('estado', 'activo')->count();
        $conPrecio      = Producto::where('estado', 'activo')
                            ->whereHas('precios', fn($q) => $q->where('activo', true)->where('precio', '>', 0))
                            ->count();
        $sinPrecio      = $totalProductos - $conPrecio;
        $margenPromedio = ProductoPrecio::where('activo', true)->where('precio', '>', 0)->whereNotNull('margen')->avg('margen');

        $query = Producto::where('estado', 'activo')
            ->with([
                'categoria',
                'variantes' => function($q) {
                    $q->where('estado', 'activo');
                },
                'precios' => function($q) {
                    $q->with('variante')
                      ->where('activo', true)
                      ->whereNull('almacen_id')
                      ->where('tipo_precio', 'venta_regular')
                      ->latest();
                },
            ]);

        // Tab: sin precio (incluye productos sin registro de precio o con precio = 0)
        if ($request->get('tab') === 'sin_precio') {
            $query->whereDoesntHave('precios', fn($q) => $q->where('activo', true)->where('precio', '>', 0));
        }

        // Filtro por categoría
        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        // Búsqueda
        if ($request->filled('buscar')) {
            $query->where(function($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->buscar . '%')
                  ->orWhere('codigo', 'like', '%' . $request->buscar . '%');
            });
        }

        $productos  = $query->orderBy('nombre')->paginate(25)->withQueryString();
        $categorias = Categoria::where('estado', 'activo')->orderBy('nombre')->get();

        return view('precios.index', compact(
            'productos', 'categorias',
            'totalProductos', 'conPrecio', 'sinPrecio', 'margenPromedio'
        ));
    }
    /**
    * Mostrar formulario para editar un precio específico
    */
    public function edit(Producto $producto, $precioId)
    {
        $precio = ProductoPrecio::with(['variante.color', 'proveedor', 'almacen'])->findOrFail($precioId);

        $producto->load([
            'categoria', 'marca', 'modelo',
            'variantes' => function($q) {
                $q->where('estado', 'activo');
            }
        ]);
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('razon_social')->get();

        // El precio mayorista real vive en una fila hermana (tipo_precio = 'venta_mayorista'),
        // no en la columna precio_mayorista de esta fila (que no la lee nadie más).
        // Es siempre global (almacen_id null) — igual que store()/show()/POS; no existe mayorista por tienda.
        $precioMayoristaActual = ProductoPrecio::where('producto_id', $producto->id)
            ->where('variante_id', $precio->variante_id)
            ->whereNull('almacen_id')
            ->where('tipo_precio', 'venta_mayorista')
            ->where('activo', true)
            ->value('precio');

        // Fila de precio global (venta_regular) de esta variante, para enlazar desde una edición
        // de precio de tienda al lugar donde realmente se edita el mayorista.
        $precioGlobalId = ProductoPrecio::where('producto_id', $producto->id)
            ->where('variante_id', $precio->variante_id)
            ->whereNull('almacen_id')
            ->where('tipo_precio', 'venta_regular')
            ->where('activo', true)
            ->orderByDesc('id')
            ->value('id');

        return view('precios.edit', compact('producto', 'precio', 'proveedores', 'precioMayoristaActual', 'precioGlobalId'));
    }
    /**
     * Mostrar detalle de precios de un producto
     */
    public function show(Producto $producto)
    {
        $producto->load([
            'categoria', 'marca', 'modelo', 
            'variantes' => function($q) {
                $q->where('estado', 'activo');
            },
            'precios' => function($q) {
                $q->with('proveedor', 'almacen', 'variante.color')->orderByRaw('almacen_id IS NULL DESC')->latest();
            }
        ]);

        $proveedores = Proveedor::where('estado', 'activo')->orderBy('razon_social')->get();
        $almacenes   = Almacen::where('estado', 'activo')->orderBy('nombre')->get();

        $preciosGlobalesActivos = $producto->precios
            ->whereNull('almacen_id')
            ->where('tipo_precio', 'venta_regular')
            ->where('activo', true)
            ->sortByDesc('id')
            ->unique('variante_id')
            ->keyBy('variante_id');

        // Precios mayoristas globales activos
        $preciosMayoristasActivos = $producto->precios
            ->whereNull('almacen_id')
            ->where('tipo_precio', 'venta_mayorista')
            ->where('activo', true)
            ->sortByDesc('id')
            ->unique('variante_id')
            ->keyBy('variante_id');

        // Para el historial/tabla completa (incluye inactivos)
        $preciosGlobales  = $producto->precios->whereNull('almacen_id')->where('tipo_precio', 'venta_regular');
        $preciosPorTienda = $producto->precios->whereNotNull('almacen_id')->where('tipo_precio', 'venta_regular');

        // Rango real de precio de venta por capacidad/variante (para no mostrar el campo
        // productos.precio_venta cuando quedó desactualizado frente a los precios por capacidad).
        $preciosVentaActivos = $preciosGlobalesActivos->pluck('precio')->map(fn($p) => (float) $p);
        $precioVentaMin = $preciosVentaActivos->min();
        $precioVentaMax = $preciosVentaActivos->max();

        return view('precios.show', compact(
            'producto', 'proveedores', 'almacenes',
            'preciosGlobales', 'preciosGlobalesActivos', 'preciosPorTienda',
            'preciosMayoristasActivos', 'precioVentaMin', 'precioVentaMax'
        ));
    }

    /**
     * Calcular precio sugerido basado en proveedor y márgenes
     */
    public function calcular(Request $request, Producto $producto)
    {
        $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'precio_compra' => 'required|numeric|min:0.01',
            'margen' => 'required|numeric|min:0|max:100',
            'impuestos' => 'nullable|numeric|min:0|max:100',
        ]);

        // Calcular precios
        $precioBase = $request->precio_compra * (1 + $request->margen / 100);
        $precioFinal = $precioBase * (1 + ($request->impuestos ?? 0) / 100);

        // Obtener información del proveedor
        $proveedor = Proveedor::find($request->proveedor_id);

        return response()->json([
            'success' => true,
            'precio_base' => round($precioBase, 2),
            'precio_final' => round($precioFinal, 2),
            'margen_aplicado' => $request->margen,
            'impuestos' => $request->impuestos ?? 0,
            'proveedor' => [
                'id' => $proveedor->id,
                'nombre' => $proveedor->razon_social,
            ]
        ]);
    }

    /**
     * Registrar nuevo precio para un producto
     */
    public function store(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'proveedor_id'    => 'nullable|exists:proveedores,id',
            'variante_id'     => 'nullable|exists:producto_variantes,id',
            'precio_compra'   => 'required|numeric|min:0.01',
            'precio_venta'    => 'required|numeric|min:0.01',
            'precio_mayorista'=> 'nullable|numeric|min:0.01',
            'margen'          => 'required|numeric|min:0|max:1000',
            'margen_mayorista'=> 'nullable|numeric|min:0|max:1000',
            'observaciones'   => 'nullable|string|max:500',
            'incluye_igv'     => 'nullable|boolean',
            'replicar_tiendas'=> 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $precioAnterior = $producto->precio_venta;

            // Determinar todas las variantes que deben recibir este precio.
            // Si se seleccionó una capacidad, el precio aplica a TODAS las variantes
            // con esa misma capacidad (independiente del color).
            $variantesIds = [];
            if (!empty($validated['variante_id'])) {
                $variantePrincipal = \App\Models\ProductoVariante::find($validated['variante_id']);
                $variantesIds = $producto->variantesActivas()
                    ->where('capacidad', $variantePrincipal?->capacidad)
                    ->pluck('id')
                    ->toArray();
            }

            // Helper para crear un registro de precio
            $crearPrecio = function (int|null $varianteId, int|null $almacenId) use ($validated, $producto) {
                ProductoPrecio::where('producto_id', $producto->id)
                    ->where('variante_id', $varianteId)
                    ->where('almacen_id', $almacenId)
                    ->where('tipo_precio', 'venta_regular')
                    ->where('activo', true)
                    ->update(['activo' => false]);

                return ProductoPrecio::create([
                    'producto_id'      => $producto->id,
                    'variante_id'      => $varianteId,
                    'almacen_id'       => $almacenId,
                    'tipo_precio'      => 'venta_regular',
                    'precio'           => $validated['precio_venta'],
                    'precio_compra'    => $validated['precio_compra'],
                    'precio_mayorista' => $validated['precio_mayorista'] ?? null,
                    'margen'           => $validated['margen'],
                    'incluye_igv'      => false,
                    'observaciones'    => $validated['observaciones'] ?? null,
                    'proveedor_id'     => $validated['proveedor_id'] ?? null,
                    'activo'           => true,
                    'creado_por'       => auth()->id(),
                ]);
            };

            if (empty($variantesIds)) {
                // Precio base del producto (sin variante)
                $crearPrecio(null, null);
                $producto->update(['precio_venta' => $validated['precio_venta']]);
            } else {
                // Crear precio global para cada variante de la misma capacidad
                foreach ($variantesIds as $vid) {
                    $crearPrecio($vid, null);
                }
            }

            // Precio mayorista global
            if (!empty($validated['precio_mayorista'])) {
                $mayoristasIds = empty($variantesIds) ? [null] : $variantesIds;
                foreach ($mayoristasIds as $vid) {
                    ProductoPrecio::where('producto_id', $producto->id)
                        ->where('variante_id', $vid)
                        ->whereNull('almacen_id')
                        ->where('tipo_precio', 'venta_mayorista')
                        ->where('activo', true)
                        ->update(['activo' => false]);

                    $margenMay = isset($validated['margen_mayorista']) && $validated['margen_mayorista'] !== ''
                        ? $validated['margen_mayorista']
                        : round((($validated['precio_mayorista'] - $validated['precio_compra']) / $validated['precio_compra']) * 100, 2);

                    ProductoPrecio::create([
                        'producto_id'   => $producto->id,
                        'variante_id'   => $vid,
                        'almacen_id'    => null,
                        'tipo_precio'   => 'venta_mayorista',
                        'precio'        => $validated['precio_mayorista'],
                        'precio_compra' => $validated['precio_compra'],
                        'margen'        => $margenMay,
                        'proveedor_id'  => $validated['proveedor_id'] ?? null,
                        'activo'        => true,
                        'creado_por'    => auth()->id(),
                    ]);
                }
            }

            // Replicar a todas las tiendas activas (para cada variante de la capacidad)
            if (!empty($validated['replicar_tiendas'])) {
                $almacenes   = Almacen::where('estado', 'activo')->get();
                $replicarIds = empty($variantesIds) ? [null] : $variantesIds;

                foreach ($almacenes as $almacen) {
                    foreach ($replicarIds as $vid) {
                        $crearPrecio($vid, $almacen->id);
                    }
                }
            }

            ProductoPrecioHistorial::create([
                'producto_id'    => $producto->id,
                'variante_id'    => $validated['variante_id'] ?: null,
                'tipo_cambio'    => 'venta_regular',
                'precio_anterior'=> $precioAnterior ?: null,
                'precio_nuevo'   => $validated['precio_venta'],
                'motivo'         => $validated['observaciones'] ?? 'Registro de precio',
                'usuario_id'     => auth()->id(),
            ]);

            DB::commit();

            return redirect()
                ->route('precios.show', $producto)
                ->with('success', 'Precio registrado correctamente' .
                    (!empty($validated['replicar_tiendas']) ? ' y replicado a todas las tiendas.' : '.'));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error al registrar precio: ' . $e->getMessage());
        }
    }
    public function bulkAction(Request $request, Producto $producto)
{
    $request->validate([
        'action'      => 'required|in:update_price,activate,deactivate,restore_global',
        'price_ids'   => 'required|array',
        'price_ids.*' => 'exists:producto_precios,id',
        'value'       => 'nullable|numeric',
    ]);

    $precios = ProductoPrecio::whereIn('id', $request->price_ids)->get();

    foreach ($precios as $precio) {
        switch ($request->action) {
            case 'update_price':
                $precio->update([
                    'precio' => $request->value,
                    'margen' => $precio->precio_compra > 0
                        ? round((($request->value - $precio->precio_compra) / $precio->precio_compra) * 100, 2)
                        : 0,
                ]);
                break;
            case 'activate':
                $precio->update(['activo' => true]);
                break;
            case 'deactivate':
                $precio->update(['activo' => false]);
                break;
            case 'restore_global':
                $global = ProductoPrecio::where('producto_id', $producto->id)
                    ->whereNull('almacen_id')
                    ->where('variante_id', $precio->variante_id)
                    ->where('activo', true)
                    ->first();
                if ($global) {
                    $precio->update([
                        'precio' => $global->precio,
                        'margen' => $global->margen,
                    ]);
                }
                break;
        }
    }

    return response()->json(['success' => true, 'message' => 'Acción aplicada correctamente']);
}

public function restoreSingle(Producto $producto, ProductoPrecio $precio)
{
    $global = ProductoPrecio::where('producto_id', $producto->id)
        ->whereNull('almacen_id')
        ->where('variante_id', $precio->variante_id)
        ->where('activo', true)
        ->first();

    if ($global) {
        $precio->update([
            'precio' => $global->precio,
            'margen' => $global->margen,
        ]);
    }

    return redirect()->back()->with('success', 'Precio restaurado a valor global');
}
    /**
     * Actualizar precio existente
     */
    public function update(Request $request, Producto $producto, $precioId)
    {
        $precio = ProductoPrecio::findOrFail($precioId);

        $validated = $request->validate([
            'precio_compra'    => 'required|numeric|min:0.01',
            'precio_venta'     => 'required|numeric|min:0.01',
            'precio_mayorista' => 'nullable|numeric|min:0.01',
            'margen_mayorista' => 'nullable|numeric',
            'margen'           => 'required|numeric|min:0|max:1000',
            'observaciones'    => 'nullable|string|max:500',
            'fecha_inicio'     => 'nullable|date',
            'fecha_fin'        => 'nullable|date|after_or_equal:fecha_inicio',
            'incluye_igv'      => 'nullable|boolean',
            'activo'           => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // Registrar en historial si el precio de venta cambió
            if ((float)$precio->precio !== (float)$validated['precio_venta']) {
                ProductoPrecioHistorial::create([
                    'producto_id'     => $producto->id,
                    'variante_id'     => $precio->variante_id,
                    'tipo_cambio'     => 'venta_regular',
                    'precio_anterior' => $precio->precio,
                    'precio_nuevo'    => $validated['precio_venta'],
                    'motivo'          => $validated['observaciones'] ?? 'Edición manual',
                    'usuario_id'      => auth()->id(),
                ]);
            }

            $precio->update([
                'precio'           => $validated['precio_venta'],
                'precio_compra'    => $validated['precio_compra'],
                'precio_mayorista' => $validated['precio_mayorista'] ?? null,
                'margen'           => $validated['margen'],
                'incluye_igv'      => false,
                'observaciones'    => $validated['observaciones'] ?? null,
                'fecha_inicio'     => $validated['fecha_inicio'] ?? null,
                'fecha_fin'        => $validated['fecha_fin'] ?? null,
                'activo'           => $validated['activo'] ?? true,
            ]);

            // Si es precio global activo y sin variante, actualizar productos.precio_venta
            if (is_null($precio->almacen_id) && is_null($precio->variante_id) && $precio->activo) {
                $producto->update(['precio_venta' => $validated['precio_venta']]);
            }

            // El precio mayorista real vive en una fila hermana (tipo_precio = 'venta_mayorista'),
            // no en la columna precio_mayorista de arriba: es la que lee el POS y Producto::getPrecioMayoristaAttribute().
            // Es siempre global (almacen_id null) — igual que store() y show(); no existe mayorista por tienda.
            // Solo se toca cuando se edita la fila global: el formulario no envía este campo al editar una fila de tienda.
            if (is_null($precio->almacen_id)) {
                ProductoPrecio::where('producto_id', $producto->id)
                    ->where('variante_id', $precio->variante_id)
                    ->whereNull('almacen_id')
                    ->where('tipo_precio', 'venta_mayorista')
                    ->where('activo', true)
                    ->update(['activo' => false]);

                if (!empty($validated['precio_mayorista'])) {
                    $margenMay = isset($validated['margen_mayorista']) && $validated['margen_mayorista'] !== ''
                        ? $validated['margen_mayorista']
                        : ($validated['precio_compra'] > 0
                            ? round((($validated['precio_mayorista'] - $validated['precio_compra']) / $validated['precio_compra']) * 100, 2)
                            : 0);

                    ProductoPrecio::create([
                        'producto_id'   => $producto->id,
                        'variante_id'   => $precio->variante_id,
                        'almacen_id'    => null,
                        'tipo_precio'   => 'venta_mayorista',
                        'precio'        => $validated['precio_mayorista'],
                        'precio_compra' => $validated['precio_compra'],
                        'margen'        => $margenMay,
                        'proveedor_id'  => $precio->proveedor_id,
                        'activo'        => true,
                        'creado_por'    => auth()->id(),
                    ]);
                }
            }

            DB::commit();

            return redirect()
                ->route('precios.show', $producto)
                ->with('success', 'Precio actualizado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    /**
     * Ver historial de cambios de precio
     */
    public function historial(Request $request, Producto $producto)
    {
        $producto->load(['variantesActivas.color']);

        $query = ProductoPrecioHistorial::where('producto_id', $producto->id)
            ->with(['usuario', 'variante.color']);

        if ($request->filled('variante_id')) {
            $variante = \App\Models\ProductoVariante::find($request->variante_id);
            if ($variante) {
                $variantesCapacidad = $producto->variantesActivas
                    ->where('capacidad', $variante->capacidad)
                    ->pluck('id');
                $query->whereIn('variante_id', $variantesCapacidad);
            }
        }

        $historial = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $capacidades = $producto->variantesActivas->groupBy(fn($v) => $v->capacidad ?? '');

        return view('precios.historial', compact('producto', 'historial', 'capacidades'));
    }

    /**
     * Último precio unitario registrado en compras para este producto+proveedor
     */
    public function ultimoPrecioCompra(Request $request, Producto $producto)
    {
        $proveedorId = $request->get('proveedor_id');
        $varianteId  = $request->get('variante_id') ?: null;

        $detalle = DetalleCompra::with(['compra.proveedor'])
            ->where('detalle_compras.producto_id', $producto->id)
            ->join('compras', 'detalle_compras.compra_id', '=', 'compras.id')
            ->where('compras.estado', '!=', 'anulado')
            ->when($proveedorId, fn($q) => $q->where('compras.proveedor_id', $proveedorId))
            ->when($varianteId, fn($q) => $q->where('detalle_compras.variante_id', $varianteId))
            ->orderByDesc('compras.fecha')
            ->orderByDesc('detalle_compras.id')
            ->select('detalle_compras.*')
            ->first();

        if (!$detalle) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'           => true,
            'precio_unitario' => (float) $detalle->precio_unitario,
            'fecha_compra'    => $detalle->compra->fecha->format('d/m/Y'),
            'compra_codigo'   => $detalle->compra->codigo,
            'proveedor'       => [
                'id'          => $detalle->compra->proveedor->id,
                'razon_social'=> $detalle->compra->proveedor->razon_social,
            ],
        ]);
    }

    /**
     * Búsqueda dinámica de proveedores (AJAX)
     * No accede al módulo de compras — solo retorna proveedores activos
     */
    public function buscarProveedores(Request $request)
    {
        $q = trim($request->get('q', ''));

        $proveedores = Proveedor::where('estado', 'activo')
            ->where(function ($query) use ($q) {
                $query->where('razon_social', 'like', "%{$q}%")
                      ->orWhere('ruc', 'like', "%{$q}%");
            })
            ->orderBy('razon_social')
            ->limit(10)
            ->get(['id', 'razon_social', 'ruc']);

        return response()->json($proveedores);
    }

    /**
     * Aplicar precio a todas las tiendas
     */
    public function aplicarATiendas(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'precio_id' => 'required|exists:producto_precios,id',
        ]);

        $precio = ProductoPrecio::findOrFail($validated['precio_id']);

        // Aquí podrías crear registros en precios_venta por tienda
        // (cuando implementes esa tabla)

        return response()->json([
            'success' => true,
            'message' => 'Precio aplicado a todas las tiendas'
        ]);
    }
}