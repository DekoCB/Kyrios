{{-- Resumen de las tareas del día para el dashboard: las propias y el avance del equipo. --}}
@php
    $usuario = auth()->user();
    $hoyTareas = \App\Models\Tarea::with('asignado')->whereDate('fecha', today())->get();
    $mias = $hoyTareas->where('asignado_a', $usuario->id)->sortBy(fn ($t) => [$t->estado === 'hecha' ? 1 : 0, \App\Models\Tarea::PRIORIDADES[$t->prioridad][2]])->values();
    $atrasadas = \App\Models\Tarea::where('asignado_a', $usuario->id)->where('estado', '!=', 'hecha')->whereDate('fecha', '<', today())->count();
    $esAdminTareas = $usuario->role?->nombre === \App\Models\Role::ADMINISTRACION;
    $porPersona = $esAdminTareas ? $hoyTareas->groupBy('asignado_a')->map(fn ($g) => ['nombre' => $g->first()->asignado?->name, 'total' => $g->count(), 'hechas' => $g->where('estado', 'hecha')->count()])->sortByDesc('total')->take(6) : collect();
@endphp
<section class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6" data-tareas-hoy>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <h2 class="font-semibold text-gray-900"><i class="fas fa-list-check text-blue-700 mr-2"></i>Tareas de hoy</h2>
        <a href="{{ route('tareas.index') }}" class="text-sm text-blue-700 hover:underline">Abrir tablero <i class="fas fa-arrow-right text-xs"></i></a>
    </div>

    @if($atrasadas > 0)
        <a href="{{ route('tareas.index') }}" class="block mb-3 text-sm bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-3 py-2"><i class="fas fa-triangle-exclamation mr-1"></i>Tienes {{ $atrasadas }} tarea(s) pendiente(s) de días anteriores.</a>
    @endif

    <div class="grid md:grid-cols-2 gap-5">
        <div>
            <p class="text-xs uppercase text-gray-400 font-medium mb-2">Mis tareas · {{ $mias->where('estado', 'hecha')->count() }}/{{ $mias->count() }} hechas</p>
            <ul class="space-y-1.5">
                @forelse($mias->take(6) as $t)
                    <li class="flex items-center gap-2 text-sm">
                        <i class="{{ $t->estado === 'hecha' ? 'fas fa-circle-check text-emerald-500' : 'far fa-circle text-gray-300' }}"></i>
                        <span class="flex-1 truncate {{ $t->estado === 'hecha' ? 'line-through text-gray-400' : 'text-gray-800' }}">{{ $t->titulo }}</span>
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ \App\Models\Tarea::PRIORIDADES[$t->prioridad][1] }}">{{ \App\Models\Tarea::PRIORIDADES[$t->prioridad][0] }}</span>
                    </li>
                @empty
                    <li class="text-sm text-gray-400">No tienes tareas para hoy. <a href="{{ route('tareas.index') }}" class="text-blue-700 hover:underline">Añadir una</a></li>
                @endforelse
                @if($mias->count() > 6)<li class="text-xs text-gray-400">y {{ $mias->count() - 6 }} más…</li>@endif
            </ul>
        </div>

        @if($esAdminTareas)
            <div>
                <p class="text-xs uppercase text-gray-400 font-medium mb-2">Avance del equipo</p>
                <ul class="space-y-2">
                    @forelse($porPersona as $p)
                        <li>
                            <div class="flex justify-between text-sm"><span class="text-gray-700 truncate">{{ $p['nombre'] }}</span><span class="text-gray-500">{{ $p['hechas'] }}/{{ $p['total'] }}</span></div>
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-500" style="width: {{ $p['total'] ? round($p['hechas'] * 100 / $p['total']) : 0 }}%"></div></div>
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">Nadie tiene tareas registradas hoy.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>
</section>
