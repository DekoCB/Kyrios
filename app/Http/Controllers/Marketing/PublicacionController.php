<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Campana;
use App\Models\Marketing\Medio;
use App\Models\Marketing\Publicacion;
use App\Models\Producto;
use App\Support\Marketing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Publicaciones: creación, calendario, aprobación, programación y resultados. */
class PublicacionController extends Controller
{
    private const RESULTADOS = ['alcance', 'impresiones', 'interacciones', 'reproducciones', 'clics'];

    private function reglas(): array
    {
        $r = [
            'titulo'          => 'required|string|max:150',
            'texto'           => 'nullable|string|max:5000',
            'red'             => ['required', Rule::in(array_keys(Marketing::REDES))],
            'tipo'            => ['required', Rule::in(array_keys(Publicacion::TIPOS))],
            'programada_para' => 'nullable|date',
            'enlace'          => 'nullable|url|max:255',
            'campana_id'      => 'nullable|exists:mkt_campanas,id',
            'producto_id'     => 'nullable|exists:productos,id',
            'medios'          => 'nullable|array|max:20',
            'medios.*'        => 'exists:mkt_medios,id',
        ];
        foreach (self::RESULTADOS as $c) {
            $r[$c] = 'nullable|integer|min:0|max:4000000000';
        }

        return $r;
    }

    private function formulario(Publicacion $publicacion, array $extra = [])
    {
        return view('marketing.publicaciones.form', $extra + [
            'publicacion' => $publicacion,
            'campanas'    => Campana::whereIn('estado', ['planificada', 'activa', 'pausada'])->orderBy('nombre')->get(['id', 'nombre']),
            'medios'      => Medio::orderByDesc('id')->limit(200)->get(['id', 'titulo', 'tipo', 'ruta']),
            'productos'   => Producto::where('estado', 'activo')->orderBy('nombre')->limit(500)->get(['id', 'nombre', 'nombre_fabrica', 'nombre_kyrios', 'codigo']),
        ]);
    }

    public function index(Request $request)
    {
        $consulta = Publicacion::with(['creador', 'campana'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('red'), fn ($q) => $q->where('red', $request->red))
            ->when($request->filled('q'), fn ($q) => $q->where('titulo', 'like', '%' . $request->q . '%'));

        return view('marketing.publicaciones.index', [
            'publicaciones' => $consulta->orderByRaw('COALESCE(publicada_en, programada_para, created_at) DESC')->paginate(20)->withQueryString(),
            'conteo'        => Publicacion::selectRaw('estado, COUNT(*) as n')->groupBy('estado')->pluck('n', 'estado'),
        ]);
    }

    public function calendario(Request $request)
    {
        $vista = in_array($request->vista, ['mes', 'semana', 'dia'], true) ? $request->vista : 'mes';
        try {
            $fecha = $request->filled('fecha') ? Carbon::parse($request->fecha)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            $fecha = now()->startOfDay();
        }

        [$desde, $hasta, $anterior, $siguiente] = match ($vista) {
            'semana' => [$fecha->copy()->startOfWeek(), $fecha->copy()->endOfWeek(), $fecha->copy()->subWeek(), $fecha->copy()->addWeek()],
            'dia'    => [$fecha->copy()->startOfDay(), $fecha->copy()->endOfDay(), $fecha->copy()->subDay(), $fecha->copy()->addDay()],
            default  => [$fecha->copy()->startOfMonth()->startOfWeek(), $fecha->copy()->endOfMonth()->endOfWeek(), $fecha->copy()->subMonthNoOverflow(), $fecha->copy()->addMonthNoOverflow()],
        };

        $items = Publicacion::with('creador')
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('red'), fn ($q) => $q->where('red', $request->red))
            ->where(function ($q) use ($desde, $hasta) {
                $q->whereBetween('programada_para', [$desde, $hasta])->orWhereBetween('publicada_en', [$desde, $hasta]);
            })->get()->sortBy(fn ($p) => $p->fecha_calendario)->groupBy(fn ($p) => $p->fecha_calendario->toDateString());

        $dias = [];
        for ($d = $desde->copy(); $d->lte($hasta); $d->addDay()) {
            $dias[] = $d->copy();
        }

        return view('marketing.publicaciones.calendario', compact('vista', 'fecha', 'desde', 'hasta', 'anterior', 'siguiente', 'items', 'dias'));
    }

    public function create(Request $request)
    {
        $publicacion = new Publicacion(['red' => 'instagram', 'tipo' => 'publicacion', 'estado' => 'pendiente']);
        if ($request->filled('fecha')) {
            try {
                $publicacion->programada_para = Carbon::parse($request->fecha)->setTime(10, 0);
            } catch (\Throwable) {
            }
        }

        return $this->formulario($publicacion);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        $medios = $datos['medios'] ?? [];
        unset($datos['medios']);

        $publicacion = Publicacion::create($datos + ['estado' => 'pendiente', 'creado_por' => auth()->id()]);
        $publicacion->medios()->sync($medios);

        return redirect()->route('marketing.publicaciones.edit', $publicacion)->with('success', 'Publicación creada. Queda pendiente de aprobación.');
    }

