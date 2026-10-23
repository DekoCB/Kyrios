<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $caja->codigo }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    @php
        $estados = [
            'pendiente' => ['Por revisar', 'bg-amber-100 text-amber-800'],
            'aprobado'  => ['Aprobado', 'bg-green-100 text-green-800'],
            'observado' => ['Observado', 'bg-orange-100 text-orange-800'],
            'rechazado' => ['Rechazado', 'bg-red-100 text-red-800'],
        ];
    @endphp

    <div class="md:ml-64 p-4 md:p-8">
        <x-header :title="$caja->nombre" :subtitle="$caja->codigo . ' · ' . ($caja->tipo === 'general' ? 'Caja general' : 'Asignada a ' . ($caja->responsable?->name ?? '—'))" />

        @include('cotizaciones.partials.mensajes')

        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('caja-chica.index') }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-arrow-left"></i> Volver</a>
            <a href="{{ route('caja-chica.rendicion', $caja) }}" target="_blank" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-file-pdf text-red-500"></i> Rendición PDF</a>
            <a href="{{ route('caja-chica.exportar', $caja) }}" class="inline-flex items-center gap-2 border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-file-csv text-green-600"></i> Exportar</a>
            @if($esAdmin && $caja->esta_activa)
                <form method="POST" action="{{ route('caja-chica.cerrar', $caja) }}" class="ml-auto" data-confirm="¿Cerrar esta caja chica? Ya no admitirá movimientos.">
                    @csrf
                    <button class="inline-flex items-center gap-2 border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 px-4 py-2 rounded-xl text-sm font-medium shadow-sm"><i class="fas fa-lock"></i> Cerrar caja</button>
                </form>
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-emerald-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Saldo disponible</p>
                <p class="text-2xl font-bold {{ $resumen['saldo'] < 0 ? 'text-red-600' : 'text-gray-900' }}">S/ {{ number_format($resumen['saldo'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Dinero entregado</p>
                <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($resumen['ingresos'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-rose-400">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Gastado</p>
                <p class="text-2xl font-bold text-gray-900">S/ {{ number_format($resumen['gastos'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-amber-400">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Por revisar</p>
                <p class="text-2xl font-bold text-gray-900">{{ $resumen['por_revisar'] }}</p>
            </div>
        </div>

        @if($caja->esta_activa)
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="xl:col-span-2 bg-white rounded-xl shadow-sm p-5">
                    <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-receipt mr-1 text-gray-400"></i>Registrar gasto</h3>
                    @include('caja-chica.partials.form-gasto', ['action' => route('caja-chica.gasto', $caja), 'metodo' => null, 'mov' => null, 'boton' => 'Guardar gasto'])
                </div>

                @if($esAdmin)
                    <div class="bg-white rounded-xl shadow-sm p-5 self-start">
                        <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-hand-holding-usd mr-1 text-gray-400"></i>Entregar / reponer dinero</h3>
                        <form method="POST" action="{{ route('caja-chica.ingreso', $caja) }}" class="space-y-3">
                            @csrf
                            <input type="number" name="monto" step="0.01" min="0.01" required placeholder="Monto (S/)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <input type="text" name="concepto" required maxlength="255" value="Reposición" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <input type="date" name="fecha" required max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Registrar ingreso</button>
                        </form>
                    </div>
                @endif
            </div>
        @else
            <div class="bg-gray-100 border-l-4 border-gray-400 text-gray-700 p-4 mb-6 rounded text-sm"><i class="fas fa-lock mr-2"></i>Caja cerrada el {{ $caja->cerrada_en?->format('d/m/Y H:i') }}. No admite más movimientos.</div>
        @endif

        {{-- Movimientos --}}
        <form method="GET" class="flex flex-wrap items-center gap-2 mb-3">
            <select name="estado" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todos los estados</option>
                @foreach($estados as $k => [$nombre])<option value="{{ $k }}" @selected(request('estado') === $k)>{{ $nombre }}</option>@endforeach
            </select>
            <select name="categoria" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">Todas las categorías</option>
                @foreach(\App\Models\CajaChicaMovimiento::CATEGORIAS as $k => $v)<option value="{{ $k }}" @selected(request('categoria') === $k)>{{ $v }}</option>@endforeach
            </select>
        </form>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detalle</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comprobante</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($movimientos as $m)
                        @php($puedeTocar = $caja->esta_activa && ($esAdmin || ($m->user_id === auth()->id() && $m->tipo === 'gasto' && in_array($m->estado, ['pendiente', 'observado'], true))))
                        <tr class="align-top hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $m->fecha->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $m->concepto }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $m->tipo === 'ingreso' ? 'Ingreso' : $m->categoria_nombre }} · {{ $m->registrador?->name }}
                                    @if($m->emisor) · {{ $m->emisor }}@endif
                                </p>
                                @if($m->motivo_revision)<p class="text-xs text-amber-700 mt-1"><i class="fas fa-comment-dots mr-1"></i>{{ $m->motivo_revision }}</p>@endif
                            </td>
                            <td class="px-4 py-3">
                                @if($m->tipo === 'gasto')
                                    <p class="text-gray-700">{{ $m->comprobante_nombre }} {{ $m->numero_comprobante }}</p>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach($m->adjuntos as $a)
                                            <a href="{{ route('caja-chica.adjunto', $a) }}" target="_blank" title="{{ $a->nombre_original }}" class="block">
                                                @if($a->es_imagen)<img src="{{ route('caja-chica.adjunto', $a) }}" alt="Comprobante" loading="lazy" class="h-12 w-12 object-cover rounded border">
                                                @else<span class="flex h-12 w-12 items-center justify-center rounded border bg-red-50 text-red-500"><i class="fas fa-file-pdf text-xl"></i></span>@endif
                                            </a>
                                        @endforeach
                                    </div>
                                @else — @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap {{ $m->tipo === 'ingreso' ? 'text-emerald-700' : 'text-gray-900' }} {{ $m->estado === 'rechazado' ? 'line-through opacity-60' : '' }}">
                                {{ $m->tipo === 'ingreso' ? '+' : '−' }} S/ {{ number_format($m->monto, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                @if($m->tipo === 'gasto')
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $estados[$m->estado][1] }}">{{ $estados[$m->estado][0] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($esAdmin && $m->tipo === 'gasto' && $caja->esta_activa)
                                    <form method="POST" action="{{ route('caja-chica.revisar', $m) }}" class="inline-flex items-center gap-1 mb-1" x-data="{ d: 'aprobar' }">
                                        @csrf
                                        <select name="decision" x-model="d" class="px-2 py-1 border border-gray-300 rounded text-xs">
                                            <option value="aprobar">Aprobar</option><option value="observar">Observar</option><option value="rechazar">Rechazar</option>
                                        </select>
                                        <input type="text" name="motivo" x-show="d !== 'aprobar'" :required="d !== 'aprobar'" placeholder="Motivo" class="px-2 py-1 border border-gray-300 rounded text-xs w-28">
                                        <button class="text-xs bg-gray-800 hover:bg-gray-900 text-white px-2 py-1 rounded">OK</button>
                                    </form><br>
                                @endif
                                @if($puedeTocar && $m->tipo === 'gasto')
                                    <a href="{{ route('caja-chica.gasto.edit', $m) }}" class="text-yellow-600 hover:text-yellow-800 mr-2" title="Editar"><i class="fas fa-edit"></i></a>
                                @endif
                                @if($puedeTocar)
                                    <form method="POST" action="{{ route('caja-chica.movimiento.destroy', $m) }}" class="inline" data-confirm="¿Eliminar este movimiento y sus archivos?">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:text-red-800" title="Eliminar"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Todavía no hay movimientos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $movimientos->links() }}</div>
    </div>
</body>
</html>
