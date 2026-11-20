{{-- Calendario de cumpleaños para los dashboards: equipo (rosa) y clientes (ámbar). Navega por mes con ?cumple=AAAA-MM y se filtra con ?cumple_ver=equipo|clientes. Al abrirse también genera los avisos pendientes. --}}
@php
    $svc = app(\App\Services\CumpleanosService::class);
    try { $svc->avisar(); } catch (\Throwable $e) { report($e); }

    $veClientes = in_array(auth()->user()->role?->nombre, \App\Services\CumpleanosService::ROLES_VEN_CLIENTES, true);
    $ver = request('cumple_ver');
    $ver = $veClientes ? (in_array($ver, ['equipo', 'clientes'], true) ? $ver : 'todos') : 'equipo';
    $tipo = $ver === 'todos' ? null : $ver;

    try { $ref = \Carbon\Carbon::createFromFormat('Y-m', (string) request('cumple', today()->format('Y-m')))->startOfMonth(); } catch (\Throwable $e) { $ref = today()->startOfMonth(); }
    $porDia = $svc->delMes($ref->year, $ref->month, $tipo);
    $proximos = $svc->proximos(7, $tipo);
    $primerDia = (int) $ref->copy()->startOfMonth()->isoWeekday(); // 1 = lunes
    $diasMes = $ref->daysInMonth;
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $enlace = fn ($f) => request()->fullUrlWithQuery(['cumple' => $f->format('Y-m')]);
    $enlaceVer = fn ($v) => request()->fullUrlWithQuery(['cumple_ver' => $v === 'todos' ? null : $v]);
    $color = fn ($p) => $p['tipo'] === 'clientes' ? ['text-amber-700', 'bg-amber-100', 'fa-user-tie'] : ['text-pink-700', 'bg-pink-100', 'fa-cake-candles'];
@endphp
<section class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6" data-cumpleanos>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h2 class="font-semibold text-gray-900"><i class="fas fa-cake-candles text-pink-500 mr-2"></i>Cumpleaños</h2>
        @if($veClientes)
            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-sm" role="group" aria-label="Qué cumpleaños mostrar">
                @foreach(['todos' => 'Todos', 'equipo' => 'Equipo', 'clientes' => 'Clientes'] as $clave => $texto)
                    <a href="{{ $enlaceVer($clave) }}" class="px-3 py-1.5 {{ $ver === $clave ? 'bg-blue-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">{{ $texto }}</a>
                @endforeach
            </div>
        @endif
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
                    @php
                        $hay = $porDia[$dia] ?? [];
                        $esHoy = $ref->copy()->day($dia)->isToday();
                        $hayCli = collect($hay)->contains('tipo', 'clientes');
                        $hayEq = collect($hay)->contains('tipo', 'equipo');
                        $fondo = $esHoy ? 'border-blue-500 bg-blue-50' : ($hayCli && $hayEq ? 'border-orange-200 bg-orange-50' : ($hayCli ? 'border-amber-200 bg-amber-50' : ($hayEq ? 'border-pink-200 bg-pink-50' : 'border-gray-100')));
                    @endphp
                    <div class="min-h-[3.25rem] rounded-lg border p-1 text-xs {{ $fondo }}" @if($hay) title="{{ collect($hay)->map(fn ($p) => $p['nombre'] . ($p['tipo'] === 'clientes' ? ' (cliente)' : ''))->implode(', ') }}" @endif>
                        <div class="{{ $esHoy ? 'font-bold text-blue-700' : 'text-gray-500' }}">{{ $dia }}</div>
                        @foreach($hay as $p)
                            @php [$txt, , $ico] = $color($p); @endphp
                            <div class="truncate {{ $txt }} font-medium leading-tight"><i class="fas {{ $ico }} text-[10px] mr-0.5"></i>{{ \Illuminate\Support\Str::before($p['nombre'], ' ') }}</div>
                        @endforeach
                    </div>
                @endfor
            </div>
            @if($veClientes)
                <p class="flex gap-4 text-xs text-gray-500 mt-3">
                    <span><i class="fas fa-cake-candles text-pink-600 mr-1"></i>Equipo</span>
                    <span><i class="fas fa-user-tie text-amber-600 mr-1"></i>Clientes</span>
                </p>
            @endif
        </div>

        <div>
            <p class="text-xs uppercase text-gray-400 font-medium mb-2">Próximos 7 días</p>
            <ul class="space-y-2">
                @forelse($proximos as $c)
                    @php [$txt, $bg, $ico] = $color($c); @endphp
                    <li class="flex items-center gap-3 text-sm">
                        <span class="inline-flex w-9 h-9 rounded-full {{ $bg }} {{ $txt }} items-center justify-center shrink-0"><i class="fas {{ $ico }}"></i></span>
                        <span class="flex-1 min-w-0">
                            @if($c['tipo'] === 'clientes')
                                <a href="{{ route('clientes.show', $c['cliente_id']) }}" class="block font-medium text-gray-800 truncate hover:underline">{{ $c['nombre'] }}</a>
                            @else
                                <span class="block font-medium text-gray-800 truncate">{{ $c['nombre'] }}</span>
                            @endif
                            <span class="block text-xs text-gray-500">{{ $c['tipo'] === 'clientes' ? 'Cliente' : 'Equipo' }} · {{ $c['fecha']->format('d/m') }} · {{ $c['faltan'] === 0 ? 'hoy' : ($c['faltan'] === 1 ? 'mañana' : 'en ' . $c['faltan'] . ' días') }}</span>
                        </span>
                    </li>
                @empty
                    <li class="text-sm text-gray-400">Ningún cumpleaños en los próximos días.</li>
                @endforelse
            </ul>
            <p class="text-xs text-gray-400 mt-4">Equipo: fecha de nacimiento del usuario o de la ficha del colaborador. @if($veClientes)Clientes: fecha de cumpleaños de su ficha. @endif Se avisa {{ \App\Services\CumpleanosService::DIAS_ANTES }} días antes.</p>
        </div>
    </div>
</section>
