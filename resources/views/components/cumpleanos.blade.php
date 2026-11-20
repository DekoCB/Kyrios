{{-- Calendario de cumpleaños del personal para los dashboards. Navega por mes con ?cumple=AAAA-MM. Al abrirse también genera los avisos pendientes. --}}
@php
    $svc = app(\App\Services\CumpleanosService::class);
    try { $svc->avisar(); } catch (\Throwable $e) { report($e); }

    try { $ref = \Carbon\Carbon::createFromFormat('Y-m', (string) request('cumple', today()->format('Y-m')))->startOfMonth(); } catch (\Throwable $e) { $ref = today()->startOfMonth(); }
    $porDia = $svc->delMes($ref->year, $ref->month);
    $proximos = $svc->proximos(7);
    $primerDia = (int) $ref->copy()->startOfMonth()->isoWeekday(); // 1 = lunes
    $diasMes = $ref->daysInMonth;
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $enlace = fn ($f) => request()->fullUrlWithQuery(['cumple' => $f->format('Y-m')]);
@endphp
<section class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6" data-cumpleanos>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h2 class="font-semibold text-gray-900"><i class="fas fa-cake-candles text-pink-500 mr-2"></i>Cumpleaños del equipo</h2>
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ $enlace($ref->copy()->subMonth()) }}" class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50" title="Mes anterior"><i class="fas fa-chevron-left"></i></a>
            <span class="font-medium text-gray-800 w-36 text-center capitalize">{{ $meses[$ref->month] }} {{ $ref->year }}</span>
            <a href="{{ $enlace($ref->copy()->addMonth()) }}" class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50" title="Mes siguiente"><i class="fas fa-chevron-right"></i></a>
            @if(!$ref->isSameMonth(today()))<a href="{{ $enlace(today()) }}" class="text-blue-700 hover:underline ml-1">Hoy</a>@endif
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="grid grid-cols-7 text-center text-xs text-gray-400 uppercase mb-1">
                @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d)<div>{{ $d }}</div>@endforeach
            </div>
            <div class="grid grid-cols-7 gap-1">
                @for($i = 1; $i < $primerDia; $i++)<div></div>@endfor
                @for($dia = 1; $dia <= $diasMes; $dia++)
                    @php $hay = $porDia[$dia] ?? []; $esHoy = $ref->copy()->day($dia)->isToday(); @endphp
                    <div class="min-h-[3.25rem] rounded-lg border p-1 text-xs {{ $esHoy ? 'border-blue-500 bg-blue-50' : ($hay ? 'border-pink-200 bg-pink-50' : 'border-gray-100') }}" @if($hay) title="{{ collect($hay)->map(fn ($e) => $e->nombres . ' ' . $e->apellidos)->implode(', ') }}" @endif>
                        <div class="{{ $esHoy ? 'font-bold text-blue-700' : 'text-gray-500' }}">{{ $dia }}</div>
                        @foreach($hay as $e)
                            <div class="truncate text-pink-700 font-medium leading-tight"><i class="fas fa-cake-candles text-[10px] mr-0.5"></i>{{ \Illuminate\Support\Str::before($e->nombres, ' ') }}</div>
                        @endforeach
                    </div>
                @endfor
            </div>
        </div>

        <div>
            <p class="text-xs uppercase text-gray-400 font-medium mb-2">Próximos 7 días</p>
            <ul class="space-y-2">
                @forelse($proximos as $c)
                    <li class="flex items-center gap-3 text-sm">
                        <span class="inline-flex w-9 h-9 rounded-full bg-pink-100 text-pink-700 items-center justify-center shrink-0"><i class="fas fa-gift"></i></span>
                        <span class="flex-1 min-w-0"><span class="block font-medium text-gray-800 truncate">{{ $c['empleado']->nombres }} {{ $c['empleado']->apellidos }}</span>
                            <span class="block text-xs text-gray-500">{{ $c['fecha']->format('d/m') }} · {{ $c['faltan'] === 0 ? 'hoy' : ($c['faltan'] === 1 ? 'mañana' : 'en ' . $c['faltan'] . ' días') }}</span></span>
                    </li>
                @empty
                    <li class="text-sm text-gray-400">Ningún cumpleaños en los próximos días.</li>
                @endforelse
            </ul>
            <p class="text-xs text-gray-400 mt-4">Sale de la fecha de nacimiento en la ficha del colaborador (Recursos Humanos). Se avisa {{ \App\Services\CumpleanosService::DIAS_ANTES }} días antes.</p>
        </div>
    </div>
</section>
