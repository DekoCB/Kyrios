@extends('layouts.modulo')
@section('titulo', $publicacion->exists ? 'Publicación' : 'Nueva publicación')
@section('subtitulo', $publicacion->exists ? $publicacion->titulo : 'Se crea pendiente de aprobación')

@section('contenido')
@php
    use App\Support\Marketing; use App\Models\Marketing\Publicacion;
    $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm';
    $existe = $publicacion->exists;
    $publicada = $publicacion->estado === 'publicada';
    $marcados = collect(old('medios', $existe ? $publicacion->medios->pluck('id')->all() : []))->map(fn ($m) => (int) $m)->all();
@endphp
<a href="{{ route('marketing.publicaciones.index') }}" class="inline-block mb-4 text-sm text-gray-600 hover:text-gray-900"><i class="fas fa-arrow-left mr-1"></i>Volver a las publicaciones</a>

@if($existe)
<div class="bg-white rounded-xl shadow-sm p-5 mb-6">
    <div class="flex flex-wrap items-center gap-3">
        <span class="px-3 py-1 rounded-full text-sm font-semibold {{ Publicacion::COLORES[$publicacion->estado] }}">{{ $publicacion->estado_nombre }}</span>
        <span class="text-sm text-gray-500">
            Creada por {{ $publicacion->creador?->name ?? '—' }}
            @if($publicacion->aprobador) · aprobada por {{ $publicacion->aprobador->name }} el {{ $publicacion->aprobada_en?->format('d/m/Y H:i') }}@endif
        </span>
    </div>
    @if($publicacion->estado === 'rechazada')
        <p class="mt-3 text-sm bg-red-50 border border-red-200 text-red-800 rounded-lg px-3 py-2"><strong>Motivo del rechazo:</strong> {{ $publicacion->motivo_rechazo }}</p>
    @endif

    <div class="mt-4 flex flex-wrap items-start gap-3">
        @if($publicacion->estado === 'pendiente')
            <form method="POST" action="{{ route('marketing.publicaciones.estado', $publicacion) }}">@csrf <input type="hidden" name="estado" value="aprobada">
                <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-green-600 hover:bg-green-700 text-white"><i class="fas fa-check mr-1"></i>Aprobar</button></form>
        @endif
        @if($publicacion->puedePasarA('rechazada'))
            <form method="POST" action="{{ route('marketing.publicaciones.estado', $publicacion) }}" class="flex gap-2">@csrf <input type="hidden" name="estado" value="rechazada">
                <input name="motivo" placeholder="Motivo del rechazo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 hover:bg-red-700 text-white"><i class="fas fa-xmark mr-1"></i>Rechazar</button></form>
        @endif
        @if($publicacion->puedePasarA('programada'))
            <form method="POST" action="{{ route('marketing.publicaciones.estado', $publicacion) }}" class="flex gap-2">@csrf <input type="hidden" name="estado" value="programada">
                <input type="datetime-local" name="programada_para" value="{{ $publicacion->programada_para?->format('Y-m-d\TH:i') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white"><i class="fas fa-clock mr-1"></i>Programar</button></form>
        @endif
        @if($publicacion->puedePasarA('publicada'))
            <form method="POST" action="{{ route('marketing.publicaciones.estado', $publicacion) }}" class="flex gap-2 flex-wrap">@csrf <input type="hidden" name="estado" value="publicada">
                <input type="url" name="enlace" value="{{ $publicacion->enlace }}" placeholder="Enlace de la publicación (opcional)" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
                <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-900 hover:bg-blue-800 text-white"><i class="fas fa-paper-plane mr-1"></i>Marcar como publicada</button></form>
        @endif
        @foreach(['pendiente' => 'Volver a pendiente', 'aprobada' => 'Volver a aprobada'] as $dest => $etq)
            @if(($dest === 'pendiente' && in_array($publicacion->estado, ['aprobada', 'rechazada'], true)) || ($dest === 'aprobada' && $publicacion->estado === 'programada'))
                <form method="POST" action="{{ route('marketing.publicaciones.estado', $publicacion) }}">@csrf <input type="hidden" name="estado" value="{{ $dest }}">
                    <button class="px-3 py-2 rounded-lg text-sm border border-gray-300 text-gray-700 hover:bg-gray-50">{{ $publicacion->estado === 'rechazada' ? 'Corregir y volver a pendiente' : $etq }}</button></form>
            @endif
        @endforeach
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
<form id="form-pub" method="POST" action="{{ $existe ? route('marketing.publicaciones.update', $publicacion) : route('marketing.publicaciones.store') }}" class="lg:col-span-2">
    @csrf @if($existe) @method('PUT') @endif
    <input type="hidden" name="medios_form" value="1">
    <div class="space-y-4 bg-white rounded-xl shadow-sm p-5">
        @if($publicada)<p class="text-sm bg-green-50 border border-green-200 text-green-800 rounded-lg px-3 py-2">Ya está publicada: aquí solo se corrigen el enlace, la campaña y los resultados.</p>@endif
        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Título (interno)</label>
            <input name="titulo" value="{{ old('titulo', $publicacion->titulo) }}" class="{{ $input }}" required @readonly($publicada)>
            @error('titulo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Texto de la publicación</label>
            <textarea name="texto" rows="6" class="{{ $input }}" @readonly($publicada)>{{ old('texto', $publicacion->texto) }}</textarea></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Red</label>
                <select name="red" class="{{ $input }}" @disabled($publicada)>@foreach(Marketing::redes() as $k => $v)<option value="{{ $k }}" @selected(old('red', $publicacion->red) === $k)>{{ $v }}</option>@endforeach</select>
                @if($publicada)<input type="hidden" name="red" value="{{ $publicacion->red }}">@endif</div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Formato</label>
                <select name="tipo" class="{{ $input }}" @disabled($publicada)>@foreach(Publicacion::TIPOS as $k => $v)<option value="{{ $k }}" @selected(old('tipo', $publicacion->tipo) === $k)>{{ $v }}</option>@endforeach</select>
                @if($publicada)<input type="hidden" name="tipo" value="{{ $publicacion->tipo }}">@endif</div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Fecha y hora previstas</label>
                <input type="datetime-local" name="programada_para" value="{{ old('programada_para', $publicacion->programada_para?->format('Y-m-d\TH:i')) }}" class="{{ $input }}" @readonly($publicada)>
                @error('programada_para')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Campaña</label>
                <select name="campana_id" class="{{ $input }}"><option value="">Ninguna</option>@foreach($campanas as $c)<option value="{{ $c->id }}" @selected((int) old('campana_id', $publicacion->campana_id) === $c->id)>{{ $c->nombre }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1">Producto del catálogo</label>
                <select name="producto_id" class="{{ $input }}" @disabled($publicada)><option value="">Ninguno</option>@foreach($productos as $p)<option value="{{ $p->id }}" @selected((int) old('producto_id', $publicacion->producto_id) === $p->id)>{{ \App\Support\EtiquetaProducto::nombre($p) }}</option>@endforeach</select>
                @if($publicada)<input type="hidden" name="producto_id" value="{{ $publicacion->producto_id }}">@endif</div>
        </div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Enlace de la publicación ya publicada</label>
            <input type="url" name="enlace" value="{{ old('enlace', $publicacion->enlace) }}" class="{{ $input }}" placeholder="https://…">
            @error('enlace')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>

        @if($existe && in_array($publicacion->estado, ['publicada']))
            <div>
                <p class="text-xs font-semibold text-gray-600 mb-2">Resultados de la publicación</p>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    @foreach(['alcance' => 'Alcance', 'impresiones' => 'Impresiones', 'interacciones' => 'Interacciones', 'reproducciones' => 'Reproducciones', 'clics' => 'Clics'] as $c => $etq)
                        <div><label class="block text-xs text-gray-500 mb-1">{{ $etq }}</label><input type="number" min="0" name="{{ $c }}" value="{{ old($c, $publicacion->$c) }}" class="{{ $input }}"></div>
                    @endforeach
                </div>
                @if($publicacion->engagement !== null)<p class="text-sm text-gray-600 mt-2">Engagement: <strong>{{ Marketing::porcentaje($publicacion->engagement) }}</strong></p>@endif
            </div>
        @endif

        <div class="flex gap-3 pt-2">
            <button class="bg-blue-900 hover:bg-blue-800 text-white font-semibold px-5 py-2 rounded-lg text-sm">{{ $existe ? 'Guardar' : 'Crear publicación' }}</button>
        </div>
    </div>
</form>

    <div class="space-y-4">
        @unless($publicada)
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-bold text-gray-800 mb-1">Imágenes y videos</h2>
            <p class="text-xs text-gray-500 mb-3">Elige de la <a href="{{ route('marketing.medios.index') }}" class="text-blue-700 hover:underline" target="_blank">biblioteca multimedia</a>. Si cambias el contenido de una publicación aprobada, vuelve a pendiente.</p>
            <div class="grid grid-cols-3 gap-2 max-h-80 overflow-y-auto">
                @forelse($medios as $m)
                    <label class="relative cursor-pointer" title="{{ $m->titulo }}">
                        <input type="checkbox" form="form-pub" name="medios[]" value="{{ $m->id }}" class="peer sr-only" @checked(in_array($m->id, $marcados, true))>
                        <div class="border-2 border-gray-200 peer-checked:border-blue-600 peer-checked:ring-2 peer-checked:ring-blue-200 rounded-lg overflow-hidden aspect-square bg-gray-100 flex items-center justify-center">
                            @if($m->tipo === 'imagen')<img src="{{ $m->url }}" alt="{{ $m->titulo }}" class="w-full h-full object-cover" loading="lazy">
                            @else<i class="fas {{ $m->tipo === 'video' ? 'fa-film' : 'fa-file' }} text-2xl text-gray-400"></i>@endif
                        </div>
                        <span class="block text-[10px] text-gray-600 truncate mt-0.5">{{ $m->titulo }}</span>
                    </label>
                @empty
                    <p class="col-span-3 text-sm text-gray-500">La biblioteca está vacía.</p>
                @endforelse
            </div>
        </div>
        @endunless

        @if($existe)
        <div class="bg-white rounded-xl shadow-sm p-5 space-y-3">
            <form method="POST" action="{{ route('marketing.publicaciones.duplicar', $publicacion) }}" class="flex gap-2">@csrf
                <select name="red" class="{{ $input }}">@foreach(Marketing::redes() as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                <button class="px-3 py-2 rounded-lg text-sm border border-gray-300 hover:bg-gray-50 whitespace-nowrap" title="Copia esta publicación a otra red">Copiar a…</button>
            </form>
            @unless($publicada)
            <form method="POST" action="{{ route('marketing.publicaciones.destroy', $publicacion) }}" data-confirm="¿Eliminar esta publicación?">@csrf @method('DELETE')
                <button class="text-sm text-red-700 hover:text-red-900"><i class="fas fa-trash mr-1"></i>Eliminar publicación</button></form>
            @endunless
        </div>
        @endif
    </div>
</div>
@endsection
