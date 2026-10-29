<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Categoria;
use App\Models\Almacen;
use App\Models\Catalogo\Marca;
use App\Models\Catalogo\Modelo;
use App\Models\Catalogo\Color;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Catalogo\ProductoSunat;
use App\Services\CodigoBarrasService;
use App\Services\VarianteService;
use App\Services\CodigoKyriosService;
use App\Models\ProductoImagen;
use App\Models\Catalogo\TipoProducto;
use App\Models\Catalogo\TipoLuminaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    /**
     * Constructor - Definir permisos por rol
     */
    public function __construct()
    {
        // Solo Admin y Almacenero pueden crear/editar
        $this->middleware('role:Administrador,Almacenero')
                ->except(['index', 'show', 'consultaTienda', 'buscarAjax']);

        
        // Solo Admin puede eliminar
        $this->middleware('role:Administrador')->only(['destroy']);
    }
    public function consultaTienda(Request $request)
{
    $query = Producto::with('categoria')->activos();
    
    // Búsqueda simple
    if ($request->filled('buscar')) {
        $query->buscar($request->buscar);
    }
    
    // Filtro por categoría
    if ($request->filled('categoria_id')) {
        $query->where('categoria_id', $request->categoria_id);
    }
    
    $productos = $query->orderBy('nombre')->paginate(20);
    $categorias = \App\Models\Categoria::activas()->orderBy('nombre')->get();
    
    return view('inventario.consulta-tienda', compact('productos', 'categorias'));
}
    /**
     * Mostrar listado de productos con filtros
     */
    public function index(Request $request)
    {
        $almacenFiltroId = $request->filled('almacen_id') ? (int) $request->almacen_id : null;

        $query = Producto::with(['categoria', 'tipoProducto', 'tipoLuminaria', 'marca', 'variantes:id,producto_id,codigo_fabricante,codigo_kyrios', 'variantesActivas.color', 'variantesActivas.imagenPrincipal']);

        if ($almacenFiltroId) {
            $query->with(['stockAlmacenes' => fn($q) => $q->where('almacen_id', $almacenFiltroId)]);
        }

        // Filtro por búsqueda
        if ($request->filled('buscar')) {
            $query->buscar($request->buscar);
        }

        // Filtro por categoría
        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        $this->aplicarFiltrosKyrios($query, $request);

        // Filtro por estado — por defecto solo 'activo' (oculta productos migrados a variantes)
        $estadoFiltro = $request->get('estado', 'activo');
        if ($estadoFiltro !== 'todos') {
            $query->where('estado', $estadoFiltro);
        }

        // Filtro por tipo de inventario
        if ($request->filled('tipo_inventario')) {
            $query->where('tipo_inventario', $request->tipo_inventario);
        }

        // Filtro por estado de stock
        if ($request->filled('stock_estado')) {
            switch ($request->stock_estado) {
                case 'bajo':
                    $query->stockBajo();
                    break;
                case 'sin_stock':
                    $query->sinStock();
                    break;
            }
        }

        // Filtro por almacén
        if ($request->filled('almacen_id')) {
            $almId = $request->almacen_id;
            $query->whereHas('stockAlmacenes', fn($sa) => $sa->where('almacen_id', $almId)->where('cantidad', '>', 0));
        }

        $productos = $query->orderBy('nombre')->paginate(15);
        $categorias = Categoria::activas()->orderBy('nombre')->get();
        $almacenes  = Almacen::activos()->orderBy('nombre')->get();
        $almacenFiltro = $almacenFiltroId ? $almacenes->find($almacenFiltroId) : null;

        // Productos inactivos/descontinuados — siempre se muestran al pie (sin paginación)
        $productosInactivos = Producto::with(['categoria'])
            ->whereIn('estado', ['inactivo', 'descontinuado'])
            ->orderBy('nombre')
            ->get();

        // Verificar permisos
        $canCreate = in_array(auth()->user()->role->nombre, ['Administrador', 'Almacenero']);
        $canEdit   = in_array(auth()->user()->role->nombre, ['Administrador', 'Almacenero']);
        $canDelete = auth()->user()->role->nombre === 'Administrador';

        $filtros = $this->opcionesFiltrosKyrios();

        return view('inventario.productos.index', compact('productos', 'categorias', 'almacenes', 'almacenFiltro', 'canCreate', 'canEdit', 'canDelete', 'productosInactivos', 'filtros'));
    }

    /**
     * Filtros de producto (nivel modelo) y de variante (acabado, tamaño, socket, daño).
     */
    private function aplicarFiltrosKyrios($query, Request $request): void
    {
        foreach (['tipo_producto_id', 'tipo_luminaria_id', 'marca_id', 'uso', 'linea', 'procedencia'] as $col) {
            if ($request->filled($col)) {
                $query->where($col, $request->input($col));
            }
        }
        // Fuente de luz / IP: el dato puede estar en el producto o en alguna de sus variantes
        if ($request->filled('fuente_luz')) {
            $fuente = $request->input('fuente_luz');
            $query->where(fn ($q) => $q->where('fuente_luz', $fuente)->orWhereHas('variantes', fn ($v) => $v->where('tipo_lampara', $fuente)));
        }
        if ($request->filled('ip')) {
            $ip = $request->input('ip');
            $query->where(fn ($q) => $q->where('ip', $ip)->orWhereHas('variantes', fn ($v) => $v->where('ip', $ip)));
        }
        foreach (['estilo' => 'estilos', 'instalacion' => 'tipos_instalacion', 'proyecto' => 'tipos_proyecto'] as $param => $col) {
            if ($request->filled($param)) {
                $query->whereJsonContains($col, $request->input($param));
            }
        }

        $variante = array_filter([
            'tamano'      => $request->input('tamano'),
            'socket'      => $request->input('socket'),
            'estado_dano' => $request->input('estado_dano'),
        ], fn ($v) => filled($v));
        $acabado = $request->input('acabado');

        if ($variante || filled($acabado)) {
            $query->whereHas('variantes', function ($q) use ($variante, $acabado) {
                foreach ($variante as $col => $val) {
                    $q->where($col, $val);
                }
                if (filled($acabado)) {
                    $q->where(fn ($w) => $w->where('acabado_1', $acabado)->orWhere('acabado_2', $acabado));
                }
            });
        }
    }

    private function opcionesFiltrosKyrios(): array
    {
        $distinto = fn (string $tabla, string $col) => \DB::table($tabla)->whereNotNull($col)->where($col, '!=', '')
            ->distinct()->orderBy($col)->pluck($col);
        // Texto plano: valor = etiqueta (primera letra en mayúscula al mostrar)
        $plano = fn ($lista) => $lista->mapWithKeys(fn ($v) => [$v => ucfirst($v)])->all();
        // JSON (estilos, instalación, proyecto): se guarda el slug y se muestra el nombre de config/kyrios.php
        $json = fn (string $col, string $cfg) => Producto::whereNotNull($col)->pluck($col)->flatten()->unique()
            ->mapWithKeys(fn ($slug) => [$slug => config("kyrios.{$cfg}.{$slug}", ucfirst($slug))])->sort()->all();

        return [
            'tipos'         => TipoProducto::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'luminarias'    => TipoLuminaria::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'marcas'        => Marca::where('estado', 'activo')->orderBy('nombre')->get(['id', 'nombre']),
            'fuentes_luz'   => $plano($distinto('productos', 'fuente_luz')->merge($distinto('producto_variantes', 'tipo_lampara'))->unique()->sort()->values()),
            'usos'          => $plano($distinto('productos', 'uso')),
            'lineas'        => $plano($distinto('productos', 'linea')),
            'estilos'       => $json('estilos', 'estilos'),
            'instalaciones' => $json('tipos_instalacion', 'tipos_instalacion'),
            'proyectos'     => $json('tipos_proyecto', 'tipos_proyecto'),
            'acabados'      => $plano($distinto('producto_variantes', 'acabado_1')),
            'tamanos'       => $plano($distinto('producto_variantes', 'tamano')),
            'sockets'       => $plano($distinto('producto_variantes', 'socket')),
            'danos'         => ['leve' => 'Daño leve', 'moderado' => 'Daño moderado', 'grave' => 'Daño grave', 'ninguno' => 'Sin daño'],
        ];
    }

    private function reglasKyrios(): array
    {
        $txt = fn (int $max) => "nullable|string|max:{$max}";

        return [
            'tipo_producto_id'  => 'nullable|exists:tipos_producto,id',
            'tipo_luminaria_id' => 'nullable|exists:tipos_luminaria,id',
            'nombre_fabrica' => $txt(150), 'nombre_kyrios' => $txt(150),
            'linea' => $txt(60), 'procedencia' => $txt(40), 'fuente_luz' => $txt(40), 'uso' => $txt(40),
            'aplicacion_2' => $txt(60), 'material_1' => $txt(60), 'material_2' => $txt(60),
            'nivel_potencia' => $txt(40), 'voltaje' => $txt(40), 'ip' => $txt(10), 'ik' => $txt(10), 'cri' => $txt(10),
            'angulo_apertura' => $txt(60), 'driver' => $txt(30), 'regulable' => 'nullable|boolean',
            'protocolo_regulacion' => $txt(80), 'ficha_tecnica' => $txt(255), 'observaciones' => 'nullable|string',
            'estilos' => 'nullable|array', 'estilos.*' => 'string|max:60|in:' . implode(',', array_keys(config('kyrios.estilos'))),
            'tipos_instalacion' => 'nullable|array', 'tipos_instalacion.*' => 'string|max:60|in:' . implode(',', array_keys(config('kyrios.tipos_instalacion'))),
            'tipos_proyecto' => 'nullable|array', 'tipos_proyecto.*' => 'string|max:60|in:' . implode(',', array_keys(config('kyrios.tipos_proyecto'))),
        ];
    }

    /** Reglas de los atributos de variante (prefijo vacío al editar, 'variante_inicial.' al crear producto). */
    private function reglasVarianteKyrios(string $prefijo = ''): array
    {
        $reglas = [
            'codigo_fabricante' => 'nullable|string|max:60', 'codigo_excel' => 'nullable|string|max:40',
            'acabado_1' => 'nullable|string|max:60', 'acabado_2' => 'nullable|string|max:60',
            'tamano' => 'nullable|string|max:20', 'socket' => 'nullable|string|max:30', 'num_lamparas' => 'nullable|string|max:10',
            'tonalidad_luz' => 'nullable|string|max:40',
            'tipo_lampara' => 'nullable|string|max:60', 'angulo_haz' => 'nullable|string|max:40',
            'protocolo_regulacion' => 'nullable|string|max:120', 'eficiencia_luminica' => 'nullable|numeric|min:0|max:9999',
            'garantia_meses' => 'nullable|integer|min:0|max:600', 'vida_util_horas' => 'nullable|integer|min:0|max:1000000',
            'ip' => 'nullable|string|max:10', 'cri' => 'nullable|string|max:10', 'otros' => 'nullable|string|max:2000',
            'diametro' => 'nullable|numeric|min:0', 'alto' => 'nullable|numeric|min:0', 'ancho' => 'nullable|numeric|min:0',
            'profundidad' => 'nullable|numeric|min:0', 'alto_suspendido' => 'nullable|numeric|min:0',
            'diametro_agujero' => 'nullable|numeric|min:0', 'potencia_w' => 'nullable|numeric|min:0', 'lumenes' => 'nullable|numeric|min:0',
            'temperatura_color' => 'nullable|integer|min:0',
            'estado_dano' => 'nullable|in:ninguno,leve,moderado,grave', 'observacion_dano' => 'nullable|string',
        ];

        return collect($reglas)->mapWithKeys(fn ($r, $k) => [$prefijo . $k => $r])->all();
    }

    /**
     * Mostrar formulario de crear producto
     */
