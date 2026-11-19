@extends('layouts.modulo')
@section('titulo', $campana->exists ? 'Campaña' : 'Nueva campaña')
@section('subtitulo', $campana->exists ? $campana->nombre : 'Registra el anuncio y sus resultados')

@section('contenido')
@php use App\Support\Marketing; use App\Models\Marketing\Campana; $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm'; $existe = $campana->exists; @endphp
<a href="{{ route('marketing.campanas.index') }}" class="inline-block mb-4 text-sm text-gray-600 hover:text-gray-900"><i class="fas fa-arrow-left mr-1"></i>Volver a las campañas</a>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <form method="POST" action="{{ $existe ? route('marketing.campanas.update', $campana) : route('marketing.campanas.store') }}" class="lg:col-span-2 bg-white rounded-xl shadow-sm p-5 space-y-4">
        @csrf @if($existe) @method('PUT') @endif
        @if($errors->any())<ul class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-2 list-disc ml-0 pl-6">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-600 mb-1">Nombre de la campaña</label><input name="nombre" value="{{ old('nombre', $campana->nombre) }}" class="{{ $input }}" required></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Red</label>
                <select name="red" class="{{ $input }}"><option value="">Varias redes</option>@foreach(Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(old('red', $campana->red) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Objetivo</label><input name="objetivo" value="{{ old('objetivo', $campana->objetivo) }}" class="{{ $input }}" placeholder="Tráfico, mensajes, ventas…"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Estado</label>
                <select name="estado" class="{{ $input }}">@foreach(Campana::ESTADOS as $k => $v)<option value="{{ $k }}" @selected(old('estado', $campana->estado) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Moneda</label>
                <select name="moneda" class="{{ $input }}">@foreach(Campana::MONEDAS as $k => $v)<option value="{{ $k }}" @selected(old('moneda', $campana->moneda) === $k)>{{ $v }} ({{ $k }})</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Inicio</label><input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $campana->fecha_inicio?->toDateString()) }}" class="{{ $input }}"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Fin</label><input type="date" name="fecha_fin" value="{{ old('fecha_fin', $campana->fecha_fin?->toDateString()) }}" class="{{ $input }}"></div>
        </div>

        <div>
            <p class="text-xs font-semibold text-gray-600 mb-2">Dinero</p>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-gray-500 mb-1">Presupuesto asignado</label><input type="number" step="0.01" min="0" name="presupuesto" value="{{ old('presupuesto', $campana->presupuesto) }}" class="{{ $input }}" required></div>
                <div><label class="block text-xs text-gray-500 mb-1">Inversión ejecutada</label><input type="number" step="0.01" min="0" name="inversion" value="{{ old('inversion', $campana->inversion) }}" class="{{ $input }}"></div>
            </div>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-600 mb-2">Resultados (de la plataforma de anuncios)</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach(['impresiones' => 'Impresiones', 'alcance' => 'Alcance', 'clics' => 'Clics', 'conversiones' => 'Conversiones'] as $c => $etq)
                    <div><label class="block text-xs text-gray-500 mb-1">{{ $etq }}</label><input type="number" min="0" name="{{ $c }}" value="{{ old($c, $campana->$c) }}" class="{{ $input }}"></div>
                @endforeach
            </div>
            <div class="mt-3 md:w-1/2"><label class="block text-xs text-gray-500 mb-1">Ingresos atribuidos a la campaña (para el ROAS)</label>
                <input type="number" step="0.01" min="0" name="ingresos" value="{{ old('ingresos', $campana->ingresos) }}" class="{{ $input }}" placeholder="Solo si se conocen"></div>
        </div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Notas</label><textarea name="notas" rows="3" class="{{ $input }}">{{ old('notas', $campana->notas) }}</textarea></div>
        <button class="bg-blue-900 hover:bg-blue-800 text-white font-semibold px-5 py-2 rounded-lg text-sm">{{ $existe ? 'Guardar' : 'Registrar campaña' }}</button>
    </form>

    @if($existe)
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-3">Indicadores</h2>
            @foreach([
                ['Costo por clic (CPC)', $campana->cpc === null ? '—' : $campana->simbolo . ' ' . number_format($campana->cpc, 2)],
                ['CTR (clics / impresiones)', $campana->ctr === null ? '—' : $campana->ctr . ' %'],
                ['Costo por conversión', $campana->costo_por_conversion === null ? '—' : $campana->simbolo . ' ' . number_format($campana->costo_por_conversion, 2)],
                ['ROAS (retorno publicitario)', $campana->roas === null ? '— (faltan los ingresos)' : $campana->roas . 'x'],
                ['Presupuesto usado', $campana->avance_presupuesto === null ? '—' : $campana->avance_presupuesto . ' %'],
            ] as [$e, $v])
                <div class="flex justify-between py-1.5 border-t border-gray-100 text-sm"><span class="text-gray-500">{{ $e }}</span><strong>{{ $v }}</strong></div>
            @endforeach
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-2">Publicaciones de la campaña</h2>
            @forelse($campana->publicaciones as $p)
                <a href="{{ route('marketing.publicaciones.edit', $p) }}" class="block py-1.5 border-t border-gray-100 text-sm text-gray-700 hover:text-blue-700 truncate">{{ $p->titulo }} <span class="text-xs text-gray-400">· {{ $p->estado_nombre }}</span></a>
            @empty
                <p class="text-sm text-gray-500">Ninguna. Al crear una publicación puedes elegir esta campaña.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('marketing.campanas.destroy', $campana) }}" data-confirm="¿Eliminar la campaña? Sus publicaciones se conservan.">@csrf @method('DELETE')
            <button class="text-sm text-red-700 hover:text-red-900"><i class="fas fa-trash mr-1"></i>Eliminar campaña</button></form>
    </div>
    @endif
</div>
@endsection
