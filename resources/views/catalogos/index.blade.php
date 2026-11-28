<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Catálogos de temporada - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="{{ asset('css/catalogo-libro.css') }}">
    @include('partials.tema-head')
    <style>[x-cloak] { display: none !important; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <x-header title="Catálogos de temporada" subtitle="Libros de productos por temporada para hojear, imprimir y compartir" />

        @if(session('success') && session('success') !== '')
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><i class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first() }}</div>
        @endif

        @php
            $inp = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm text-sm focus:border-yellow-500 focus:ring-yellow-500';
            $pill = ['publicado' => 'bg-green-100 text-green-800', 'borrador' => 'bg-amber-100 text-amber-800', 'archivado' => 'bg-gray-200 text-gray-600'];
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-[300px_minmax(0,1fr)] gap-6 items-start">
            {{-- Lista de catálogos --}}
            <aside class="bg-white rounded-xl shadow-sm p-5" x-data="{ nuevo: {{ $errors->has('nombre') && !$sel ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Mis catálogos</h2>
                    @if($puedeEditar)<button type="button" @click="nuevo = !nuevo" class="text-sm font-semibold bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-3 py-1.5 rounded-lg"><i class="fas fa-plus mr-1"></i>Nuevo</button>@endif
                </div>

                @if($puedeEditar)
                <form x-show="nuevo" x-cloak method="POST" action="{{ route('catalogos.store') }}" class="grid gap-3 mb-4 pb-4 border-b border-gray-200">
                    @csrf
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="n-nombre">Nombre</label><input id="n-nombre" name="nombre" value="{{ old('nombre') }}" maxlength="120" required placeholder="Ej. Verano 2028" class="{{ $inp }}"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="n-tipo">Tipo de catálogo</label>
                        <select id="n-tipo" name="tipo" class="{{ $inp }}">@foreach(\App\Models\CatalogoLibro::TIPOS as $k => $t)<option value="{{ $k }}" @selected(old('tipo') === $k)>{{ $t }}</option>@endforeach</select>
                        <p class="text-xs text-gray-500 mt-1">Técnico: fichas con características y precios. Brochure: solo imágenes y código, más vistoso.</p></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="n-temp">Temporada</label><select id="n-temp" name="temporada" class="{{ $inp }}">@foreach(\App\Models\CatalogoLibro::TEMPORADAS as $t)<option>{{ $t }}</option>@endforeach</select></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="n-anio">Año</label><input id="n-anio" type="number" name="anio" min="2020" max="2100" value="{{ old('anio', now()->year + 1) }}" class="{{ $inp }}"></div>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="armar" value="1" checked class="rounded border-gray-300 text-yellow-500"> Armar páginas con los productos</label>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="tipo_luminaria_id" class="{{ $inp }}" aria-label="Tipo de luminaria"><option value="">Todos los tipos</option>@foreach($tiposLuminaria as $t)<option value="{{ $t->id }}">{{ ucfirst($t->nombre) }}</option>@endforeach</select>
                        <select name="marca_id" class="{{ $inp }}" aria-label="Marca"><option value="">Todas las marcas</option>@foreach($marcas as $m)<option value="{{ $m->id }}">{{ $m->nombre }}</option>@endforeach</select>
                    </div>
                    <button class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold text-sm px-4 py-2 rounded-lg">Crear catálogo</button>
                    <p class="text-xs text-gray-500">Entran los productos activos con foto, hasta {{ \App\Services\LibroCatalogoService::MAX_AUTO }}. Después puede reordenar y agregar páginas.</p>
                </form>
                @endif

                @php $ultimo = ''; @endphp
                @forelse($catalogos as $c)
                    @php $grupo = $c->etiquetaTemporada(); @endphp
                    @if($grupo !== $ultimo)<p class="font-mono text-[11px] uppercase tracking-wider text-gray-500 mt-4 mb-1.5">{{ $grupo }}</p>@php $ultimo = $grupo; @endphp @endif
                    <a href="{{ route('catalogos.index', ['c' => $c->id]) }}" @if($sel && $sel->id === $c->id) aria-current="true" @endif
                       class="flex items-center gap-3 border rounded-lg p-2 mb-1.5 {{ $sel && $sel->id === $c->id ? 'border-yellow-400 bg-yellow-50' : 'border-gray-200 hover:border-gray-400' }}">
                        <span class="w-9 h-12 rounded-sm bg-[#3f3d37] relative shrink-0 overflow-hidden"><span class="absolute left-2 right-1.5 bottom-2 h-0.5 bg-yellow-400"></span></span>
                        <span class="min-w-0"><b class="block text-sm leading-tight text-gray-900 truncate">{{ $c->nombre }}</b><span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[11px] font-bold {{ $pill[$c->estado] }}">{{ $c->estado }}</span> <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $c->esBrochure() ? 'bg-fuchsia-100 text-fuchsia-800' : 'bg-sky-100 text-sky-800' }}">{{ $c->esBrochure() ? 'Brochure' : 'Técnico' }}</span></span>
                    </a>
                @empty
                    <p class="text-sm text-gray-500">Todavía no hay catálogos.@if($puedeEditar) Cree el primero con «Nuevo».@endif</p>
                @endforelse

                @if($sel && $puedeEditar)
                    <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-200">
                        <form method="POST" action="{{ route('catalogos.duplicar', $sel) }}">@csrf<button class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold px-3 py-1.5 rounded-lg">Duplicar</button></form>
                        <form method="POST" action="{{ route('catalogos.estado', $sel) }}">@csrf<button class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold px-3 py-1.5 rounded-lg">{{ $sel->estado === 'publicado' ? 'Archivar' : 'Publicar' }}</button></form>
                        <form method="POST" action="{{ route('catalogos.destroy', $sel) }}" data-confirm="¿Eliminar el catálogo «{{ $sel->nombre }}»? No se puede deshacer.">@csrf @method('DELETE')<button class="border border-red-200 text-red-700 hover:bg-red-50 text-xs font-semibold px-3 py-1.5 rounded-lg">Eliminar</button></form>
                    </div>
                @endif
            </aside>

            {{-- Visor y acciones --}}
            <main class="min-w-0 grid gap-6">
                @if($sel)
                    <section class="bg-white rounded-xl shadow-sm p-5" id="visor">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3 min-w-0"><h2 class="text-lg font-bold text-gray-900 truncate">{{ $sel->nombre }}</h2><span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $pill[$sel->estado] }}">{{ $sel->estado }}</span></div>
                            @unless($sel->esBrochure())
                            <div class="flex items-center gap-2"><span class="text-xs text-gray-500">Precios en pantalla</span>
                                <div class="cl-seg" role="group" aria-label="Precios en pantalla">
                                    <button type="button" data-precios-set="none" aria-pressed="false">Sin precio</button>
                                    <button type="button" data-precios-set="pen" aria-pressed="false">Soles (S/)</button>
                                    <button type="button" data-precios-set="usd" aria-pressed="true">Dólares (US$)</button>
                                </div>
                            </div>
                            @endunless
                        </div>
                        @include('catalogos.partials.libro', ['catalogo' => $sel, 'libro' => $libro, 'modo' => 'usd', 'qr' => $qr])
                        @unless($sel->esBrochure())<p class="text-xs text-gray-500 mt-3">Los precios son de venta, sin IGV. Los dólares se calculan con el tipo de cambio del día.</p>@endunless
                    </section>

                    <section class="bg-white rounded-xl shadow-sm p-5" id="acciones" x-data="{ tab: '{{ $tab }}' }">
                        <div class="flex gap-1 border-b border-gray-200 mb-5" role="tablist">
                            @foreach(['editar' => 'Editar', 'imprimir' => 'Imprimir', 'compartir' => 'Compartir'] as $k => $t)
                                <button type="button" role="tab" @click="tab = '{{ $k }}'" :aria-selected="tab === '{{ $k }}'" :class="tab === '{{ $k }}' ? 'border-yellow-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-800'" class="px-4 py-2.5 font-semibold text-sm border-b-[3px] -mb-px">{{ $t }}</button>
                            @endforeach
                        </div>
                        <div x-show="tab === 'editar'">@include('catalogos.partials.editar')</div>
                        <div x-show="tab === 'imprimir'" x-cloak>@include('catalogos.partials.imprimir')</div>
                        <div x-show="tab === 'compartir'" x-cloak>@include('catalogos.partials.compartir')</div>
                    </section>
                @else
                    <section class="bg-white rounded-xl shadow-sm p-10 text-center text-gray-500">
                        <i class="fas fa-book-open text-4xl text-gray-300 mb-3"></i>
                        <p class="font-semibold text-gray-700">Todavía no hay ningún catálogo</p>
                        <p class="text-sm mt-1">{{ $puedeEditar ? 'Cree el primero con el botón «Nuevo» para verlo como un libro.' : 'Administración o Marketing pueden crear el primero.' }}</p>
                    </section>
                @endif
            </main>
        </div>
    </div>
    <script src="{{ asset('js/catalogo-libro.js') }}" defer></script>
</body>
</html>
