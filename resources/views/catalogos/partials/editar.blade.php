{{-- Pestaña «Editar»: datos, diseño, páginas con sus productos y agregar páginas. Recibe $sel, $paginas, $diseno, $puedeEditar, $tiposLuminaria, $marcas, $inp. --}}
@if(!$puedeEditar)
    <p class="text-sm text-gray-500">Solo Administración y Marketing pueden editar los catálogos. Usted puede hojearlo, imprimirlo y compartirlo.</p>
@else
@php
    $brochure = $sel->esBrochure();
    $d = $diseno;
    $tamanos = ['chico' => 'Chica', 'medio' => 'Mediana', 'grande' => 'Grande', 'completo' => 'Completa (rellena el recuadro)'];
    $subs = ['paginas' => 'Páginas y productos', 'diseno' => 'Diseño', 'datos' => 'Datos', 'agregar' => 'Agregar páginas'];
@endphp
<div x-data="{ sub: '{{ $errors->has('portada_imagen') || $errors->has('colores.*') ? 'diseno' : 'paginas' }}' }">
    <div class="flex flex-wrap gap-2 mb-5" role="tablist" aria-label="Qué editar">
        @foreach($subs as $k => $t)
            <button type="button" role="tab" @click="sub = '{{ $k }}'" :aria-selected="sub === '{{ $k }}'"
                    :class="sub === '{{ $k }}' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                    class="px-3.5 py-1.5 rounded-full border text-sm font-semibold">{{ $t }}</button>
        @endforeach
    </div>

    {{-- ── Datos ── --}}
    <div x-show="sub === 'datos'" x-cloak>
        <form method="POST" action="{{ route('catalogos.update', $sel) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @csrf @method('PUT')
            <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-nombre">Nombre</label><input id="e-nombre" name="nombre" value="{{ old('nombre', $sel->nombre) }}" maxlength="120" required class="{{ $inp }}"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-tipo">Tipo de catálogo</label>
                <select id="e-tipo" name="tipo" class="{{ $inp }}">@foreach(\App\Models\CatalogoLibro::TIPOS as $k => $t)<option value="{{ $k }}" @selected(old('tipo', $sel->tipo) === $k)>{{ $t }}</option>@endforeach</select>
                <p class="text-xs text-gray-500 mt-1">Técnico: fichas con características y precios. Brochure: solo imágenes y código.</p></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-temp">Temporada</label><select id="e-temp" name="temporada" class="{{ $inp }}">@foreach(\App\Models\CatalogoLibro::TEMPORADAS as $t)<option @selected(old('temporada', $sel->temporada) === $t)>{{ $t }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-anio">Año</label><input id="e-anio" type="number" name="anio" min="2020" max="2100" value="{{ old('anio', $sel->anio) }}" class="{{ $inp }}"></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-estado">Estado</label><select id="e-estado" name="estado" class="{{ $inp }}">@foreach(\App\Models\CatalogoLibro::ESTADOS as $e)<option value="{{ $e }}" @selected(old('estado', $sel->estado) === $e)>{{ ucfirst($e) }}</option>@endforeach</select></div>
            <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="e-vig">Vigente hasta</label><input id="e-vig" type="date" name="vigencia" value="{{ old('vigencia', $sel->vigencia?->toDateString()) }}" class="{{ $inp }}"><p class="text-xs text-gray-500 mt-1">Se muestra en el índice del libro.</p></div>
            <div class="sm:col-span-2 lg:col-span-4"><button class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold text-sm px-5 py-2 rounded-lg">Guardar cambios</button></div>
        </form>
    </div>

    {{-- ── Diseño: tipografía, colores, portada y características ── --}}
    <div x-show="sub === 'diseno'" x-cloak>
        <form method="POST" action="{{ route('catalogos.diseno', $sel) }}" enctype="multipart/form-data" class="grid gap-6" x-data="{ estilo: '{{ old('portada.estilo', $d['portada']['estilo']) }}' }">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <fieldset class="grid gap-3 content-start">
                    <legend class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Tipografía y colores</legend>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="d-fuente">Tipografía</label>
                        <select id="d-fuente" name="fuente" class="{{ $inp }}">@foreach(\App\Models\CatalogoLibro::FUENTES as $k => $f)<option value="{{ $k }}" @selected(old('fuente', $d['fuente']) === $k)>{{ $f['etiqueta'] }}</option>@endforeach</select>
                        <p class="text-xs text-gray-500 mt-1">En el PDF se usa la fuente equivalente del sistema (Helvetica, Times o Courier).</p></div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach(\App\Models\CatalogoLibro::COLORES as $k => $t)
                            <div x-data="{ v: '{{ old('colores.' . $k, $d['colores'][$k]) }}' }">
                                <label class="block text-xs font-semibold text-gray-600 mb-1" for="c-{{ $k }}">{{ $t }}</label>
                                <div class="flex items-center gap-2"><input id="c-{{ $k }}" type="color" x-model="v" class="h-9 w-12 rounded border border-gray-300 bg-white p-0.5 cursor-pointer"><input type="text" name="colores[{{ $k }}]" x-model="v" maxlength="7" pattern="#[0-9a-fA-F]{6}" class="{{ $inp }} font-mono !px-2" aria-label="{{ $t }} en hexadecimal"></div>
                            </div>
                        @endforeach
                    </div>
                    @error('colores.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset class="grid gap-3 content-start">
                    <legend class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Portada</legend>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="p-estilo">Estilo</label>
                        <select id="p-estilo" name="portada[estilo]" x-model="estilo" class="{{ $inp }}">@foreach(\App\Models\CatalogoLibro::PORTADAS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="p-titulo">Título <span class="font-normal text-gray-400">(vacío = «{{ $brochure ? 'Brochure' : 'Catálogo' }} + temporada»)</span></label><input id="p-titulo" name="portada[titulo]" value="{{ old('portada.titulo', $d['portada']['titulo']) }}" maxlength="80" class="{{ $inp }}"></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="p-sub">Subtítulo</label><input id="p-sub" name="portada[subtitulo]" value="{{ old('portada.subtitulo', $d['portada']['subtitulo']) }}" maxlength="120" class="{{ $inp }}"></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1" for="p-lema">Lema</label><input id="p-lema" name="portada[lema]" value="{{ old('portada.lema', $d['portada']['lema']) }}" maxlength="60" class="{{ $inp }}"></div>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="portada[logo]" value="0"><input type="checkbox" name="portada[logo]" value="1" @checked(old('portada.logo', $d['portada']['logo'])) class="rounded border-gray-300 text-yellow-500"> Mostrar el logo de Kyrios en la portada</label>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1" for="p-foto">Foto de la portada <span class="font-normal text-gray-400">(JPG, PNG o WebP, hasta 4 MB; se usa con el estilo «Con foto de fondo»)</span></label>
                        <input id="p-foto" type="file" name="portada_imagen" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold hover:file:bg-gray-200">
                        @error('portada_imagen')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        @if($d['portada']['imagen'])
                            <div class="flex items-center gap-3 mt-2"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($d['portada']['imagen']) }}" alt="Foto actual de la portada" class="h-16 w-12 object-cover rounded border border-gray-200">
                                <label class="flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="quitar_imagen" value="1" class="rounded border-gray-300 text-red-500"> Quitar esta foto</label></div>
                        @endif
                    </div>
                </fieldset>
            </div>

            @unless($brochure)
                <fieldset>
                    <legend class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-2">Características que aparecen en cada producto</legend>
                    <input type="hidden" name="caracteristicas[]" value="">
                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                        @foreach(\App\Models\CatalogoLibro::CARACTERISTICAS as $k)
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="caracteristicas[]" value="{{ $k }}" @checked(in_array($k, old('caracteristicas', $d['caracteristicas']), true)) class="rounded border-gray-300 text-yellow-500"> {{ $k }}</label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Son las de todo el catálogo; en «Páginas y productos» puede elegir otras para un producto en particular.</p>
                </fieldset>
            @else
                <p class="text-sm text-gray-500"><i class="fas fa-circle-info mr-1"></i>El brochure muestra solo la foto y el código de cada producto, sin características ni precios.</p>
            @endunless

            <div class="flex flex-wrap items-center gap-3">
                <button class="bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold text-sm px-5 py-2 rounded-lg">Guardar diseño</button>
                <span class="text-xs text-gray-500">El libro de arriba se actualiza al guardar.</span>
            </div>
        </form>
        <form method="POST" action="{{ route('catalogos.diseno', $sel) }}" class="mt-3" data-confirm="¿Volver al diseño de partida? Se pierden los colores, la tipografía y la portada personalizados.">
            @csrf @method('PUT')
            <input type="hidden" name="restablecer" value="1">
            <button class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold px-4 py-2 rounded-lg">Restablecer diseño</button>
        </form>
    </div>

    {{-- ── Páginas y productos ── --}}
    <div x-show="sub === 'paginas'">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">Páginas del catálogo ({{ $paginas->count() }})</h3>
        @if($paginas->isEmpty())
            <p class="text-sm text-gray-500">El catálogo no tiene páginas. Agréguelas en «Agregar páginas» o arme todo automáticamente.</p>
        @else
            <ul class="grid gap-1.5 max-h-[640px] overflow-y-auto pr-1">
                @foreach($paginas as $i => $p)
                    <li class="border border-gray-200 rounded-lg text-sm">
                        <div class="grid grid-cols-[28px_minmax(0,1fr)_auto] gap-2 items-center px-2 py-1.5">
                            <span class="font-mono text-xs text-gray-500">{{ $i + 1 }}</span>
                            <span class="min-w-0"><b class="block text-gray-900">{{ $p->tipo === 'seccion' ? 'Sección' : $p->items->count() . ($p->items->count() === 1 ? ' producto' : ' productos') }}</b>
                                <span class="block text-xs text-gray-500 truncate">{{ $p->tipo === 'seccion' ? $p->titulo : $p->items->map(fn ($it) => $it->variante?->producto ? \App\Support\EtiquetaProducto::nombre($it->variante->producto) : '—')->implode(', ') }}</span></span>
                            <span class="flex gap-1">
                                <form method="POST" action="{{ route('catalogos.paginas.mover', [$sel, $p]) }}">@csrf <input type="hidden" name="direccion" value="sube"><button class="px-2 py-1 border border-gray-300 rounded-md text-xs hover:bg-gray-50 disabled:opacity-40" @disabled($i === 0) aria-label="Subir">↑</button></form>
                                <form method="POST" action="{{ route('catalogos.paginas.mover', [$sel, $p]) }}">@csrf <input type="hidden" name="direccion" value="baja"><button class="px-2 py-1 border border-gray-300 rounded-md text-xs hover:bg-gray-50 disabled:opacity-40" @disabled($i === $paginas->count() - 1) aria-label="Bajar">↓</button></form>
                                <form method="POST" action="{{ route('catalogos.paginas.destroy', [$sel, $p]) }}" data-confirm="¿Quitar esta página del catálogo?">@csrf @method('DELETE')<button class="px-2 py-1 border border-red-200 text-red-700 rounded-md text-xs hover:bg-red-50" aria-label="Quitar página">✕</button></form>
                            </span>
                        </div>
                        <details class="border-t border-gray-100">
                            <summary class="cursor-pointer px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-gray-50"><i class="fas fa-sliders mr-1"></i>Fotos, tamaño y características de {{ $p->tipo === 'seccion' ? 'la foto de la sección' : 'los productos de esta página' }}</summary>
                            <div class="grid gap-4 p-3 bg-gray-50">
                                @foreach($p->items as $it)
                                    @continue(!$it->variante)
                                    @php $imgs = $it->variante->imagenes->sortBy(fn ($x) => [$x->es_principal ? 0 : 1, $x->orden])->values(); $maxFotos = $p->tipo === 'seccion' ? 1 : 4; @endphp
                                    <form method="POST" action="{{ route('catalogos.items.update', [$sel, $it]) }}" class="bg-white border border-gray-200 rounded-lg p-3 grid gap-3"
                                          x-data="{ sel: @js(array_values($it->imagenes ?? [])), max: {{ $maxFotos }}, usar: @js($it->caracteristicas === null),
                                                    alternar(id) { const i = this.sel.indexOf(id); if (i >= 0) { this.sel.splice(i, 1); } else if (this.sel.length < this.max) { this.sel.push(id); } } }">
                                        @csrf @method('PUT')
                                        <p class="font-semibold text-gray-900">{{ \App\Support\EtiquetaProducto::nombre($it->variante->producto) }} <span class="font-mono text-xs text-gray-500">{{ $it->variante->codigo_kyrios }}</span></p>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-600 mb-1">Fotos que van en el catálogo <span class="font-normal text-gray-400">(hasta {{ $maxFotos }}; la primera que toque es la principal. Sin elegir, se usa la principal del producto)</span></p>
                                            @if($imgs->isEmpty())
                                                <p class="text-xs text-amber-700">Este producto no tiene fotos cargadas.</p>
                                            @else
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach($imgs as $img)
                                                        <button type="button" @click="alternar({{ $img->id }})" :class="sel.includes({{ $img->id }}) ? 'ring-2 ring-yellow-400 border-yellow-400' : 'border-gray-200'" class="relative w-16 h-16 rounded-lg border bg-white overflow-hidden" aria-label="Foto {{ $loop->iteration }} del producto">
                                                            <img src="{{ $img->url }}" alt="" class="w-full h-full object-contain" loading="lazy">
                                                            <span x-show="sel.includes({{ $img->id }})" x-text="sel.indexOf({{ $img->id }}) + 1" class="absolute top-0.5 left-0.5 bg-yellow-400 text-gray-900 text-[11px] font-bold rounded-full w-5 h-5 grid place-items-center"></span>
                                                            @if($img->es_principal)<span class="absolute bottom-0 inset-x-0 bg-black/60 text-white text-[9px] text-center">principal</span>@endif
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <template x-for="id in sel" :key="id"><input type="hidden" name="imagenes[]" :value="id"></template>
                                        </div>
                                        @if($p->tipo !== 'seccion')
                                            <div class="grid sm:grid-cols-2 gap-3">
                                                <div><label class="block text-xs font-semibold text-gray-600 mb-1">Tamaño de la foto</label>
                                                    <select name="tamano" class="{{ $inp }}">@foreach($tamanos as $k => $t)<option value="{{ $k }}" @selected(($it->tamano ?? 'grande') === $k)>{{ $t }}</option>@endforeach</select></div>
                                            </div>
                                            @unless($brochure)
                                                <div>
                                                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-600 mb-1"><input type="checkbox" name="usar_diseno" value="1" x-model="usar" class="rounded border-gray-300 text-yellow-500"> Usar las características del catálogo</label>
                                                    <div class="flex flex-wrap gap-x-4 gap-y-1" :class="usar ? 'opacity-40 pointer-events-none' : ''">
                                                        @foreach(\App\Models\CatalogoLibro::CARACTERISTICAS as $k)
                                                            <label class="inline-flex items-center gap-1.5 text-xs text-gray-700"><input type="checkbox" name="caracteristicas[]" value="{{ $k }}" :disabled="usar" @checked(in_array($k, $it->caracteristicas ?? $d['caracteristicas'], true)) class="rounded border-gray-300 text-yellow-500"> {{ $k }}</label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endunless
                                        @endif
                                        <div><button class="bg-gray-900 hover:bg-black text-white text-xs font-semibold px-4 py-1.5 rounded-lg">Guardar este producto</button></div>
                                    </form>
                                @endforeach
                            </div>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- ── Agregar páginas ── --}}
    <div x-show="sub === 'agregar'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <form method="POST" action="{{ route('catalogos.paginas.armar', $sel) }}" class="grid gap-2 content-start" data-confirm="Esto reemplaza todas las páginas actuales por unas armadas automáticamente. ¿Continuar?">
            @csrf
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Armar páginas automáticamente</h3>
            <div class="grid grid-cols-2 gap-2">
                <select name="tipo_luminaria_id" class="{{ $inp }}" aria-label="Tipo de luminaria"><option value="">Todos los tipos</option>@foreach($tiposLuminaria as $t)<option value="{{ $t->id }}">{{ ucfirst($t->nombre) }}</option>@endforeach</select>
                <select name="marca_id" class="{{ $inp }}" aria-label="Marca"><option value="">Todas las marcas</option>@foreach($marcas as $m)<option value="{{ $m->id }}">{{ $m->nombre }}</option>@endforeach</select>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="todos" value="1" class="rounded border-gray-300 text-yellow-500"> Con todos los productos del filtro, no solo los que ya están</label>
            <div><button class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold px-4 py-2 rounded-lg">Armar páginas</button></div>
            <p class="text-xs text-gray-500">Se pierden las fotos y tamaños elegidos por producto; los vuelve a elegir en «Páginas y productos».</p>
        </form>

        <form method="POST" action="{{ route('catalogos.paginas.store', $sel) }}" class="grid gap-3 content-start"
              x-data="{
                  tipo: 'uno', titulo: '', q: '', filtroTipo: '', filtroMarca: '', res: [], sel: [], cargando: false,
                  max() { return { uno: 1, dos: 2, tres: 3, cuatro: 4, seccion: 1 }[this.tipo]; },
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
                    <option value="uno">Un producto grande</option><option value="dos">Dos productos</option>
                    @if($brochure)<option value="tres">Tres productos (uno grande y dos chicos)</option>@endif
                    <option value="cuatro">Cuatro productos</option><option value="seccion">Portada de sección</option>
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
</div>
@endif
