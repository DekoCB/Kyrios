@extends('layouts.modulo')
@section('titulo', 'Lugares de trabajo')
@section('subtitulo', 'Oficina, tiendas y obras donde se puede marcar asistencia; la ubicación de cada marcación se valida contra ellos')

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'lugares'])
    @php $caja = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring focus:ring-blue-200'; $etq = 'block text-xs font-medium text-gray-600 mb-1'; @endphp

    <div class="grid lg:grid-cols-3 gap-6">
        <section class="lg:col-span-1 bg-white rounded-xl shadow-sm p-5" x-data="{
            lat: '{{ old('latitud') }}', lng: '{{ old('longitud') }}', msg: '',
            ubicar() { this.msg = 'Obteniendo ubicación…'; navigator.geolocation ? navigator.geolocation.getCurrentPosition(p => { this.lat = p.coords.latitude.toFixed(7); this.lng = p.coords.longitude.toFixed(7); this.msg = 'Ubicación cargada (precisión de ' + Math.round(p.coords.accuracy) + ' m).'; }, () => this.msg = 'No se pudo obtener la ubicación.', { enableHighAccuracy: true, timeout: 20000 }) : this.msg = 'Este navegador no puede obtener la ubicación.'; }
        }">
            <h2 class="font-semibold text-gray-900 mb-3"><i class="fas fa-plus text-gray-400 mr-1"></i> Agregar un lugar</h2>
            <form method="POST" action="{{ route('rrhh.lugares.store') }}" class="grid gap-3">
                @csrf
                <div><label class="{{ $etq }}" for="l-nombre">Nombre *</label><input id="l-nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="120" placeholder="Ej. Oficina central" class="{{ $caja }}">@error('nombre')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="{{ $etq }}" for="l-tipo">Tipo</label><select id="l-tipo" name="tipo" class="{{ $caja }}">@foreach(\App\Models\LugarTrabajo::TIPOS as $k => $t)<option value="{{ $k }}" @selected(old('tipo') === $k)>{{ $t }}</option>@endforeach</select></div>
                    <div><label class="{{ $etq }}" for="l-radio">Radio (metros) *</label><input id="l-radio" type="number" name="radio_m" min="20" max="5000" value="{{ old('radio_m', 150) }}" required class="{{ $caja }}">@error('radio_m')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                </div>
                <div><label class="{{ $etq }}" for="l-dir">Dirección</label><input id="l-dir" name="direccion" value="{{ old('direccion') }}" maxlength="255" class="{{ $caja }}"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="{{ $etq }}" for="l-lat">Latitud *</label><input id="l-lat" name="latitud" x-model="lat" required inputmode="decimal" placeholder="-12.0464000" class="{{ $caja }} font-mono">@error('latitud')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                    <div><label class="{{ $etq }}" for="l-lng">Longitud *</label><input id="l-lng" name="longitud" x-model="lng" required inputmode="decimal" placeholder="-77.0428000" class="{{ $caja }} font-mono">@error('longitud')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror</div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="ubicar()" class="text-sm border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg px-3 py-1.5"><i class="fas fa-location-crosshairs mr-1"></i>Usar mi ubicación actual</button>
                    <span class="text-xs text-gray-500" x-text="msg"></span>
                </div>
                <p class="text-[11px] text-gray-400">Tip: en Google Maps, mantenga pulsado el punto exacto y copie los dos números que aparecen (latitud, longitud).</p>
                <button class="bg-gray-900 hover:bg-black text-white text-sm font-semibold px-4 py-2 rounded-lg">Agregar lugar</button>
            </form>
        </section>

        <section class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden">
            <h2 class="font-semibold text-gray-900 px-5 py-3 border-b border-gray-100">Lugares registrados ({{ $lugares->count() }})</h2>
            @if($lugares->isEmpty())
                <p class="p-6 text-sm text-gray-500">Todavía no hay lugares. Mientras no haya ninguno, todas las marcaciones con ubicación quedan pendientes de revisión.</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($lugares as $l)
                        <li class="p-4" x-data="{ editar: false }">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900">{{ $l->nombre }} <span class="text-xs font-normal text-gray-500">· {{ \App\Models\LugarTrabajo::TIPOS[$l->tipo] ?? $l->tipo }}</span>@unless($l->activo)<span class="ml-2 text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">Inactivo</span>@endunless</p>
                                    <p class="text-xs text-gray-500">{{ $l->direccion }}@if($l->direccion) · @endif radio {{ number_format($l->radio_m) }} m · <a class="text-blue-700 hover:underline" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $l->latitud }},{{ $l->longitud }}">{{ $l->latitud }}, {{ $l->longitud }}</a></p>
                                </div>
                                <button type="button" @click="editar = !editar" class="text-sm text-blue-700 hover:underline">Editar</button>
                                <form method="POST" action="{{ route('rrhh.lugares.destroy', $l) }}" data-confirm="¿Eliminar «{{ $l->nombre }}»? Las marcaciones ya hechas no cambian.">@csrf @method('DELETE')<button class="text-sm text-red-600 hover:underline">Eliminar</button></form>
                            </div>
                            <form x-show="editar" x-cloak method="POST" action="{{ route('rrhh.lugares.update', $l) }}" class="grid sm:grid-cols-3 gap-3 mt-3 bg-gray-50 rounded-lg p-3">
                                @csrf @method('PUT')
                                <div class="sm:col-span-2"><label class="{{ $etq }}">Nombre</label><input name="nombre" value="{{ $l->nombre }}" required maxlength="120" class="{{ $caja }}"></div>
                                <div><label class="{{ $etq }}">Tipo</label><select name="tipo" class="{{ $caja }}">@foreach(\App\Models\LugarTrabajo::TIPOS as $k => $t)<option value="{{ $k }}" @selected($l->tipo === $k)>{{ $t }}</option>@endforeach</select></div>
                                <div class="sm:col-span-3"><label class="{{ $etq }}">Dirección</label><input name="direccion" value="{{ $l->direccion }}" maxlength="255" class="{{ $caja }}"></div>
                                <div><label class="{{ $etq }}">Latitud</label><input name="latitud" value="{{ $l->latitud }}" required class="{{ $caja }} font-mono"></div>
                                <div><label class="{{ $etq }}">Longitud</label><input name="longitud" value="{{ $l->longitud }}" required class="{{ $caja }} font-mono"></div>
                                <div><label class="{{ $etq }}">Radio (m)</label><input type="number" name="radio_m" value="{{ $l->radio_m }}" min="20" max="5000" required class="{{ $caja }}"></div>
                                <label class="sm:col-span-2 flex items-center gap-2 text-sm"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" @checked($l->activo) class="rounded border-gray-300"> Activo (se usa para validar marcaciones)</label>
                                <div><button class="bg-gray-900 hover:bg-black text-white text-sm font-semibold px-4 py-2 rounded-lg">Guardar</button></div>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
