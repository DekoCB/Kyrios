<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\CostoService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Tablero de costos y márgenes (Administración y Logística, como el módulo de precios). */
class CostoController extends Controller
{
    public const ESTADOS = [
        'bajo' => 'Bajo el mínimo',
        'sin_minimo' => 'Sin margen mínimo',
        'sin_precio' => 'Sin precio de venta',
        'sin_costo' => 'Sin costo',
        'ok' => 'En regla',
    ];

    public function __construct(private CostoService $costos)
    {
    }

    public function index(Request $request)
    {
        $productos = Producto::where('estado', 'activo')
            ->with('categoria:id,nombre')
            ->when($request->filled('categoria_id'), fn ($q) => $q->where('categoria_id', $request->categoria_id))
            ->when($request->filled('buscar'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre', 'like', "%{$request->buscar}%")->orWhere('nombre_kyrios', 'like', "%{$request->buscar}%")
                ->orWhere('nombre_fabrica', 'like', "%{$request->buscar}%")->orWhere('codigo', 'like', "%{$request->buscar}%")))
            ->orderBy('nombre')->get();

        $general = CostoService::minimoGeneral();
        $precios = $this->costos->precios($productos->pluck('id')->all());
        $filas = $productos->map(function (Producto $p) use ($precios, $general) {
            $costo = $p->costo_promedio !== null ? (float) $p->costo_promedio : null;
            $precio = $this->costos->precioDe($precios, $p->id);
            $minimo = CostoService::minimoDe($p, $general);

            return (object) [
                'producto' => $p,
                'propio' => $p->margen_minimo !== null,
                'costo' => $costo,
                'ultimo' => $p->ultimo_costo_compra !== null ? (float) $p->ultimo_costo_compra : null,
                'precio' => $precio,
                'margen' => CostoService::margen($precio, $costo),
                'minimo' => $minimo,
                'estado' => CostoService::estado($costo, $precio, $minimo),
                'sugerido' => $minimo !== null ? CostoService::precioSugerido($costo, $minimo) : null,
            ];
        });

        $resumen = $filas->countBy('estado');
        $estado = $request->input('estado');
        if ($estado && isset(self::ESTADOS[$estado])) {
            $filas = $filas->where('estado', $estado)->values();
        }

        $pagina = max(1, (int) $request->input('page', 1));
        $lista = new LengthAwarePaginator($filas->forPage($pagina, 30)->values(), $filas->count(), 30, $pagina,
            ['path' => route('precios.costos.index'), 'query' => $request->except('page')]);

        return view('precios.costos.index', [
            'filas' => $lista,
            'resumen' => $resumen,
            'estados' => self::ESTADOS,
            'general' => $general,
            'categorias' => Categoria::where('estado', 'activo')->orderBy('nombre')->get(),
            'ventasBajas' => $this->costos->ventasBajoMargen(30, 50),
        ]);
    }

    public function show(Producto $producto)
    {
        $precios = $this->costos->precios([$producto->id]);
        $costo = $producto->costo_promedio !== null ? (float) $producto->costo_promedio : null;
        $precio = $this->costos->precioDe($precios, $producto->id);
        $general = CostoService::minimoGeneral();
        $minimo = CostoService::minimoDe($producto, $general);

        $variantes = $producto->variantes()->where('estado', 'activo')->get()->map(function ($v) use ($precios, $producto) {
            $c = $v->costo_promedio !== null ? (float) $v->costo_promedio : null;
            $pr = $this->costos->precioDe($precios, $producto->id, $v->id);

            return (object) ['variante' => $v, 'costo' => $c, 'ultimo' => $v->ultimo_costo_compra !== null ? (float) $v->ultimo_costo_compra : null,
                'precio' => $pr, 'margen' => CostoService::margen($pr, $c)];
        });

        return view('precios.costos.show', [
            'producto' => $producto,
            'costo' => $costo,
            'ultimo' => $producto->ultimo_costo_compra !== null ? (float) $producto->ultimo_costo_compra : null,
            'precio' => $precio,
            'margen' => CostoService::margen($precio, $costo),
            'minimo' => $minimo,
            'general' => $general,
            'estado' => CostoService::estado($costo, $precio, $minimo),
            'estados' => self::ESTADOS,
            'sugerido' => $minimo !== null ? CostoService::precioSugerido($costo, $minimo) : null,
            'variantes' => $variantes,
            'historial' => $this->costos->historial($producto->id)->take(60),
            'proveedores' => $this->costos->proveedores($producto->id),
        ]);
    }

    public function margenMinimo(Request $request, Producto $producto)
    {
        $datos = $request->validate(['margen_minimo' => 'nullable|numeric|min:0|max:1000']);
        $producto->update(['margen_minimo' => $datos['margen_minimo'] ?? null]);
        $this->costos->revisarMargenes();

        return back()->with('success', $producto->margen_minimo === null
            ? 'Se quitó el margen mínimo propio: este producto usa el margen general.'
            : 'Margen mínimo guardado.');
    }

    /** Margen mínimo general; con «aplicar a todos» se borran los mínimos propios y todos pasan a usar el general. */
    public function margenGeneral(Request $request)
    {
        $datos = $request->validate(['margen_general' => 'nullable|numeric|min:0|max:1000', 'aplicar_a_todos' => 'nullable|boolean']);
        $valor = isset($datos['margen_general']) && $datos['margen_general'] !== '' ? (float) $datos['margen_general'] : null;

        CostoService::guardarMinimoGeneral($valor);
        $limpiados = 0;
        if ($request->boolean('aplicar_a_todos')) {
            $limpiados = Producto::whereNotNull('margen_minimo')->update(['margen_minimo' => null]);
        }
        $this->costos->revisarMargenes();

        $msg = $valor === null ? 'Se quitó el margen mínimo general: solo se controlan los productos con mínimo propio.'
            : 'Margen mínimo general guardado: ' . rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.') . '%.';
        if ($limpiados) {
            $msg .= " Se quitó el mínimo propio de {$limpiados} producto(s); ahora todos usan el general.";
        }

        return back()->with('success', $msg);
    }
}
