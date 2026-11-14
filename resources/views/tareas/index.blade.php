@extends('layouts.modulo')
@section('titulo', 'Tareas del día')
@section('subtitulo', 'Lo que cada persona del equipo tiene por hacer, un tablero por día')

@section('contenido')
@php
    $prioridades = collect(\App\Models\Tarea::PRIORIDADES)->map(fn ($p, $k) => ['clave' => $k, 'nombre' => $p[0], 'clases' => $p[1], 'orden' => $p[2]])->values();
@endphp

<div x-data="tablero({{ \Illuminate\Support\Js::from([
        'personas' => $personas, 'tareas' => $tareas, 'yo' => $yo, 'esAdmin' => $esAdmin, 'fecha' => $fecha->toDateString(), 'prioridades' => $prioridades,
        'urls' => ['tareas' => route('tareas.store'), 'base' => url('/tareas')],
    ]) }})" x-cloak>

    {{-- Barra del día --}}
    <div class="flex flex-wrap items-center gap-2 mb-5">
        <a href="{{ route('tareas.index', ['fecha' => $fecha->copy()->subDay()->toDateString()]) }}" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm hover:bg-gray-50" title="Día anterior"><i class="fas fa-chevron-left"></i></a>
        <form method="GET" class="inline"><input type="date" name="fecha" value="{{ $fecha->toDateString() }}" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm"></form>
        <a href="{{ route('tareas.index', ['fecha' => $fecha->copy()->addDay()->toDateString()]) }}" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm hover:bg-gray-50" title="Día siguiente"><i class="fas fa-chevron-right"></i></a>
        @unless($fecha->isToday())<a href="{{ route('tareas.index') }}" class="px-3 py-2 text-sm text-blue-700 hover:underline">Hoy</a>@endunless
        <span class="text-sm font-medium text-gray-700 hidden sm:inline">{{ $fecha->isoFormat('dddd D [de] MMMM') }}</span>

        <label class="ml-auto inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer"><input type="checkbox" x-model="soloMias" class="rounded border-gray-300"> Solo las mías</label>
        @if($pendientesAnteriores > 0)
            <form method="POST" action="{{ route('tareas.arrastrar') }}" data-confirm="¿Pasar a este día las {{ $pendientesAnteriores }} tareas pendientes de días anteriores?">
                @csrf <input type="hidden" name="hasta" value="{{ $fecha->toDateString() }}">
                <button class="px-4 py-2 text-sm border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 rounded-lg"><i class="fas fa-arrow-right-arrow-left mr-1"></i>Traer {{ $pendientesAnteriores }} pendiente(s) anteriores</button>
            </form>
        @endif
    </div>

    {{-- Resumen del día --}}
    <div class="grid grid-cols-3 gap-3 mb-5 max-w-xl">
        <div class="bg-white rounded-xl shadow-sm p-3 text-center"><p class="text-2xl font-bold text-gray-900" x-text="tareas.length"></p><p class="text-xs text-gray-500">Tareas</p></div>
        <div class="bg-white rounded-xl shadow-sm p-3 text-center"><p class="text-2xl font-bold text-emerald-600" x-text="tareas.filter(t => t.estado === 'hecha').length"></p><p class="text-xs text-gray-500">Hechas</p></div>
        <div class="bg-white rounded-xl shadow-sm p-3 text-center"><p class="text-2xl font-bold text-amber-600" x-text="tareas.filter(t => t.estado !== 'hecha').length"></p><p class="text-xs text-gray-500">Por hacer</p></div>
    </div>

    {{-- Tablero --}}
    <div class="flex gap-4 overflow-x-auto pb-4 items-start">
        <template x-for="p in personasVisibles" :key="p.id">
            <section class="w-72 shrink-0 bg-gray-100 border border-gray-200 rounded-2xl p-3" :data-persona="p.id">
                <header class="mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-full bg-blue-700 text-white text-xs font-bold flex items-center justify-center" x-text="p.iniciales"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 truncate" x-text="p.id === yo ? p.nombre + ' (tú)' : p.nombre"></p>
                            <p class="text-[11px] text-gray-500" x-text="p.rol"></p>
                        </div>
                        <span class="text-xs font-semibold text-gray-600" x-text="hechas(p.id) + '/' + deColumna(p.id).length"></span>
                    </div>
                    <div class="h-1.5 bg-gray-200 rounded-full mt-2 overflow-hidden"><div class="h-full bg-emerald-500 transition-all" :style="'width:' + avance(p.id) + '%'"></div></div>
                </header>

                <div class="space-y-2">
                    <template x-for="t in deColumna(p.id)" :key="t.id">
                        <article class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 hover:shadow transition" :class="t.estado === 'hecha' ? 'opacity-70' : ''" :data-tarea="t.id">
                            <div class="flex items-start gap-2">
                                <button type="button" @click="alternar(t)" :disabled="!puede(t)" class="mt-0.5 shrink-0 text-lg leading-none disabled:cursor-not-allowed"
                                        :class="t.estado === 'hecha' ? 'text-emerald-500' : 'text-gray-300 hover:text-emerald-400'" :title="t.estado === 'hecha' ? 'Marcar como pendiente' : 'Marcar como hecha'">
                                    <i :class="t.estado === 'hecha' ? 'fas fa-circle-check' : 'far fa-circle'"></i>
                                </button>
                                <button type="button" @click="abrir(t)" class="text-left flex-1 min-w-0">
                                    <span class="block text-sm font-medium text-gray-900" :class="t.estado === 'hecha' ? 'line-through text-gray-500' : ''" x-text="t.titulo"></span>
                                </button>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 mt-2 ml-7">
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full" :class="clasesPrioridad(t.prioridad)" x-text="nombrePrioridad(t.prioridad)"></span>
                                <span x-show="t.items.length" class="text-[11px] text-gray-500 inline-flex items-center gap-1"><i class="far fa-square-check"></i><span x-text="t.items.filter(i => i.hecho).length + '/' + t.items.length"></span></span>
                                <span x-show="t.descripcion" class="text-gray-400 text-xs" title="Tiene descripción"><i class="fas fa-align-left"></i></span>
                            </div>
                        </article>
                    </template>

                    <p x-show="deColumna(p.id).length === 0" class="text-xs text-gray-400 text-center py-3">Sin tareas para este día</p>
                </div>

                <template x-if="puedeAgregar(p.id)">
                    <form @submit.prevent="agregar(p.id)" class="mt-3">
                        <input type="text" x-model="nuevas[p.id]" maxlength="160" placeholder="+ Añadir una tarea…" class="w-full px-3 py-2 text-sm bg-white/70 border border-dashed border-gray-300 rounded-lg focus:bg-white focus:border-blue-400 focus:ring focus:ring-blue-100">
                    </form>
                </template>
            </section>
        </template>
    </div>

    {{-- Detalle de la tarea --}}
    <div x-show="abierta" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="cerrar()">
        <div class="absolute inset-0 bg-black/40" @click="cerrar()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6" x-show="abierta" x-transition>
            <template x-if="abierta">
                <div>
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <input type="text" x-model="abierta.titulo" @change="guardar('titulo')" :disabled="!puede(abierta)" maxlength="160" class="flex-1 text-lg font-bold text-gray-900 border-0 border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:ring-0 px-0">
                        <button @click="cerrar()" class="text-gray-400 hover:text-gray-700"><i class="fas fa-xmark text-lg"></i></button>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-4 text-sm">
                        <label class="block"><span class="text-xs text-gray-500">Prioridad</span>
                            <select x-model="abierta.prioridad" @change="guardar('prioridad')" :disabled="!puede(abierta)" class="w-full mt-1 px-2 py-2 border border-gray-300 rounded-lg">
                                <template x-for="pr in prioridades" :key="pr.clave"><option :value="pr.clave" x-text="pr.nombre"></option></template>
                            </select></label>
                        <label class="block"><span class="text-xs text-gray-500">Estado</span>
                            <select x-model="abierta.estado" @change="guardar('estado')" :disabled="!puede(abierta)" class="w-full mt-1 px-2 py-2 border border-gray-300 rounded-lg">
                                <option value="pendiente">Pendiente</option><option value="en_progreso">En progreso</option><option value="hecha">Hecha</option>
                            </select></label>
                        <label class="block"><span class="text-xs text-gray-500">Día</span>
                            <input type="date" x-model="abierta.fecha" @change="guardar('fecha')" :disabled="!puede(abierta)" class="w-full mt-1 px-2 py-2 border border-gray-300 rounded-lg"></label>
                        <label class="block"><span class="text-xs text-gray-500">Responsable</span>
                            <select x-model.number="abierta.asignado_a" @change="guardar('asignado_a')" :disabled="!esAdmin" class="w-full mt-1 px-2 py-2 border border-gray-300 rounded-lg">
                                <template x-for="p in personas" :key="p.id"><option :value="p.id" x-text="p.nombre"></option></template>
                            </select></label>
                    </div>

                    <label class="block mb-4"><span class="text-xs text-gray-500">Descripción</span>
                        <textarea x-model="abierta.descripcion" @change="guardar('descripcion')" :disabled="!puede(abierta)" rows="3" maxlength="2000" placeholder="Detalles, enlaces, a quién llamar…" class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea></label>

                    <div class="mb-4">
                        <p class="text-xs text-gray-500 mb-2">Lista de pasos <span x-show="abierta.items.length" x-text="'(' + abierta.items.filter(i => i.hecho).length + '/' + abierta.items.length + ')'"></span></p>
                        <ul class="space-y-1">
                            <template x-for="i in abierta.items" :key="i.id">
                                <li class="flex items-center gap-2 text-sm group">
                                    <input type="checkbox" :checked="i.hecho" @change="marcarPaso(i, $event.target.checked)" :disabled="!puede(abierta)" class="rounded border-gray-300">
                                    <span class="flex-1" :class="i.hecho ? 'line-through text-gray-400' : 'text-gray-800'" x-text="i.texto"></span>
                                    <button x-show="puede(abierta)" @click="quitarPaso(i)" class="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100"><i class="fas fa-trash-can text-xs"></i></button>
                                </li>
                            </template>
                        </ul>
                        <form x-show="puede(abierta)" @submit.prevent="agregarPaso()" class="mt-2 flex gap-2">
                            <input type="text" x-model="paso" maxlength="200" placeholder="Agregar un paso…" class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                            <button class="px-3 py-1.5 text-sm bg-gray-800 text-white rounded-lg">Añadir</button>
                        </form>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400" x-text="error"></p>
                        <button x-show="puede(abierta)" @click="eliminar()" class="text-sm text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg"><i class="fas fa-trash mr-1"></i>Eliminar tarea</button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function tablero(datos) {
    return {
        personas: datos.personas, tareas: datos.tareas, yo: datos.yo, esAdmin: datos.esAdmin, fecha: datos.fecha, prioridades: datos.prioridades,
        urls: datos.urls, soloMias: false, nuevas: {}, abierta: null, paso: '', error: '',

        get personasVisibles() { return this.soloMias ? this.personas.filter(p => p.id === this.yo) : this.personas; },
        deColumna(id) {
            const orden = Object.fromEntries(this.prioridades.map(p => [p.clave, p.orden]));
            return this.tareas.filter(t => t.asignado_a === id)
                .sort((a, b) => (a.estado === 'hecha') - (b.estado === 'hecha') || orden[a.prioridad] - orden[b.prioridad] || a.id - b.id);
        },
        hechas(id) { return this.deColumna(id).filter(t => t.estado === 'hecha').length; },
        avance(id) { const n = this.deColumna(id).length; return n ? Math.round(this.hechas(id) * 100 / n) : 0; },
        puede(t) { return this.esAdmin || t.asignado_a === this.yo; },
        puedeAgregar(id) { return this.esAdmin || id === this.yo; },
        clasesPrioridad(c) { return (this.prioridades.find(p => p.clave === c) || {}).clases || ''; },
        nombrePrioridad(c) { return (this.prioridades.find(p => p.clave === c) || {}).nombre || c; },

        async api(metodo, url, cuerpo) {
            this.error = '';
            const r = await fetch(url, {
                method: metodo,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: cuerpo ? JSON.stringify(cuerpo) : undefined,
            });
            const json = await r.json().catch(() => ({}));
            if (!r.ok) { this.error = json.message || 'No se pudo guardar.'; throw new Error(this.error); }
            return json;
        },
        reemplazar(t) {
            const i = this.tareas.findIndex(x => x.id === t.id);
            i >= 0 ? this.tareas.splice(i, 1, t) : this.tareas.push(t);
            if (this.abierta && this.abierta.id === t.id) this.abierta = JSON.parse(JSON.stringify(t));
            // si la tarea cambió de día, deja de verse en este tablero
            if (t.fecha !== this.fecha) this.tareas = this.tareas.filter(x => x.id !== t.id);
        },

        async agregar(personaId) {
            const titulo = (this.nuevas[personaId] || '').trim();
            if (!titulo) return;
            try {
                const r = await this.api('POST', this.urls.tareas, { titulo, asignado_a: personaId, fecha: this.fecha });
                this.reemplazar(r.tarea);
                this.nuevas[personaId] = '';
            } catch (e) { alert(this.error); }
        },
        async alternar(t) {
            if (!this.puede(t)) return;
            try { this.reemplazar((await this.api('PATCH', this.urls.base + '/' + t.id, { estado: t.estado === 'hecha' ? 'pendiente' : 'hecha' })).tarea); } catch (e) { alert(this.error); }
        },
        abrir(t) { this.abierta = JSON.parse(JSON.stringify(t)); this.paso = ''; this.error = ''; },
        cerrar() { this.abierta = null; },
        async guardar(campo) {
            try { this.reemplazar((await this.api('PATCH', this.urls.base + '/' + this.abierta.id, { [campo]: this.abierta[campo] })).tarea); } catch (e) {}
        },
        async eliminar() {
            if (!confirm('¿Eliminar esta tarea?')) return;
            try { await this.api('DELETE', this.urls.base + '/' + this.abierta.id); this.tareas = this.tareas.filter(x => x.id !== this.abierta.id); this.abierta = null; } catch (e) {}
        },
        async agregarPaso() {
            const texto = this.paso.trim(); if (!texto) return;
            try { this.reemplazar((await this.api('POST', this.urls.base + '/' + this.abierta.id + '/items', { texto })).tarea); this.paso = ''; } catch (e) {}
        },
        async marcarPaso(i, hecho) {
            try { this.reemplazar((await this.api('PATCH', this.urls.base + '/items/' + i.id, { hecho })).tarea); } catch (e) {}
        },
        async quitarPaso(i) {
            try { this.reemplazar((await this.api('DELETE', this.urls.base + '/items/' + i.id)).tarea); } catch (e) {}
        },
    };
}
</script>
@endsection
