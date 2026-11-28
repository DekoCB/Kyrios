{{-- Pestañas del centro de costos y filtro de período. Variables: $activo, $desde, $hasta, $veVentas --}}
@php
    $rapidos = [
        'Este mes' => [today()->startOfMonth(), today()],
        'Mes pasado' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
        'Últimos 90 días' => [today()->subDays(89), today()],
        'Este año' => [today()->startOfYear(), today()],
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('centro-costos.importaciones', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium border {{ $activo === 'importaciones' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}"><i class="fas fa-ship"></i> Importaciones</a>
    @if($veVentas)
        <a href="{{ route('centro-costos.ventas', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium border {{ $activo === 'ventas' ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}"><i class="fas fa-chart-line"></i> Ventas y utilidad</a>
    @endif
</div>
<div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
    @foreach($rapidos as $texto => [$a, $b])
        <a href="{{ request()->fullUrlWithQuery(['desde' => $a->toDateString(), 'hasta' => $b->toDateString()]) }}" class="px-3 py-1.5 rounded-full border {{ $desde === $a->toDateString() && $hasta === $b->toDateString() ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">{{ $texto }}</a>
    @endforeach
</div>