    public function edit(Publicacion $publicacion)
    {
        return $this->formulario($publicacion->load('medios', 'creador', 'aprobador'));
    }

    public function update(Request $request, Publicacion $publicacion)
    {
        $datos = $request->validate($this->reglas());
        // El formulario manda «medios_form» para distinguir «ninguno marcado» de «no se tocaron los medios»
        $medios = $request->has('medios_form') ? ($datos['medios'] ?? []) : $publicacion->medios()->pluck('mkt_medios.id')->all();
        unset($datos['medios']);

        if ($publicacion->estado === 'publicada') {
            // Ya salió: solo se corrigen el enlace, la campaña y los resultados
            $publicacion->update(array_intersect_key($datos, array_flip(array_merge(['enlace', 'campana_id'], self::RESULTADOS))));

            return back()->with('success', 'Resultados guardados.');
        }

        $actual = $publicacion->medios()->pluck('mkt_medios.id')->sort()->values()->all();
        $nuevo = collect($medios)->map(fn ($m) => (int) $m)->sort()->values()->all();
        $cambioContenido = $publicacion->titulo !== $datos['titulo'] || (string) $publicacion->texto !== (string) ($datos['texto'] ?? '')
            || $publicacion->red !== $datos['red'] || $publicacion->tipo !== $datos['tipo'] || $actual !== $nuevo;

        $publicacion->fill($datos);
        $volvio = false;
        // Si se cambia lo que se va a publicar, hay que volver a aprobarlo; reprogramar la fecha no lo pide
        if ($cambioContenido && in_array($publicacion->estado, ['aprobada', 'programada'], true)) {
            $publicacion->fill(['estado' => 'pendiente', 'aprobado_por' => null, 'aprobada_en' => null]);
            $volvio = true;
        }
        if ($publicacion->estado === 'programada' && !$publicacion->programada_para) {
            $publicacion->estado = 'aprobada';
        }
        $publicacion->save();
        $publicacion->medios()->sync($medios);

        return back()->with('success', $volvio ? 'Guardado. Como cambió el contenido, vuelve a quedar pendiente de aprobación.' : 'Publicación guardada.');
    }

    public function cambiarEstado(Request $request, Publicacion $publicacion)
    {
        $request->validate([
            'estado'          => ['required', Rule::in(array_keys(Publicacion::ESTADOS))],
            'motivo'          => 'nullable|string|max:255',
            'programada_para' => 'nullable|date',
            'publicada_en'    => 'nullable|date',
            'enlace'          => 'nullable|url|max:255',
        ]);
        $nuevo = $request->estado;

        if (!$publicacion->puedePasarA($nuevo)) {
            return back()->with('error', "Una publicación {$publicacion->estado_nombre} no puede pasar a {$nuevo}.");
        }

        $cambios = ['estado' => $nuevo];
        switch ($nuevo) {
            case 'aprobada':
                $cambios += ['aprobado_por' => auth()->id(), 'aprobada_en' => now(), 'motivo_rechazo' => null];
                break;
            case 'programada':
                $fecha = $request->input('programada_para') ?: $publicacion->programada_para;
                if (!$fecha) {
                    return back()->with('error', 'Indica la fecha y hora para programarla.');
                }
                $cambios['programada_para'] = $fecha;
                break;
            case 'publicada':
                $cambios['publicada_en'] = $request->input('publicada_en') ?: now();
                if ($request->filled('enlace')) {
                    $cambios['enlace'] = $request->enlace;
                }
                break;
            case 'rechazada':
                if (!$request->filled('motivo')) {
                    return back()->with('error', 'Escribe el motivo del rechazo para que se pueda corregir.');
                }
                $cambios += ['motivo_rechazo' => $request->motivo, 'aprobado_por' => null, 'aprobada_en' => null];
                break;
            case 'pendiente':
                $cambios += ['aprobado_por' => null, 'aprobada_en' => null];
                break;
        }
        $publicacion->update($cambios);

        return back()->with('success', 'La publicación ahora está ' . strtolower(Publicacion::ESTADOS[$nuevo]) . '.');
    }

    /** Copia la publicación a otra red (queda pendiente, sin fecha de publicación ni resultados). */
    public function duplicar(Request $request, Publicacion $publicacion)
    {
        $request->validate(['red' => ['required', Rule::in(array_keys(Marketing::REDES))]]);

        $copia = $publicacion->replicate(['estado', 'publicada_en', 'enlace', 'aprobado_por', 'aprobada_en', 'motivo_rechazo', ...self::RESULTADOS]);
        $copia->fill(['red' => $request->red, 'estado' => 'pendiente', 'creado_por' => auth()->id()])->save();
        $copia->medios()->sync($publicacion->medios()->pluck('mkt_medios.id')->all());

        return redirect()->route('marketing.publicaciones.edit', $copia)->with('success', 'Copia creada para ' . Marketing::nombreRed($request->red) . '.');
    }

    public function destroy(Publicacion $publicacion)
    {
        if ($publicacion->estado === 'publicada') {
            return back()->with('error', 'Una publicación ya publicada no se elimina: queda en el historial de resultados.');
        }
        $publicacion->delete();

        return redirect()->route('marketing.publicaciones.index')->with('success', 'Publicación eliminada.');
    }
}
