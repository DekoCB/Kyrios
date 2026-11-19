@extends('layouts.modulo')
@section('titulo', 'Biblioteca multimedia')
@section('subtitulo', 'Imágenes, videos y diseños para las publicaciones')

@section('contenido')
@php use App\Models\Marketing\Medio; $input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm'; @endphp
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <div class="lg:col-span-3">
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4 mb-4 flex flex-wrap items-center gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="Buscar por título o etiqueta" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
            <select name="tipo" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white"><option value="">Todos los tipos</option>@foreach(Medio::TIPOS as $k => $v)<option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $v }}</option>@endforeach</select>
            <button class="px-4 py-1.5 rounded-lg text-sm font-semibold text-blue-700 border border-blue-300 hover:bg-blue-50">Filtrar</button>
        </form>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            @forelse($medios as $m)
                <div class="bg-white rounded-xl shadow-sm overflow-hidden flex flex-col" x-data="{ editar: false }">
                    <a href="{{ $m->url }}" target="_blank" rel="noopener" class="block aspect-video bg-gray-100 flex items-center justify-center">
                        @if($m->tipo === 'imagen')<img src="{{ $m->url }}" alt="{{ $m->titulo }}" class="w-full h-full object-cover" loading="lazy">
                        @elseif($m->tipo === 'video')<i class="fas fa-film text-4xl text-gray-400"></i>
                        @else<i class="fas fa-file-lines text-4xl text-gray-400"></i>@endif
                    </a>
                    <div class="p-3 text-sm flex-1">
                        <p class="font-semibold text-gray-800 truncate" title="{{ $m->titulo }}">{{ $m->titulo }}</p>
                        <p class="text-xs text-gray-500">{{ Medio::TIPOS[$m->tipo] ?? $m->tipo }} · {{ $m->tamano_legible }}</p>
                        @if($m->producto)<p class="text-xs text-blue-700 truncate"><i class="fas fa-box mr-1"></i>{{ \App\Support\EtiquetaProducto::nombre($m->producto) }}</p>@endif
                        @if($m->etiquetas)<p class="text-xs text-gray-400 truncate">{{ $m->etiquetas }}</p>@endif
                        <p class="text-xs text-gray-400">{{ ($uso[$m->id] ?? 0) ? 'En ' . $uso[$m->id] . ' publicación(es)' : 'Sin usar' }}</p>
                    </div>
                    <div class="px-3 pb-3 flex items-center justify-between text-xs">
                        <button type="button" @click="editar = !editar" class="text-blue-700 hover:underline">Editar datos</button>
                        <form method="POST" action="{{ route('marketing.medios.destroy', $m) }}" data-confirm="¿Eliminar «{{ $m->titulo }}» de la biblioteca?{{ ($uso[$m->id] ?? 0) ? ' Se quita de las publicaciones que lo usan.' : '' }}">@csrf @method('DELETE')
                            <button class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button></form>
                    </div>
                    <form x-show="editar" x-cloak method="POST" action="{{ route('marketing.medios.update', $m) }}" class="px-3 pb-3 space-y-2 border-t border-gray-100 pt-2">@csrf @method('PUT')
                        <input name="titulo" value="{{ $m->titulo }}" class="{{ $input }}" required>
                        <input name="etiquetas" value="{{ $m->etiquetas }}" class="{{ $input }}" placeholder="Etiquetas">
                        <select name="producto_id" class="{{ $input }}"><option value="">Sin producto</option>@foreach($productos as $p)<option value="{{ $p->id }}" @selected($m->producto_id === $p->id)>{{ \App\Support\EtiquetaProducto::nombre($p) }}</option>@endforeach</select>
                        <button class="w-full bg-blue-900 text-white font-semibold py-1.5 rounded-lg text-xs">Guardar</button>
                    </form>
                </div>
            @empty
                <p class="col-span-full bg-white rounded-xl shadow-sm p-8 text-center text-gray-500">La biblioteca está vacía. Sube el primer archivo.</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $medios->links() }}</div>
    </div>

    <form method="POST" action="{{ route('marketing.medios.store') }}" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm p-5 space-y-3 h-fit">
        @csrf
        <h2 class="font-bold text-gray-800">Subir archivos</h2>
        <div>
            <input type="file" name="archivos[]" multiple required class="text-sm w-full" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.webm,.pdf,.ai,.psd,.zip">
            <p class="text-xs text-gray-500 mt-1">Hasta 20 archivos, 50 MB cada uno.</p>
            @error('archivos')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            @error('archivos.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <input name="titulo" value="{{ old('titulo') }}" class="{{ $input }}" placeholder="Título (si subes un solo archivo)">
        <input name="etiquetas" value="{{ old('etiquetas') }}" class="{{ $input }}" placeholder="Etiquetas: navidad, ring, promo">
        <select name="producto_id" class="{{ $input }}"><option value="">Sin producto asociado</option>@foreach($productos as $p)<option value="{{ $p->id }}">{{ \App\Support\EtiquetaProducto::nombre($p) }}</option>@endforeach</select>
        <button class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 rounded-lg text-sm"><i class="fas fa-upload mr-1"></i>Subir</button>
    </form>
</div>
@endsection
