@php
    $clases = ['pendiente' => 'bg-amber-100 text-amber-800', 'aprobada' => 'bg-green-100 text-green-800', 'rechazada' => 'bg-red-100 text-red-800'];
    $textos = ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'];
@endphp
<span class="text-xs font-semibold px-2.5 py-0.5 rounded-full {{ $clases[$estado] ?? 'bg-gray-100 text-gray-700' }}">{{ $textos[$estado] ?? $estado }}</span>