public function create()
{
    // Verificar que los modelos existen
    if (!class_exists('App\Models\Catalogo\Marca')) {
        dd('El modelo Marca no existe');
    }
    
    // Obtener datos de inventario
    $categorias = Categoria::where('estado', 'activo')
        ->with(['marcas' => fn($q) => $q->where('estado', 'activo')])
        ->orderBy('nombre')
        ->get();
    $almacenes = Almacen::where('estado', 'activo')->orderBy('nombre')->get();
    
    // Obtener datos del catálogo
    $marcas = Marca::where('estado', 'activo')->orderBy('nombre')->get();
    $modelos = Modelo::where('estado', 'activo')->with('marca')->orderBy('nombre')->get();
    $colores = Color::where('estado', 'activo')->orderBy('nombre')->get(); // ✅ YA LO TIENES
    $unidades = UnidadMedida::where('estado', 'activo')->orderBy('nombre')->get(); // ✅ YA LO TIENES
    
    $tiposProducto = TipoProducto::activos()->orderBy('nombre')->get();
    $tiposLuminaria = TipoLuminaria::activos()->orderBy('nombre')->get();

    return view('inventario.productos.create', compact(
        'tiposProducto',
        'tiposLuminaria',
        'categorias',
        'almacenes',
        'marcas',
        'modelos',
        'colores',      // ✅ BIEN
        'unidades'      // ✅ BIEN
    ));
}

    /**
     * Guardar nuevo producto
     */
    public function store(Request $request, CodigoBarrasService $codigoBarrasService)
{
    $validated = $request->validate([
        // Campos básicos
        'nombre' => 'required|string|max:255',
        'descripcion' => 'nullable|string',
        'categoria_id' => 'required|exists:categorias,id',
        
        // ✅ NUEVO: Foreign Keys desde catálogos
        'marca_id' => 'nullable|exists:marcas,id',           // Cambiado de 'marca'
        'modelo_id' => 'nullable|exists:modelos,id',         // Cambiado de 'modelo'
        'color_id' => 'nullable|exists:colores,id',          // ✅ NUEVO
        'unidad_medida_id' => 'required|exists:unidades_medida,id', // Cambiado de 'unidad_medida'
        
        'tipo_inventario' => 'nullable|in:cantidad',
        
        // Códigos
        'codigo_barras' => 'nullable|string|max:50|unique:productos,codigo_barras',
        'codigo_sunat' => 'nullable|string|regex:/^\d{8}$/',

        // Stock
        'stock_minimo' => 'required|integer|min:0',
        'stock_maximo' => 'required|integer|min:1',
        'ubicacion' => 'nullable|string|max:100',
        
        // Imagen y estado
        'imagen' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        'estado' => 'required|in:activo,inactivo,descontinuado',
        
        // Stock inicial (solo para accesorios)
        'stock_inicial' => 'nullable|integer|min:0',
        'almacen_id' => 'nullable|required_with:stock_inicial|exists:almacenes,id',
    ] + $this->reglasKyrios() + $this->reglasVarianteKyrios('variante_inicial.'));

    // Codificación KYRIOS: el producto toma el código de su primera variante
    $correlativoPrimera = null;
    $codigoPrimeraVariante = null;
    $varianteInicial = (array) ($validated['variante_inicial'] ?? []);
    unset($validated['variante_inicial']);
    // Sin tipo elegido: el producto queda como «Sin tipo» (código ST)
    if (empty($validated['tipo_producto_id'])) {
        $validated['tipo_producto_id'] = TipoProducto::where('codigo', 'ST')->value('id');
    }
    if (!empty($validated['tipo_producto_id'])) {
        [$codigoPrimeraVariante, $correlativoPrimera] = app(CodigoKyriosService::class)->generar(
            TipoProducto::findOrFail($validated['tipo_producto_id']),
            !empty($validated['tipo_luminaria_id']) ? TipoLuminaria::find($validated['tipo_luminaria_id']) : null,
            !empty($validated['marca_id']) ? Marca::find($validated['marca_id']) : null
        );
        $validated['codigo'] = $codigoPrimeraVariante;
    }

    // Generar código automático si no existe
    if (empty($validated['codigo'])) {
        $validated['codigo'] = Producto::generarCodigo();
    }

    // Generar código de barras si no se proporcionó
    if (empty($validated['codigo_barras'])) {
        $validated['codigo_barras'] = $codigoBarrasService->generarCodigoUnico(null, 'accesorio');
    }

    // Manejar imagen
    if ($request->hasFile('imagen')) {
        $validated['imagen'] = $request->file('imagen')->store('productos', 'public');
    }

    \DB::transaction(function () use ($validated, $request, $varianteInicial, $codigoPrimeraVariante, $correlativoPrimera) {
        // Crear producto
        $producto = Producto::create($validated);

        $primeraVariante = null;
        if ($codigoPrimeraVariante) {
            $primeraVariante = ProductoVariante::create(array_merge($varianteInicial, [
                'producto_id'   => $producto->id,
                'sku'           => $codigoPrimeraVariante,
                'codigo_kyrios' => $codigoPrimeraVariante,
                'correlativo'   => $correlativoPrimera,
                'estado_dano'   => $varianteInicial['estado_dano'] ?? 'ninguno',
                'stock_actual'  => 0,
                'stock_minimo'  => $producto->stock_minimo ?? 0,
                'estado'        => 'activo',
                'creado_por'    => auth()->id(),
            ]));
        }

        \Log::info('Producto creado:', [
            'id' => $producto->id,
            'nombre' => $producto->nombre,
            'tipo_inventario' => $producto->tipo_inventario,
        ]);

        // Stock inicial SOLO para productos tipo 'cantidad' sin variantes
        if ($producto->tipo_inventario === 'cantidad' &&
            $request->filled('stock_inicial') &&
            $request->stock_inicial > 0 &&
            empty($request->variantes_iniciales)) {

            if ($request->filled('almacen_id')) {
                \App\Models\MovimientoInventario::registrarMovimiento([
                    'producto_id'     => $producto->id,
                    'variante_id'     => $primeraVariante?->id,
                    'almacen_id'      => $request->almacen_id,
                    'tipo_movimiento' => 'ingreso',
                    'cantidad'        => $request->stock_inicial,
                    'motivo'          => 'Stock inicial del producto',
                    'usuario_id'      => auth()->id(),
                ]);
            }
        }

        // Si tiene código de barras, guardarlo en la tabla de códigos múltiples
        if ($producto->codigo_barras) {
            $producto->codigosBarras()->create([
                'codigo_barras' => $producto->codigo_barras,
                'descripcion'   => 'Principal',
                'es_principal'  => true,
            ]);
        }

        // Crear variantes iniciales si las hay
        $variantesData = array_filter(
            (array)$request->input('variantes_iniciales', []),
            fn($v) => !empty($v['color_id']) || !empty($v['capacidad'])
        );
        if (!empty($variantesData)) {
            $varianteService = app(VarianteService::class);
            foreach ($variantesData as $vData) {
                $varianteService->obtenerOCrearVariante(
                    $producto,
                    !empty($vData['color_id']) ? (int)$vData['color_id'] : null,
                    !empty($vData['capacidad']) ? $vData['capacidad'] : null,
                    0
                );
            }
        }
    });

    return redirect()
        ->route('inventario.productos.index')
        ->with('success', 'Producto creado exitosamente');
}
    public function show(Producto $producto)
    {
        $producto->load([
            'categoria',
            'marca',
            'modelo',
            'color',
            'unidadMedida',
            'variantesActivas.color',
            'variantesActivas.imagenPrincipal',
            'tipoProducto',
            'tipoLuminaria',
            'movimientos' => fn($q) => $q->latest()->limit(10),
        ]);

        $stockReal = $producto->variantesActivas->isNotEmpty()
            ? $producto->variantesActivas->sum('stock_actual')
            : $producto->stock_actual;

        return view('inventario.productos.show', compact('producto', 'stockReal'));
    }

    /**
     * Mostrar formulario de editar producto
     */
    public function edit(Producto $producto)
    {
        // Cargar relaciones necesarias
        $producto->load(['marca', 'modelo', 'color', 'categoria', 'unidadMedida']);
        
        $tiposProducto = TipoProducto::activos()->orderBy('nombre')->get();
        $tiposLuminaria = TipoLuminaria::activos()->orderBy('nombre')->get();

        // Obtener datos para los selects
        $categorias = Categoria::where('estado', 'activo')->orderBy('nombre')->get();
        $marcas = \App\Models\Catalogo\Marca::where('estado', 'activo')->orderBy('nombre')->get();
        $modelos = \App\Models\Catalogo\Modelo::where('estado', 'activo')
                    ->with('marca')
                    ->orderBy('nombre')
                    ->get();
        
        // Obtener colores y unidades de medida
        $colores = \App\Models\Catalogo\Color::where('estado', 'activo')
                    ->orderBy('nombre')
                    ->get();
        
        $unidades = \App\Models\Catalogo\UnidadMedida::where('estado', 'activo')
                    ->orderBy('nombre')
                    ->get();

        // Obtener códigos de barras (opcional)
        $codigosBarras = $producto->codigosBarras ?? collect();

        // Descripción del código SUNAT ya asignado (para preseleccionar en el buscador)
        $productoSunatSeleccionado = $producto->codigo_sunat
            ? ProductoSunat::where('codigo', $producto->codigo_sunat)->first()
            : null;

        return view('inventario.productos.edit', compact(
            'producto',
            'tiposProducto',
            'tiposLuminaria',
            'categorias',
            'marcas',
            'modelos',
            'colores',
            'unidades',
            'codigosBarras',
            'productoSunatSeleccionado'
        ));
    }

    /**
     * Actualizar producto
     */
    public function update(Request $request, Producto $producto)
{
    $validated = $request->validate([
        'nombre' => 'required|string|max:200',
        'descripcion' => 'nullable|string',
        'categoria_id' => 'required|exists:categorias,id',
        
        // ✅ NUEVO: Foreign Keys
        'marca_id' => 'nullable|exists:marcas,id',
        'modelo_id' => 'nullable|exists:modelos,id',
        'color_id' => 'nullable|exists:colores,id',
        'unidad_medida_id' => 'required|exists:unidades_medida,id',
        
        'tipo_inventario' => 'nullable|in:cantidad',
        
        // Código de barras (único excepto este producto)
        'codigo_barras' => 'nullable|string|max:100|unique:productos,codigo_barras,' . $producto->id,
        'codigo_sunat' => 'nullable|string|regex:/^\d{8}$/',

        // Imagen
        'imagen' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

        // Stock
        'stock_minimo' => 'required|integer|min:0',
        'stock_maximo' => 'required|integer|min:1',
        'ubicacion' => 'nullable|string|max:50',
        'estado' => 'required|in:activo,inactivo,descontinuado',
    ] + $this->reglasKyrios());

    // El código se genera una sola vez; cambiar tipo/marca no lo altera
    if ($producto->tipo_producto_id) { unset($validated['tipo_producto_id']); }
    if ($producto->tipo_luminaria_id) { unset($validated['tipo_luminaria_id']); }

    // Subir nueva imagen si existe
    if ($request->hasFile('imagen')) {
        if ($producto->imagen) {
            Storage::disk('public')->delete($producto->imagen);
        }
        $validated['imagen'] = $request->file('imagen')->store('productos', 'public');
    }

    // Actualizar producto
    $producto->update($validated);

    // Actualizar código de barras principal si cambió
    if ($producto->wasChanged('codigo_barras')) {
        // Buscar si ya tiene un código principal
        $principal = $producto->codigosBarras()->where('es_principal', true)->first();
        
        if ($principal) {
            // Actualizar el existente
            $principal->update(['codigo_barras' => $producto->codigo_barras]);
        } else {
            // Crear nuevo
            $producto->codigosBarras()->create([
                'codigo_barras' => $producto->codigo_barras,
                'descripcion' => 'Principal',
                'es_principal' => true
            ]);
        }
    }

    return redirect()
        ->route('inventario.productos.index')
        ->with('success', 'Producto actualizado exitosamente');
}

    /**
     * Eliminar o desactivar producto.
     * Si tiene movimientos → inactivo (ocultado pero conservado).
     * Si no tiene movimientos → borrado definitivo.
     */
    public function destroy(Producto $producto)
    {
        $tieneMovimientos = $producto->movimientos()->exists()
            || \App\Models\DetalleCompra::where('producto_id', $producto->id)->exists()
            || \App\Models\DetalleVenta::where('producto_id', $producto->id)->exists();

        if ($tieneMovimientos) {
            $producto->update(['estado' => 'inactivo']);
            return redirect()
                ->route('inventario.productos.index')
                ->with('info', 'El producto tiene movimientos registrados y fue desactivado. Puedes reactivarlo desde la sección de productos inactivos.');
        }

        // Sin movimientos: borrado físico. Si algo más lo referencia (pedido, cotización, guía de remisión)
        // la base lo impide: en ese caso se desactiva en vez de fallar con un error.
        try {
            $producto->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $producto->update(['estado' => 'inactivo']);

            return redirect()
                ->route('inventario.productos.index')
                ->with('info', 'El producto está en pedidos, cotizaciones o guías y fue desactivado. Puedes reactivarlo desde la sección de productos inactivos.');
        }

        if ($producto->imagen) {
            Storage::disk('public')->delete($producto->imagen);
        }

        return redirect()
            ->route('inventario.productos.index')
            ->with('success', 'Producto eliminado correctamente.');
    }

    /**
     * Reactivar un producto inactivo/descontinuado.
     */
    public function reactivar(Producto $producto)
    {
        $producto->update(['estado' => 'activo']);
        return redirect()
            ->route('inventario.productos.index')
            ->with('success', "Producto \"{$producto->nombre}\" reactivado.");
    }
    /**
     * Mostrar gestión de códigos de barras del producto
     */
    public function codigosBarras(Producto $producto)
    {
        $codigosBarras = $producto->codigosBarras()->orderBy('es_principal', 'desc')->get();
        
        return view('inventario.productos.codigos-barras', compact('producto', 'codigosBarras'));
    }

    /**
     * Guardar nuevo código de barras
     */
    public function storeCodigoBarras(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'codigo_barras' => 'required|string|max:50|unique:productos_codigos_barras,codigo_barras',
            'descripcion' => 'nullable|string|max:100',
            'es_principal' => 'boolean'
        ]);

        // Si es principal, quitar principal de los demás
        if ($request->boolean('es_principal')) {
            $producto->codigosBarras()->update(['es_principal' => false]);
        }

        $producto->codigosBarras()->create($validated);

        return redirect()->back()->with('success', 'Código de barras agregado');
    }

    /**
     * Eliminar código de barras
     */
    public function destroyCodigoBarras($codigoBarrasId)
    {
        $codigoBarras = \App\Models\ProductoCodigoBarras::findOrFail($codigoBarrasId);
        $codigoBarras->delete();

        return redirect()->back()->with('success', 'Código de barras eliminado');
    }

    /**
 * Generar código de barras automático (AJAX)
 */
