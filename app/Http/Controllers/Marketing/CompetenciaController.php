<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Competidor;
use App\Models\Marketing\CompetidorMetrica;
use App\Models\Marketing\Cuenta;
use App\Models\Marketing\Publicacion;
use App\Support\Marketing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Seguimiento de cuentas de la competencia (cargado a mano) y comparativa contra las propias. */
class CompetenciaController extends Controller
{
    public function index(Request $request)
    {
        $red = $request->filled('red') ? $request->red : null;
        $competidores = Competidor::with(['metricas' => fn ($q) => $q->orderByDesc('fecha')->limit(2)])
            ->when($red, fn ($q) => $q->where('red', $red))->orderByDesc('activo')->orderBy('red')->orderBy('nombre')->get();

        $filas = $competidores->map(function (Competidor $c) {
            [$ultima, $previa] = [$c->metricas->get(0), $c->metricas->get(1)];

            return [
                'competidor'  => $c,
                'ultima'      => $ultima,
                'crecimiento' => $ultima && $previa && $ultima->seguidores !== null && $previa->seguidores !== null ? $ultima->seguidores - $previa->seguidores : null,
                'crec_pct'    => $ultima && $previa && $previa->seguidores ? round(($ultima->seguidores - $previa->seguidores) / $previa->seguidores * 100, 1) : null,
            ];
        });

        // Lo propio en cada red, para comparar: seguidores de nuestra cuenta y publicaciones de los últimos 30 días
        $propias = Cuenta::activas()->get()->groupBy('red')->map(fn ($cs, $r) => [
            'seguidores'    => $cs->sum(fn ($c) => (int) $c->seguidores_actuales),
            'publicaciones' => Publicacion::where('red', $r)->where('estado', 'publicada')->where('publicada_en', '>=', now()->subDays(30))->count(),
        ]);

        return view('marketing.competencia.index', ['filas' => $filas, 'propias' => $propias]);
    }

    public function show(Competidor $competidor)
    {
        return view('marketing.competencia.show', ['competidor' => $competidor, 'metricas' => $competidor->metricas()->orderByDesc('fecha')->limit(60)->get()]);
    }

    private function reglas(): array
    {
        return [
            'red'     => ['required', Rule::in(array_keys(Marketing::REDES))],
            'nombre'  => 'required|string|max:120',
            'usuario' => 'nullable|string|max:120',
            'url'     => 'nullable|url|max:255',
            'notas'   => 'nullable|string|max:2000',
        ];
    }

    public function store(Request $request)
    {
        $competidor = Competidor::create($request->validate($this->reglas()));

        return redirect()->route('marketing.competencia.show', $competidor)->with('success', 'Cuenta competidora registrada. Carga sus seguidores y publicaciones.');
    }

    public function update(Request $request, Competidor $competidor)
    {
        $competidor->update($request->validate($this->reglas()) + ['activo' => $request->boolean('activo')]);

        return back()->with('success', 'Datos guardados.');
    }

    public function destroy(Competidor $competidor)
    {
        $competidor->delete();

        return redirect()->route('marketing.competencia.index')->with('success', 'Cuenta competidora eliminada con su historial.');
    }

    public function guardarMetrica(Request $request, Competidor $competidor)
    {
        $datos = $request->validate([
            'fecha'            => 'required|date|before_or_equal:today',
            'seguidores'       => 'nullable|integer|min:0|max:4000000000',
            'publicaciones'    => 'nullable|integer|min:0|max:100000',
            'interacciones'    => 'nullable|integer|min:0|max:4000000000',
            'destacada'        => 'nullable|string|max:255',
            'enlace_destacada' => 'nullable|url|max:255',
        ]);
        if (count(array_filter(array_diff_key($datos, ['fecha' => 1]), fn ($v) => $v !== null && $v !== '')) === 0) {
            return back()->withInput()->with('error', 'Indica al menos un dato (seguidores, publicaciones, interacciones o una publicación destacada).');
        }

        $m = CompetidorMetrica::firstOrNew(['competidor_id' => $competidor->id, 'fecha' => $datos['fecha']]);
        $m->fill($datos)->save();

        return back()->with('success', 'Registro del ' . $m->fecha->format('d/m/Y') . ' guardado.');
    }

    public function eliminarMetrica(Competidor $competidor, CompetidorMetrica $metrica)
    {
        abort_unless($metrica->competidor_id === $competidor->id, 404);
        $metrica->delete();

        return back()->with('success', 'Registro eliminado.');
    }
}
