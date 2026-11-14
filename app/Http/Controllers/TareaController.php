<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Tarea;
use App\Models\TareaItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tablero de tareas diarias del equipo: una columna por persona, con tareas, prioridad y lista de pasos.
 * Todos los internos ven el tablero; cada quien gestiona las suyas y Administración las de todos.
 */
class TareaController extends Controller
{
    private function esAdmin(): bool
    {
        return auth()->user()->role?->nombre === Role::ADMINISTRACION;
    }

    public function index(Request $request)
    {
        $fecha = $this->fecha($request->query('fecha'));

        $personas = User::with('role')->where('estado', 'activo')
            ->whereHas('role', fn ($q) => $q->whereIn('nombre', Role::INTERNOS))
            ->orderBy('name')->get();

        $tareas = Tarea::with('items')->whereDate('fecha', $fecha)
            ->orderBy('orden')->orderBy('id')->get();

        // Cada persona: primero la propia, luego las demás por nombre
        $yo = auth()->id();
        $personas = $personas->sortBy(fn ($u) => ($u->id === $yo ? '0' : '1') . mb_strtolower($u->name))->values();

        return view('tareas.index', [
            'fecha'    => $fecha,
            'personas' => $personas->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->name, 'rol' => $u->role?->nombre, 'iniciales' => $this->iniciales($u->name)])->all(),
            'tareas'   => $tareas->map->paraTablero()->values()->all(),
            'yo'       => $yo,
            'esAdmin'  => $this->esAdmin(),
            'pendientesAnteriores' => Tarea::where('estado', '!=', 'hecha')->whereDate('fecha', '<', $fecha)
                ->when(!$this->esAdmin(), fn ($q) => $q->where('asignado_a', $yo))->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo'      => 'required|string|max:160',
            'descripcion' => 'nullable|string|max:2000',
            'asignado_a'  => 'nullable|exists:users,id',
            'fecha'       => 'required|date',
            'prioridad'   => ['nullable', Rule::in(array_keys(Tarea::PRIORIDADES))],
        ]);

        $asignado = (int) ($datos['asignado_a'] ?? $request->user()->id);
        abort_unless($this->esAdmin() || $asignado === $request->user()->id, 403, 'Solo Administración asigna tareas a otras personas.');

        $fecha = Carbon::parse($datos['fecha'])->toDateString();
        $tarea = Tarea::create([
            'titulo' => $datos['titulo'], 'descripcion' => $datos['descripcion'] ?? null, 'asignado_a' => $asignado, 'creado_por' => $request->user()->id,
            'fecha' => $fecha, 'prioridad' => $datos['prioridad'] ?? 'media',
            'orden' => (int) Tarea::whereDate('fecha', $fecha)->where('asignado_a', $asignado)->max('orden') + 1,
        ]);

        return response()->json(['tarea' => $tarea->load('items')->paraTablero()], 201);
    }

    public function update(Request $request, Tarea $tarea): JsonResponse
    {
        $this->exigirEditable($tarea);
        $datos = $request->validate([
            'titulo'      => 'sometimes|required|string|max:160',
            'descripcion' => 'sometimes|nullable|string|max:2000',
            'fecha'       => 'sometimes|date',
            'prioridad'   => ['sometimes', Rule::in(array_keys(Tarea::PRIORIDADES))],
            'estado'      => ['sometimes', Rule::in(array_keys(Tarea::ESTADOS))],
            'asignado_a'  => 'sometimes|exists:users,id',
        ]);

        if (isset($datos['asignado_a']) && (int) $datos['asignado_a'] !== $tarea->asignado_a) {
            abort_unless($this->esAdmin(), 403, 'Solo Administración reasigna tareas.');
        }
        if (isset($datos['estado'])) {
            $datos['completada_en'] = $datos['estado'] === 'hecha' ? now() : null;
        }

        $tarea->update($datos);

        return response()->json(['tarea' => $tarea->load('items')->paraTablero()]);
    }

    public function destroy(Tarea $tarea): JsonResponse
    {
        $this->exigirEditable($tarea);
        $tarea->delete();

        return response()->json(['ok' => true]);
    }

    public function agregarItem(Request $request, Tarea $tarea): JsonResponse
    {
        $this->exigirEditable($tarea);
        $datos = $request->validate(['texto' => 'required|string|max:200']);

        $tarea->items()->create(['texto' => $datos['texto'], 'orden' => (int) $tarea->items()->max('orden') + 1]);
        // Un paso nuevo reabre una tarea que ya estaba terminada
        $this->sincronizar($tarea->refresh());

        return response()->json(['tarea' => $tarea->load('items')->paraTablero()], 201);
    }

    public function actualizarItem(Request $request, TareaItem $item): JsonResponse
    {
        $tarea = $item->tarea;
        $this->exigirEditable($tarea);
        $datos = $request->validate(['hecho' => 'sometimes|boolean', 'texto' => 'sometimes|required|string|max:200']);
        $item->update($datos);
        $this->sincronizar($tarea->refresh());

        return response()->json(['tarea' => $tarea->load('items')->paraTablero()]);
    }

    public function eliminarItem(TareaItem $item): JsonResponse
    {
        $tarea = $item->tarea;
        $this->exigirEditable($tarea);
        $item->delete();
        $this->sincronizar($tarea->refresh());

        return response()->json(['tarea' => $tarea->load('items')->paraTablero()]);
    }

    /** Pasa al día indicado las tareas que quedaron sin terminar de un día anterior (las propias; todas si es administrador). */
    public function arrastrar(Request $request)
    {
        $datos = $request->validate(['hasta' => 'required|date']);
        $hasta = Carbon::parse($datos['hasta'])->toDateString();

        $n = Tarea::where('estado', '!=', 'hecha')->whereDate('fecha', '<', $hasta)
            ->when(!$this->esAdmin(), fn ($q) => $q->where('asignado_a', $request->user()->id))
            ->update(['fecha' => $hasta]);

        return redirect()->route('tareas.index', ['fecha' => $hasta])->with('success', $n ? "{$n} tarea(s) pendiente(s) pasaron a este día." : 'No había tareas pendientes de días anteriores.');
    }

    /** Con todos los pasos marcados la tarea queda hecha; si se desmarca alguno, vuelve a estar en progreso. */
    private function sincronizar(Tarea $tarea): void
    {
        $items = $tarea->items()->get();
        if ($items->isEmpty()) {
            return;
        }

        if ($items->every->hecho) {
            $tarea->update(['estado' => 'hecha', 'completada_en' => $tarea->completada_en ?? now()]);
        } elseif ($tarea->estado === 'hecha') {
            $tarea->update(['estado' => 'en_progreso', 'completada_en' => null]);
        } elseif ($items->contains->hecho && $tarea->estado === 'pendiente') {
            $tarea->update(['estado' => 'en_progreso']);
        }
    }

    private function exigirEditable(Tarea $tarea): void
    {
        abort_unless($tarea->editablePor(auth()->user()), 403, 'Esta tarea es de otra persona.');
    }

    private function fecha(?string $valor): Carbon
    {
        try {
            return $valor ? Carbon::parse($valor)->startOfDay() : today();
        } catch (\Throwable) {
            return today();
        }
    }

    private function iniciales(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre));

        return mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . (isset($partes[1]) ? mb_substr($partes[1], 0, 1) : ''));
    }
}