public function generarCodigoBarras(Request $request, CodigoBarrasService $codigoBarrasService)
{
    try {
        $request->validate([
            'tipo' => 'nullable|in:accesorio,otros'
        ]);

        $tipo = $request->get('tipo', 'otros');

        $codigo = $codigoBarrasService->generarCodigoUnico(null, $tipo);
        
        return response()->json([
            'success' => true,
            'codigo' => $codigo,
            'message' => 'Código generado correctamente'
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Error generando código de barras', [
            'error' => $e->getMessage(),
            'tipo' => $request->tipo
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al generar código: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Validar si un código de barras ya existe (AJAX)
 */
public function validarCodigoBarras(Request $request)
    {
        $codigo = $request->get('codigo');
        $productoId = $request->get('producto_id');
        
        $query = Producto::where('codigo_barras', $codigo);
        
        // Si estamos editando, excluir el producto actual
        if ($productoId) {
            $query->where('id', '!=', $productoId);
        }
        
        $existe = $query->exists();
        
        // También verificar en la tabla de códigos de barras múltiples
        if (!$existe && class_exists('\App\Models\ProductoCodigoBarras')) {
            $existe = \App\Models\ProductoCodigoBarras::where('codigo_barras', $codigo)->exists();
        }
        
        return response()->json([
            'existe' => $existe,
            'codigo' => $codigo
        ]);
    }
    /**
     * Establecer como principal
     */
    public function setPrincipalCodigoBarras($codigoBarrasId)
    {
        $codigoBarras = \App\Models\ProductoCodigoBarras::findOrFail($codigoBarrasId);
        
        // Quitar principal de todos los demás
        $codigoBarras->producto->codigosBarras()->update(['es_principal' => false]);
        
        // Establecer este como principal
        $codigoBarras->update(['es_principal' => true]);

        return redirect()->back()->with('success', 'Código de barras principal actualizado');
    }
    /**
     * Búsqueda AJAX para autocompletado
     */
    public function buscarAjax(Request $request)
{
    $termino = $request->get('q');
    $proveedor_id = $request->get('proveedor_id'); // Para filtrar por proveedor
    
    $query = Producto::activos()
        ->with(['marca', 'modelo', 'color', 'unidadMedida'])
        ->buscar($termino);
    
    // Si se especifica proveedor, filtrar productos que tiene ese proveedor
    if ($proveedor_id) {
        $query->whereHas('proveedores', function($q) use ($proveedor_id) {
            $q->where('proveedor_id', $proveedor_id);
        });
    }
    
    $productos = $query->limit(10)->get([
        'id', 
        'codigo', 
        'nombre', 
        'tipo_inventario',
        'marca_id',
        'modelo_id',
        'color_id',
        'unidad_medida_id'
    ]);
    
    // Formatear respuesta para el select2
    $resultados = $productos->map(function($producto) {
        return [
            'id' => $producto->id,
            'text' => $producto->codigo . ' - ' . $producto->nombre,
            'nombre' => $producto->nombre,
            'codigo' => $producto->codigo,
            'tipo_inventario' => $producto->tipo_inventario,
            'marca' => $producto->marca->nombre ?? '',
            'modelo' => $producto->modelo->nombre ?? '',
            'color' => $producto->color->nombre ?? '',
            'unidad' => $producto->unidadMedida->nombre ?? ''
        ];
    });
    
    return response()->json($resultados);
}

// ─────────────────────────────────────────────────────────────────────────────
// GESTIÓN DE VARIANTES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Mostrar variantes de un producto (vista web)
 */
public function variantes(Producto $producto)
{
    $producto->load(['variantes.color', 'variantes.imagenes', 'marca', 'modelo', 'categoria', 'tipoProducto', 'tipoLuminaria']);
    $colores = Color::where('estado', 'activo')->orderBy('nombre')->get();

    $stockTotalReal = $producto->stock_actual;

    return view('inventario.productos.variantes', compact('producto', 'colores', 'stockTotalReal'));
}

/**
 * POST /inventario/productos/{producto}/variantes
 * Crear variante desde el formulario web
 */
public function storeVariante(Request $request, Producto $producto, VarianteService $varianteService)
{
    $validated = $request->validate([
        'color_id'     => 'nullable|exists:colores,id',
        'capacidad'    => 'nullable|string|max:50',
        'sobreprecio'  => 'nullable|numeric|min:0',
        'stock_inicial'=> 'nullable|integer|min:0',
    ] + $this->reglasVarianteKyrios());

    // Productos con codificación Kyrios: la variante recibe su código KY-... automáticamente
    if ($producto->tipo_producto_id) {
        try {
            $attrs = array_intersect_key($validated, $this->reglasVarianteKyrios());
            $variante = $varianteService->crearVarianteKyrios($producto, $attrs);

            if (!empty($validated['stock_inicial']) && $validated['stock_inicial'] > 0) {
                $variante->incrementarStock($validated['stock_inicial']);
            }

            return redirect()
                ->route('inventario.productos.variantes', $producto)
                ->with('success', 'Variante agregada: ' . $variante->codigo_kyrios);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    try {
        $colorId   = $validated['color_id'] ?? null;
        $capacidad = $validated['capacidad'] ? trim($validated['capacidad']) : null;

        $yaExiste = ProductoVariante::where('producto_id', $producto->id)
            ->when($colorId,   fn($q) => $q->where('color_id', $colorId),   fn($q) => $q->whereNull('color_id'))
            ->when($capacidad, fn($q) => $q->where('capacidad', $capacidad), fn($q) => $q->whereNull('capacidad'))
            ->exists();

        if ($yaExiste) {
            return back()->withInput()->with('error', 'Ya existe una variante con ese color y capacidad para este producto.');
        }

        $variante = $varianteService->obtenerOCrearVariante(
            $producto,
            $colorId,
            $capacidad,
            (float)($validated['sobreprecio'] ?? 0)
        );

        if (!empty($validated['stock_inicial']) && $validated['stock_inicial'] > 0) {
            $variante->incrementarStock($validated['stock_inicial']);
        }

        return redirect()
            ->route('inventario.productos.variantes', $producto)
            ->with('success', 'Variante agregada: ' . $variante->sku);

    } catch (\Exception $e) {
        return back()->withInput()->with('error', $e->getMessage());
    }
}

/**
 * PUT /inventario/productos/variantes/{variante}
 * Editar variante existente
 */
public function updateVariante(Request $request, ProductoVariante $variante)
{
    $validated = $request->validate([
        'color_id'    => 'nullable|exists:colores,id',
        'capacidad'   => 'nullable|string|max:50',
        'stock_minimo'=> 'nullable|integer|min:0',
    ] + $this->reglasVarianteKyrios());

    // Variante con código Kyrios: se editan los atributos; el código no cambia
    if ($variante->codigo_kyrios) {
        $attrs = array_intersect_key($validated, $this->reglasVarianteKyrios());
        $variante->update($attrs + ['stock_minimo' => $validated['stock_minimo'] ?? $variante->stock_minimo]);

        return redirect()
            ->route('inventario.productos.variantes', $variante->producto_id)
            ->with('success', 'Variante actualizada: ' . $variante->codigo_kyrios);
    }

    // Regenerar SKU si cambió color o capacidad
    $colorCambio = ($validated['color_id']  ?? null) != $variante->color_id;
    $capCambio   = ($validated['capacidad'] ?? null) != $variante->capacidad;

    $variante->update([
        'color_id'    => $validated['color_id']  ?? null,
        'capacidad'   => $validated['capacidad'] ?? null,
        'stock_minimo'=> $validated['stock_minimo'] ?? $variante->stock_minimo,
    ]);

    if ($colorCambio || $capCambio) {
        $color = $variante->color_id ? Color::find($variante->color_id) : null;
        $nuevoSku = \App\Models\ProductoVariante::generarSku($variante->producto, $color, $variante->capacidad);
        $variante->update(['sku' => $nuevoSku]);
    }

    return redirect()
        ->route('inventario.productos.variantes', $variante->producto_id)
        ->with('success', 'Variante actualizada: ' . $variante->fresh()->sku);
}

/**
 * POST /inventario/productos/variantes/{variante}/imagenes
 * Sube imágenes de la variante (máximo 6 en total).
 */
public function storeImagenes(Request $request, ProductoVariante $variante)
{
    $request->validate([
        'imagenes'   => 'required|array|min:1',
        'imagenes.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
    ]);

    $archivos = $request->file('imagenes');
    $existentes = $variante->imagenes()->count();
    $libres = ProductoImagen::MAX_POR_VARIANTE - $existentes;

    if (count($archivos) > $libres) {
        return back()->with('error', sprintf(
            'Cada variante admite máximo %d imágenes. Tiene %d y intentó agregar %d.',
            ProductoImagen::MAX_POR_VARIANTE, $existentes, count($archivos)
        ));
    }

    $ocupados = $variante->imagenes()->pluck('orden')->all();
    foreach ($archivos as $archivo) {
        $orden = collect(range(1, ProductoImagen::MAX_POR_VARIANTE))->first(fn ($n) => !in_array($n, $ocupados, true));
        $ocupados[] = $orden;

        $variante->imagenes()->create([
            'producto_id'  => $variante->producto_id,
            'ruta'         => $archivo->store("productos/{$variante->id}", 'public'),
            'orden'        => $orden,
            'es_principal' => $existentes === 0 && count($ocupados) === 1,
        ]);
    }

    return back()->with('success', 'Imágenes agregadas a ' . ($variante->codigo_kyrios ?? $variante->sku));
}

/** DELETE /inventario/productos/imagenes/{imagen} */
public function destroyImagen(ProductoImagen $imagen)
{
    $variante = $imagen->variante;
    $eraPrincipal = $imagen->es_principal;
    Storage::disk('public')->delete($imagen->ruta);
    $imagen->delete();

    if ($eraPrincipal && ($siguiente = $variante->imagenes()->first())) {
        $siguiente->update(['es_principal' => true]);
    }

    return back()->with('success', 'Imagen eliminada');
}

/** POST /inventario/productos/imagenes/{imagen}/principal */
public function principalImagen(ProductoImagen $imagen)
{
    ProductoImagen::where('variante_id', $imagen->variante_id)->update(['es_principal' => false]);
    $imagen->update(['es_principal' => true]);

    return back()->with('success', 'Imagen principal actualizada');
}

/**
 * DELETE /inventario/productos/variantes/{variante}
 * Desactivar variante
 */
public function destroyVariante(ProductoVariante $variante, VarianteService $varianteService)
{
    try {
        $productoId = $variante->producto_id;
        $varianteService->desactivarVariante($variante);

        return redirect()
            ->route('inventario.productos.variantes', $productoId)
            ->with('success', 'Variante desactivada');
    } catch (\Exception $e) {
        return back()->with('error', $e->getMessage());
    }
}


/**
 * Asociar producto a proveedor
 */
public function asociarProveedor(Request $request, $productoId)
{
    $validated = $request->validate([
        'proveedor_id' => 'required|exists:proveedores,id',
        'codigo_proveedor' => 'nullable|string|max:100',
        'plazo_entrega_dias' => 'nullable|integer|min:0',
        'es_preferente' => 'boolean'
    ]);
    
    $producto = Producto::findOrFail($productoId);
    
    $producto->proveedores()->syncWithoutDetaching([
        $validated['proveedor_id'] => [
            'codigo_proveedor' => $validated['codigo_proveedor'] ?? null,
            'plazo_entrega_dias' => $validated['plazo_entrega_dias'] ?? 0,
            'es_preferente' => $validated['es_preferente'] ?? false
        ]
    ]);
    
    return response()->json(['success' => true]);
}
}