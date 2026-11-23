<?php

namespace App\Http\Controllers;

use App\Models\Catalogo\Marca;
use App\Models\Catalogo\TipoLuminaria;
use App\Models\CatalogoLibro;
use App\Models\CatalogoLibroPagina;
use App\Models\Role;
use App\Services\LibroCatalogoService;
use App\Support\EtiquetaProducto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Catálogos de temporada: libro 3D para hojear, con páginas de productos, impresión por páginas y enlace público. */
class CatalogoLibroController extends Controller
{
    /** Tamaños de hoja del PDF en milímetros (ancho, alto). */
    public const HOJAS = ['A4' => [210, 297], 'A5' => [148, 210], 'Carta' => [215.9, 279.4]];

    public function __construct(private LibroCatalogoService $servicio)
    {
    }

    private function puedeEditar(): bool
    {
        return in_array(auth()->user()->role->nombre, [Role::ADMINISTRACION, Role::MARKETING], true);
    }

    private function volver(CatalogoLibro $c, string $tab = 'editar', string $mensaje = '')
    {
        return redirect()->to(route('catalogos.index', ['c' => $c->id, 'tab' => $tab]) . '#acciones')->with('success', $mensaje);
    }

    public function index(Request $request)
    {
        $catalogos = CatalogoLibro::orderByDesc('anio')->orderBy('temporada')->orderBy('nombre')->get();
        $sel = $catalogos->firstWhere('id', (int) $request->query('c')) ?? $catalogos->first();
        $libro = $sel ? $this->servicio->construir($sel) : null;
        $tab = in_array($request->query('tab'), ['editar', 'imprimir', 'compartir'], true) ? $request->query('tab') : 'editar';

        return view('catalogos.index', [
            'catalogos' => $catalogos,
            'sel' => $sel,
            'libro' => $libro,
            'paginas' => $sel ? $sel->paginas()->with('items.variante.producto')->get() : collect(),
            'stats' => $sel ? $this->servicio->estadisticas($sel) : null,
            'qr' => $sel ? $this->qr($sel->url_publica) : null,
            'tab' => $tab,
            'puedeEditar' => $this->puedeEditar(),
            'tiposLuminaria' => TipoLuminaria::orderBy('nombre')->get(['id', 'nombre']),
            'marcas' => Marca::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    private function qr(string $url): string
    {
        return (string) QrCode::format('svg')->size(300)->margin(0)->errorCorrection('M')->generate($url);
    }

    private function datos(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:120',
            'temporada' => ['required', Rule::in(CatalogoLibro::TEMPORADAS)],
            'anio' => 'required|integer|min:2020|max:2100',
        ], ['nombre.required' => 'Escriba un nombre para el catálogo.']);
    }

    public function store(Request $request)
    {
        $datos = $this->datos($request);
        $filtros = $request->validate(['armar' => 'nullable|boolean', 'tipo_luminaria_id' => 'nullable|integer', 'marca_id' => 'nullable|integer']);
        $c = CatalogoLibro::create($datos + ['estado' => 'borrador', 'creado_por' => auth()->id()]);
        $n = 0;
        if ($request->boolean('armar')) {
            $n = $this->servicio->armarAuto($c, ['tipo_luminaria_id' => $filtros['tipo_luminaria_id'] ?? null, 'marca_id' => $filtros['marca_id'] ?? null]);
        }

        return $this->volver($c, 'editar', $n ? "Catálogo creado con {$n} productos." . ($n >= LibroCatalogoService::MAX_AUTO ? ' Se llegó al máximo de ' . LibroCatalogoService::MAX_AUTO . '; agregue el resto a mano.' : '') : 'Catálogo creado. Agregue sus páginas.');
    }

    public function update(Request $request, CatalogoLibro $catalogo)
    {
        $datos = $this->datos($request) + $request->validate([
            'estado' => ['required', Rule::in(CatalogoLibro::ESTADOS)],
            'vigencia' => 'nullable|date',
        ]);
        $catalogo->update($datos);

        return $this->volver($catalogo, 'editar', 'Catálogo actualizado.');
    }

    public function estado(Request $request, CatalogoLibro $catalogo)
    {
        $catalogo->update(['estado' => $catalogo->estado === 'publicado' ? 'archivado' : 'publicado']);

        return $this->volver($catalogo, 'editar', $catalogo->estado === 'publicado' ? 'Catálogo publicado.' : 'Catálogo archivado.');
    }

    public function duplicar(CatalogoLibro $catalogo)
    {
        $copia = $this->servicio->duplicar($catalogo, auth()->id());

        return $this->volver($copia, 'editar', 'Se creó una copia como borrador. Cambie lo que necesite.');
    }

    public function destroy(CatalogoLibro $catalogo)
    {
        $catalogo->delete();

        return redirect()->route('catalogos.index')->with('success', 'Catálogo eliminado.');
    }

    // ── Páginas ──────────────────────────────────────────────────────────

    public function paginaStore(Request $request, CatalogoLibro $catalogo)
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(CatalogoLibroPagina::CAPACIDAD))],
            'titulo' => 'nullable|string|max:60',
            'variantes' => 'required|array|min:1',
            'variantes.*' => 'integer|exists:producto_variantes,id',
        ], ['variantes.required' => 'Marque al menos un producto.', 'variantes.min' => 'Marque al menos un producto.']);
        $ids = array_values(array_unique($datos['variantes']));
        $max = CatalogoLibroPagina::CAPACIDAD[$datos['tipo']];
        if (count($ids) > $max) {
            return back()->withErrors(['variantes' => $datos['tipo'] === 'seccion' ? 'Elija un solo producto para la foto de fondo.' : "Este diseño admite hasta {$max} productos."])->withInput();
        }
        if ($datos['tipo'] === 'seccion' && blank($datos['titulo'] ?? null)) {
            return back()->withErrors(['titulo' => 'Escriba el título de la sección.'])->withInput();
        }
        $orden = (int) $catalogo->paginas()->max('orden') + 1;
        $this->servicio->crearPagina($catalogo, $orden, $datos['tipo'], $datos['tipo'] === 'seccion' ? trim($datos['titulo']) : null, $ids);

        return $this->volver($catalogo, 'editar', 'Página agregada al final del catálogo.');
    }

    public function paginaMover(Request $request, CatalogoLibro $catalogo, CatalogoLibroPagina $pagina)
    {
        abort_unless($pagina->catalogo_id === $catalogo->id, 404);
        $sube = $request->input('direccion') === 'sube';
        $paginas = $catalogo->paginas()->get()->values();
        $i = $paginas->search(fn ($p) => $p->id === $pagina->id);
        $j = $sube ? $i - 1 : $i + 1;
        if ($paginas->has($j)) {
            [$a, $b] = [$paginas[$i], $paginas[$j]];
            [$oa, $ob] = [$a->orden, $b->orden];
            $a->update(['orden' => $ob]);
            $b->update(['orden' => $oa]);
        }

        return $this->volver($catalogo);
    }

    public function paginaDestroy(CatalogoLibro $catalogo, CatalogoLibroPagina $pagina)
    {
        abort_unless($pagina->catalogo_id === $catalogo->id, 404);
        $pagina->delete();

        return $this->volver($catalogo, 'editar', 'Página quitada.');
    }

    /** Arma las páginas solas: con los productos que ya están (agrupados) o, si no hay, con los filtros. */
    public function armar(Request $request, CatalogoLibro $catalogo)
    {
        $f = $request->validate(['todos' => 'nullable|boolean', 'tipo_luminaria_id' => 'nullable|integer', 'marca_id' => 'nullable|integer']);
        $actuales = $catalogo->paginas()->with('items')->get()->flatMap(fn ($p) => $p->tipo === 'seccion' ? [] : $p->items->pluck('variante_id'))->unique()->values()->all();
        if ($request->boolean('todos') || !$actuales) {
            $n = $this->servicio->armarAuto($catalogo, ['tipo_luminaria_id' => $f['tipo_luminaria_id'] ?? null, 'marca_id' => $f['marca_id'] ?? null]);
        } else {
            $n = $this->servicio->armarAuto($catalogo, [], $actuales);
        }

        return $this->volver($catalogo, 'editar', "Páginas armadas con {$n} productos.");
    }

    /** Buscador de productos para agregar páginas (variantes con foto). */
    public function productos(Request $request)
    {
        $vs = $this->servicio->variantesDisponibles([
            'q' => $request->query('q'), 'tipo_luminaria_id' => $request->query('tipo'), 'marca_id' => $request->query('marca'),
        ])->with('imagenPrincipal')->orderBy('producto_id')->limit(30)->get();

        return response()->json($vs->map(fn ($v) => [
            'id' => $v->id,
            'cod' => $v->codigo_kyrios,
            'nombre' => $v->producto ? EtiquetaProducto::nombre($v->producto) : 'Producto',
            'sub' => trim(($v->producto?->tipoLuminaria?->nombre ?? '') . ($v->producto?->marca ? ' · ' . $v->producto->marca->nombre : ''), ' ·'),
            'img' => $v->imagenPrincipal?->url,
        ]));
    }

    // ── Enlace público ───────────────────────────────────────────────────

    public function enlace(Request $request, CatalogoLibro $catalogo)
    {
        $d = $request->validate([
            'enlace_activo' => 'nullable|boolean',
            'dias' => ['nullable', Rule::in(['7', '30', '90', ''])],
            'precio_enlace' => ['required', Rule::in(CatalogoLibro::PRECIOS)],
        ]);
        $catalogo->update([
            'enlace_activo' => $request->boolean('enlace_activo'),
            'precio_enlace' => $d['precio_enlace'],
            'enlace_expira_at' => filled($d['dias'] ?? null) ? now()->addDays((int) $d['dias']) : null,
        ]);

        return $this->volver($catalogo, 'compartir', $catalogo->enlace_activo ? 'Enlace público guardado.' : 'Enlace público apagado.');
    }

    // ── PDF ──────────────────────────────────────────────────────────────

    public function pdf(Request $request, CatalogoLibro $catalogo)
    {
        $d = $request->validate([
            'precios' => ['nullable', Rule::in(CatalogoLibro::PRECIOS)],
            'tam' => ['nullable', Rule::in(array_keys(self::HOJAS))],
            'formato' => ['nullable', Rule::in(['una', 'libro'])],
            'paginas' => 'nullable|array',
            'paginas.*' => 'integer|min:0',
        ]);
        $libro = $this->servicio->construir($catalogo, true);
        // Páginas imprimibles en orden: portada, índice, las de producto y la contraportada
        $secuencia = array_merge([['t' => 'portada']], $libro['paginas']);
        $elegidas = collect($d['paginas'] ?? [])->unique()->sort()->values();
        $indices = $elegidas->isEmpty() ? array_keys($secuencia) : $elegidas->filter(fn ($i) => isset($secuencia[$i]))->all();
        abort_if(!$indices, 422, 'Elija al menos una página.');

        [$ancho, $alto] = self::HOJAS[$d['tam'] ?? 'A4'];
        $formato = $d['formato'] ?? 'una';
        $hojas = [];
        if ($formato === 'libro') {
            foreach ($indices as $k) {
                $j = $k === 0 ? 0 : ($k + 1) >> 1;                       // hoja física donde cae cada página
                $hojas[$j][$k === 0 || $k % 2 === 0 ? 'der' : 'izq'] = $k;
            }
            ksort($hojas);
        } else {
            foreach ($indices as $k) {
                $hojas[] = ['der' => $k];
            }
        }

        $url = $catalogo->url_publica;
        $qr = 'data:image/svg+xml;base64,' . base64_encode($this->qr($url));
        $logo = \App\Models\Empresa::logoDocumentoDataUri(null);
        $nombre = 'catalogo-' . \Illuminate\Support\Str::slug($catalogo->nombre) . '-' . ($d['precios'] ?? 'none') . '.pdf';

        return Pdf::setOptions(['isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif', 'chroot' => public_path()])
            ->loadView('catalogos.pdf', [
                'catalogo' => $catalogo, 'secuencia' => $secuencia, 'secciones' => $libro['secciones'], 'hojas' => $hojas, 'modo' => $d['precios'] ?? 'none',
                'ancho' => $ancho, 'alto' => $alto, 'doble' => $formato === 'libro', 'qr' => $qr, 'logoOscuro' => public_path('img/logo-kyrios-fondo-oscuro.png'), 'url' => $url,
            ])
            ->setPaper($formato === 'libro' ? [0, 0, $ancho * 2 * 2.8346, $alto * 2.8346] : [0, 0, $ancho * 2.8346, $alto * 2.8346])
            ->stream($nombre);
    }
}
