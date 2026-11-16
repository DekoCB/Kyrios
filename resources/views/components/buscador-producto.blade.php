@props(['name' => 'buscar', 'value' => '', 'placeholder' => 'Buscar por código o nombre...', 'enviar' => true])
{{-- Buscador de productos con lista desplegable: se puede escribir (sugiere mientras tanto) o abrir la lista completa.
     Sigue siendo un campo de texto normal del formulario, así que Enter y «Buscar» funcionan como siempre. --}}
<div class="relative" x-data="buscadorProducto(@js(route('inventario.productos.sugerencias')), @js($enviar))" @click.outside="abierto = false" @keydown.escape="abierto = false">
    <input type="text" name="{{ $name }}" x-ref="campo" x-model="texto" value="{{ $value }}" autocomplete="off"
           @input.debounce.200ms="cargar()" @keydown.arrow-down.prevent="abrir()"
           placeholder="{{ $placeholder }}"
           {{ $attributes->merge(['class' => 'w-full pl-4 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500']) }}>
    <button type="button" data-lista-productos @click="abierto ? abierto = false : abrir()" class="absolute right-0 top-0 h-full px-3 text-gray-500 hover:text-blue-600" title="Ver lista de productos" tabindex="-1">
        <i class="fas fa-chevron-down text-xs"></i>
    </button>

    <div x-show="abierto" x-cloak class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-64 overflow-y-auto text-left">
        <template x-for="p in resultados" :key="p.id">
            <button type="button" @click="elegir(p)" class="w-full text-left px-4 py-2.5 hover:bg-blue-50 border-b border-gray-50 last:border-0 transition-colors">
                <p class="text-sm font-medium text-gray-900 truncate" x-text="p.nombre"></p>
                <p class="text-xs text-gray-400 font-mono truncate" x-text="p.codigos || p.codigo"></p>
            </button>
        </template>
        <p x-show="cargando" class="px-4 py-3 text-center text-xs text-gray-400">Buscando…</p>
        <p x-show="!cargando && resultados.length === 0" class="px-4 py-3 text-center text-xs text-gray-400">Sin resultados</p>
    </div>
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('buscadorProducto', (url, enviar) => ({
                texto: '', abierto: false, cargando: false, resultados: [], pedido: 0,
                init() { this.texto = this.$refs.campo.getAttribute('value') || ''; },
                abrir() { this.abierto = true; this.cargar(); },
                async cargar() {
                    const n = ++this.pedido;
                    this.abierto = true; this.cargando = true;
                    try {
                        const r = await fetch(url + '?q=' + encodeURIComponent(this.texto.trim()), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const datos = r.ok ? await r.json() : [];
                        if (n === this.pedido) this.resultados = datos;
                    } catch (e) {
                        if (n === this.pedido) this.resultados = [];
                    } finally {
                        if (n === this.pedido) this.cargando = false;
                    }
                },
                elegir(p) {
                    this.texto = p.codigo || p.nombre;
                    this.abierto = false;
                    if (enviar) this.$refs.campo.form?.requestSubmit();
                },
            }));
        });
    </script>
@endonce
