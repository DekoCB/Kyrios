@php
    $mapa = [
        'pendiente'  => ['bg-amber-100 text-amber-800', 'Pendiente'],
        'aceptada'   => ['bg-emerald-100 text-emerald-800', 'Aceptada'],
        'vencida'    => ['bg-gray-200 text-gray-700', 'Vencida'],
        'rechazada'  => ['bg-red-100 text-red-800', 'Rechazada'],
        'convertida' => ['bg-blue-100 text-blue-800', 'Convertida'],
        'anulada'    => ['bg-gray-800 text-white', 'Anulada'],
    ];
    [$clase, $texto] = $mapa[$estado] ?? ['bg-gray-100 text-gray-700', ucfirst($estado)];
@endphp
<span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full {{ $clase }}">{{ $texto }}</span>
