{{-- Las marcaciones de un día (entrada y salida) con selfie, ubicación y, si hace falta, aprobar o rechazar. Recibe $a (Asistencia con marcaciones.lugar). --}}
@forelse($a->marcaciones as $m)
    @php [$etq, $clase, $icono] = \App\Models\AsistenciaMarcacion::UBICACIONES[$m->ubicacion]; $final = $m->estado_final; @endphp
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mb-1 text-xs">
        <span class="font-semibold text-gray-600 w-12">{{ $m->tipo === 'entrada' ? 'Entrada' : 'Salida' }}</span>
        @if($m->foto)<a href="{{ route('rrhh.marcaciones.foto', $m) }}" target="_blank" rel="noopener" title="Ver la selfie"><img src="{{ route('rrhh.marcaciones.foto', $m) }}" alt="Selfie" class="w-8 h-8 rounded object-cover border border-gray-200" loading="lazy"></a>@endif
        @if($m->revision === 'aprobada')
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold bg-green-100 text-green-800"><i class="fas fa-check"></i> Aprobada</span>
        @elseif($m->revision === 'rechazada')
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold bg-red-100 text-red-800"><i class="fas fa-xmark"></i> Rechazada</span>
        @else
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold {{ $clase }}" title="{{ $etq }}"><i class="fas {{ $icono }}"></i>
                {{ $m->ubicacion === 'valida' ? ($m->lugar?->nombre ?? 'En su lugar') : ($m->ubicacion === 'fuera' ? 'Fuera' : 'Sin ubicación') }}</span>
        @endif
        @if($m->distancia_m !== null && $m->ubicacion !== 'valida')<span class="text-gray-400">a {{ number_format($m->distancia_m) }} m</span>@endif
        @if($m->mapa_url)<a href="{{ $m->mapa_url }}" target="_blank" rel="noopener" class="text-blue-700 hover:underline"><i class="fas fa-map-location-dot"></i> mapa</a>@endif
        @if($m->ubicacion !== 'valida' && !$m->revision)
            <form method="POST" action="{{ route('rrhh.marcaciones.revisar', $m) }}" class="inline">@csrf <input type="hidden" name="revision" value="aprobada"><button class="px-1.5 py-0.5 border border-green-300 text-green-700 rounded hover:bg-green-50">Aprobar</button></form>
            <form method="POST" action="{{ route('rrhh.marcaciones.revisar', $m) }}" class="inline" data-confirm="Al rechazar la marcación el día queda como falta. ¿Continuar?">@csrf <input type="hidden" name="revision" value="rechazada"><button class="px-1.5 py-0.5 border border-red-300 text-red-700 rounded hover:bg-red-50">Rechazar</button></form>
        @endif
    </div>
@empty
    <span class="text-xs text-gray-400">{{ $a->origen === 'web' ? 'Sin foto ni ubicación' : ucfirst($a->origen) }}</span>
@endforelse
