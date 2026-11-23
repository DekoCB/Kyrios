{{-- Pestaña «Editar»: datos del catálogo, páginas y agregar páginas. Recibe $sel, $paginas, $puedeEditar, $tiposLuminaria, $marcas, $inp. --}}
@if(!$puedeEditar)
    <p class="text-sm text-gray-500">Solo Administración y Marketing pueden editar los catálogos. Usted puede hojearlo, imprimirlo y compartirlo.</p>
@else
    <form method="POST" action="{{ route('catalogos.update', $sel) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @csrf @method('PUT')
        <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-nombre">Nombre</label><input id="e-nombre" name="nombre" value="{{ old('nombre', $sel->nombre) }}" maxlength="120" required class="{{ $inp }}"></div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-temp">Temporada</label><select id="e-temp" name="temporada" class="{{ $inp }}">@foreach(\App\Models\CatalogoLibro::TEMPORADAS as $t)<option @selected(old('temporada', $sel->temporada) === $t)>{{ $t }}</option>@endforeach</select></div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-anio">Año</label><input id="e-anio" type="number" name="anio" min="2020" max="2100" value="{{ old('anio', $sel->anio) }}" class="{{ $inp }}"></div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-estado">Estado</label><select id="e-estado" name="estado" class="{{ $inp }}">@foreach(\App\Models\CatalogoLibro::ESTADOS as $e)<option value="{{ $e }}" @selected(old('estado', $sel->estado) === $e)>{{ ucfirst($e) }}</option>@endforeach</select></div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-vig">Vigente hasta</label><input id="e-vig" type="date" name="vigencia" value="{{ old('vigencia', $sel->vigencia?->toDateString()) }}" class="{{ $inp }}"><p class="text-xs text-gray-500 mt-1">Se muestra en el índice del libro.</p></div>
        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Portada</label><input value="Logo de Kyrios" disabled class="{{ $inp }} bg-gray-50"><p class="text-xs text-gray-500 mt-1">Todas las portadas llevan el logo.</p></div>
        <div class="sm:col-span-2 lg:col-span-4"><button class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold text-sm px-5 py-2 rounded-lg">Guardar cambios</button></div>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">Páginas del catálogo ({{ $paginas->count() }})</h3>
            @if($paginas->isEmpty())
                <p class="text-sm text-gray-500">El catálogo no tiene páginas. Agregue una a la derecha o arme todo automáticamente.</p>
            @else
                <ul class="grid gap-1.5 max-h-[360px] overflow-y-auto pr-1">
                    @foreach($paginas as $i => $p)
                        <li class="grid grid-cols-[28px_minmax(0,1fr)_auto] gap-2 items-center border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                            <span class="font-mono text-xs text-gray-500">{{ $i + 1 }}</span>
                            <span class="min-w-0"><b class="block text-gray-900">{{ $p->tipo === 'seccion' ? 'Sección' : $p->items->count() . ($p->items->count() === 1 ? ' producto' : ' productos') }}</b>
                                <span class="block text-xs text-gray-500 truncate">{{ $p->tipo === 'seccion' ? $p->titulo : $p->items->map(fn ($it) => $it->variante?->producto ? \App\Support\EtiquetaProducto::nombre($it->variante->producto) : '—')->implode(', ') }}</span></span>
                            <span class="flex gap-1">
                                <form method="POST" action="{{ route('catalogos.paginas.mover', [$sel, $p]) }}">@csrf <input type="hidden" name="direccion" value="sube"><button class="px-2 py-1 border border-gray-300 rounded-md text-xs hover:bg-gray-50 disabled:opacity-40" @disabled($i === 0) aria-label="Subir">↑</button></form>
                                <form method="POST" action="{{ route('catalogos.paginas.mover', [$sel, $p]) }}">@csrf <input type="hidden" name="direccion" value="baja"><button class="px-2 py-1 border border-gray-300 rounded-md text-xs hover:bg-gray-50 disabled:opacity-40" @disabled($i === $paginas->count() - 1) aria-label="Bajar">↓</button></form>
                                <form method="POST" action="{{ route('catalogos.paginas.destroy', [$sel, $p]) }}" data-confirm="¿Quitar esta página del catálogo?">@csrf @method('DELETE')<button class="px-2 py-1 border border-red-200 text-red-700 rounded-md text-xs hover:bg-red-50" aria-label="Quitar página">✕</button></form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('catalogos.paginas.armar', $sel) }}" class="mt-4 grid gap-2" data-confirm="Esto reemplaza todas las páginas actuales por unas armadas automáticamente. ¿Continuar?">
                @csrf
                <p class="text-xs font-semibold text-gray-600">Armar páginas automáticamente</p>
                <div class="grid grid-cols-2 gap-2">
                    <select name="tipo_luminaria_id" class="{{ $inp }}" aria-label="Tipo de luminaria"><option value="">Todos los tipos</option>@foreach($tiposLuminaria as $t)<option value="{{ $t->id }}">{{ ucfirst($t->nombre) }}</option>@endforeach</select>
                    <select name="marca_id" class="{{ $inp }}" aria-label="Marca"><option value="">Todas las marcas</option>@foreach($marcas as $m)<option value="{{ $m->id }}">{{ $m->nombre }}</option>@endforeach</select>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="todos" value="1" class="rounded border-gray-300 text-yellow-500"> Con todos los productos del filtro, no solo los que ya están</label>
                <div><button class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold px-4 py-2 rounded-lg">Armar páginas</button></div>
            </form>
        </div>

        {{-- Agregar una página --}}
        <form method="POST" action="{{ route('catalogos.paginas.store', $sel) }}" class="grid gap-3 content-start"
              x-data="{
                  tipo: 'uno', titulo: '', q: '', filtroTipo: '', filtroMarca: '', res: [], sel: [], cargando: false,
                  max() { return { uno: 1, dos: 2, cuatro: 4, seccion: 1 }[this.tipo]; },
                  async buscar() {
                      this.cargando = true;
                      const p = new URLSearchParams({ q: this.q, tipo: this.filtroTipo, marca: this.filtroMarca });
                      try { this.res = await (await fetch('{{ route('catalogos.productos') }}?' + p, { headers: { 'Accept': 'application/json' } })).json(); } catch (e) { this.res = []; }
                      this.cargando = false;
                  },
                  esta(id) { return this.sel.some(x => x.id === id); },
                  alternar(p) { this.esta(p.id) ? this.sel = this.sel.filter(x => x.id !== p.id) : (this.sel.length < this.max() ? this.sel.push(p) : null); }
              }" x-init="buscar()">
            @csrf
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Agregar una página</h3>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="a-tipo">Diseño</label>
                <select id="a-tipo" name="tipo" x-model="tipo" @change="sel = sel.slice(0, max())" class="{{ $inp }}">
                    <option value="uno">Un producto grande</option><option value="dos">Dos productos</option><option value="cuatro">Cuatro productos</option><option value="seccion">Portada de sección</option>
                </select></div>
            <div x-show="tipo === 'seccion'" x-cloak><label class="block text-xs font-semibold text-gray-600 mb-1" for="a-titulo">Título de la sección</label><input id="a-titulo" name="titulo" x-model="titulo" maxlength="60" placeholder="Ej. Exterior" class="{{ $inp }}"></div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" x-text="tipo === 'seccion' ? 'Foto de fondo: elija un producto' : 'Productos (hasta ' + max() + ')'"></label>
                <div class="grid grid-cols-3 gap-2 mb-2">
                    <input x-model="q" @input.debounce.350ms="buscar()" placeholder="Buscar…" class="{{ $inp }} col-span-3 sm:col-span-1" aria-label="Buscar producto">
                    <select x-model="filtroTipo" @change="buscar()" class="{{ $inp }}" aria-label="Tipo de luminaria"><option value="">Todos los tipos</option>@foreach($tiposLuminaria as $t)<option value="{{ $t->id }}">{{ ucfirst($t->nombre) }}</option>@endforeach</select>
                    <select x-model="filtroMarca" @change="buscar()" class="{{ $inp }}" aria-label="Marca"><option value="">Todas las marcas</option>@foreach($marcas as $m)<option value="{{ $m->id }}">{{ $m->nombre }}</option>@endforeach</select>
                </div>
                <template x-for="p in sel" :key="p.id"><input type="hidden" name="variantes[]" :value="p.id"></template>
                <div class="border border-gray-200 rounded-lg max-h-56 overflow-y-auto divide-y divide-gray-100">
                    <p x-show="cargando" class="p-3 text-sm text-gray-500">Buscando…</p>
                    <p x-show="!cargando && res.length === 0" class="p-3 text-sm text-gray-500">No hay productos con foto que coincidan.</p>
                    <template x-for="p in res" :key="p.id">
                        <label class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50" :class="esta(p.id) ? 'bg-yellow-50' : ''">
                            <input type="checkbox" :checked="esta(p.id)" @change="alternar(p); $event.target.checked = esta(p.id)" class="rounded border-gray-300 text-yellow-500">
                            <img :src="p.img" alt="" class="w-9 h-9 object-contain bg-white border border-gray-200 rounded" loading="lazy">
                            <span class="min-w-0 text-sm"><b class="block truncate text-gray-900" x-text="p.nombre"></b><span class="block text-xs text-gray-500 truncate" x-text="p.cod + (p.sub ? ' · ' + p.sub : '')"></span></span>
                        </label>
                    </template>
                </div>
                <p class="text-xs text-gray-500 mt-1"><span x-text="sel.length"></span> de <span x-text="max()"></span> elegidos.</p>
            </div>
            <div><button class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold text-sm px-5 py-2 rounded-lg" :disabled="sel.length === 0">Agregar al final</button></div>
        </form>
    </div>
@endif
