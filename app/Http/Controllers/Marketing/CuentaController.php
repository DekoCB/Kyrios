<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Cuenta;
use App\Models\Marketing\Metrica;
use App\Models\Role;
use App\Models\User;
use App\Services\MarketingImportador;
use App\Support\Marketing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cuentas oficiales en las redes: estado de conexión, responsable y las métricas que se cargan de cada una. */
class CuentaController extends Controller
{
    private function reglas(): array
    {
        return [
            'red'            => ['required', Rule::in(array_keys(Marketing::REDES))],
            'nombre'         => 'required|string|max:120',
            'usuario'        => 'nullable|string|max:120',
            'url'            => 'nullable|url|max:255',
            'estado'         => ['required', Rule::in(array_keys(Cuenta::ESTADOS))],
            'origen'         => 'nullable|string|max:40',
            'responsable_id' => 'nullable|exists:users,id',
            'notas'          => 'nullable|string|max:2000',
        ];
    }

    private function responsables()
    {
        return User::where('estado', 'activo')->whereHas('role', fn ($q) => $q->whereIn('nombre', [Role::MARKETING, Role::ADMINISTRACION]))->orderBy('name')->get(['id', 'name']);
    }

    public function index()
    {
        $cuentas = Cuenta::with('responsable')->orderByDesc('activa')->orderBy('red')->orderBy('nombre')->get();

        return view('marketing.cuentas.index', ['cuentas' => $cuentas, 'responsables' => $this->responsables()]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate($this->reglas());
        $datos['origen'] = ($datos['origen'] ?? null) ?: 'Metricool';
        $cuenta = Cuenta::create($datos);

        return redirect()->route('marketing.cuentas.show', $cuenta)->with('success', 'Cuenta agregada. Ya puedes cargar sus métricas.');
    }

    public function show(Cuenta $cuenta)
    {
        return view('marketing.cuentas.show', [
            'cuenta'       => $cuenta->load('responsable'),
            'metricas'     => $cuenta->metricas()->orderByDesc('fecha')->limit(60)->get(),
            'responsables' => $this->responsables(),
        ]);
    }

    public function update(Request $request, Cuenta $cuenta)
    {
        $datos = $request->validate($this->reglas());
        $datos['origen'] = ($datos['origen'] ?? null) ?: 'Metricool';
        $datos['activa'] = $request->boolean('activa');
        $cuenta->update($datos);

        return back()->with('success', 'Cuenta actualizada.');
    }

    public function destroy(Cuenta $cuenta)
    {
        $cuenta->delete(); // sus métricas se van con ella; las interacciones quedan sin cuenta

        return redirect()->route('marketing.cuentas.index')->with('success', 'Cuenta eliminada junto con sus métricas.');
    }

    /** Carga (o corrige) las métricas de un día. */
    public function guardarMetrica(Request $request, Cuenta $cuenta)
    {
        $datos = $request->validate([
            'fecha'          => 'required|date|before_or_equal:today',
            'seguidores'     => 'nullable|integer|min:0|max:4000000000',
            'alcance'        => 'nullable|integer|min:0|max:4000000000',
            'impresiones'    => 'nullable|integer|min:0|max:4000000000',
            'interacciones'  => 'nullable|integer|min:0|max:4000000000',
            'reproducciones' => 'nullable|integer|min:0|max:4000000000',
            'clics'          => 'nullable|integer|min:0|max:4000000000',
            'publicaciones'  => 'nullable|integer|min:0|max:4000000000',
        ]);
        if (count(array_filter(array_diff_key($datos, ['fecha' => 1]), fn ($v) => $v !== null)) === 0) {
            return back()->withInput()->with('error', 'Indica al menos un dato (seguidores, alcance, interacciones…).');
        }

        $metrica = Metrica::firstOrNew(['cuenta_id' => $cuenta->id, 'fecha' => $datos['fecha']]);
        $metrica->fill($datos)->save();

        return back()->with('success', 'Métricas del ' . $metrica->fecha->format('d/m/Y') . ' guardadas.');
    }

    public function eliminarMetrica(Cuenta $cuenta, Metrica $metrica)
    {
        abort_unless($metrica->cuenta_id === $cuenta->id, 404);
        $metrica->delete();

        return back()->with('success', 'Registro eliminado.');
    }

    public function importar(Request $request, Cuenta $cuenta, MarketingImportador $importador)
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120'], [
            'archivo.required' => 'Elige el archivo a importar.',
            'archivo.mimes'    => 'El archivo debe ser CSV o Excel (.xlsx).',
        ]);

        try {
            $r = $importador->metricas($cuenta, $request->file('archivo'));
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo leer el archivo: ' . $e->getMessage());
        }

        $total = $r['creadas'] + $r['actualizadas'];
        if ($total === 0) {
            return back()->with('error', implode(' ', array_slice($r['errores'], 0, 3)) ?: 'No se importó ninguna fila.');
        }

        $mensaje = "Importación lista: {$r['creadas']} día(s) nuevo(s), {$r['actualizadas']} actualizado(s)" . ($r['omitidas'] ? ", {$r['omitidas']} fila(s) omitida(s)" : '') . '.';

        return back()->with('success', $mensaje)->with('avisos', array_slice($r['errores'], 0, 5));
    }
}
