{{-- Pestañas del módulo de Recursos humanos (Administración). Variable: $activo --}}
@php
    $pendientesRrhh = \App\Models\SolicitudRrhh::where('estado', 'pendiente')->count();
    $pestanas = [
        'personal'    => ['Personal', 'fa-id-card', route('rrhh.empleados.index')],
        'asistencia'  => ['Por día', 'fa-user-clock', route('rrhh.asistencia.control')],
        'reporte'     => ['Por mes', 'fa-chart-column', route('rrhh.asistencia.reporte')],
        'trabajador'  => ['Por trabajador', 'fa-user-check', route('rrhh.asistencia.trabajador')],
        'lugares'     => ['Lugares de trabajo', 'fa-location-dot', route('rrhh.lugares.index')],
        'evaluaciones' => ['Evaluaciones', 'fa-star-half-stroke', route('rrhh.evaluaciones.index')],
        'huellero'    => ['Huellero', 'fa-hand-pointer', route('rrhh.asistencia.huellero')],
        'solicitudes' => ['Solicitudes', 'fa-umbrella-beach', route('rrhh.solicitudes.index')],
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-6">
    @foreach($pestanas as $clave => [$texto, $icono, $url])
        <a href="{{ $url }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium border {{ ($activo ?? '') === $clave ? 'bg-blue-700 text-white border-blue-700' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}">
            <i class="fas {{ $icono }}"></i> {{ $texto }}
            @if($clave === 'solicitudes' && $pendientesRrhh > 0)<span class="text-xs font-bold bg-red-600 text-white px-1.5 rounded-full">{{ $pendientesRrhh }}</span>@endif
        </a>
    @endforeach
</div>
