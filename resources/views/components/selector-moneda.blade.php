{{-- Alterna entre dólares (principal) y soles todos los precios marcados con precio_dual(). Recuerda la elección. --}}
@props(['compacto' => false, 'oscuro' => false])
@php $tc = \App\Models\TipoCambio::actual(); @endphp
<div class="inline-flex items-center gap-2 text-xs whitespace-nowrap {{ $oscuro ? 'text-white/80' : 'text-gray-500' }}" data-selector-moneda>
    @unless($compacto)<span class="hidden sm:inline">Ver precios en</span>@endunless
    <div class="inline-flex rounded-lg border border-gray-300 bg-white overflow-hidden" role="group" aria-label="Moneda">
        <button type="button" data-moneda="USD" class="px-3 py-1.5 text-sm font-medium">US$ Dólares</button>
        <button type="button" data-moneda="PEN" class="px-3 py-1.5 text-sm font-medium border-l border-gray-300">S/ Soles</button>
    </div>
    <span>T.C. <strong>{{ number_format($tc, 3) }}</strong></span>
    @if(auth()->user()?->role?->nombre === \App\Models\Role::ADMINISTRACION)
        @unless($compacto)<a href="{{ route('tipo-cambio.index') }}" class="{{ $oscuro ? 'text-white underline' : 'text-blue-600 hover:underline' }}">actualizar</a>@endunless
    @endif
</div>
@once
<script>
(function () {
    var CLAVE = 'kyrios_moneda';
    function guardada() { try { return localStorage.getItem(CLAVE) === 'PEN' ? 'PEN' : 'USD'; } catch (e) { return 'USD'; } }
    function aplicar(moneda) {
        document.querySelectorAll('.precio-dual').forEach(function (el) {
            var valor = parseFloat(el.dataset[moneda.toLowerCase()]);
            el.textContent = (moneda === 'USD' ? 'US$ ' : 'S/ ') + valor.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        });
        document.querySelectorAll('[data-selector-moneda] [data-moneda]').forEach(function (b) {
            var activo = b.dataset.moneda === moneda;
            b.className = 'px-3 py-1.5 text-sm font-medium' + (b.dataset.moneda === 'PEN' ? ' border-l border-gray-300' : '') + (activo ? ' bg-blue-700 text-white' : ' bg-white text-gray-700 hover:bg-gray-50');
            b.setAttribute('aria-pressed', activo ? 'true' : 'false');
        });
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-selector-moneda] [data-moneda]');
        if (!b) return;
        try { localStorage.setItem(CLAVE, b.dataset.moneda); } catch (err) {}
        aplicar(b.dataset.moneda);
        window.dispatchEvent(new CustomEvent('kyr-moneda', { detail: b.dataset.moneda }));
    });
    document.addEventListener('alpine:init', function () {
        if (window.Alpine && !Alpine.store('moneda')) {
            Alpine.store('moneda', {
                modo: guardada(),
                tc: parseFloat((document.querySelector('meta[name=tipo-cambio]') || {}).content) || 3.75,
                init() { window.addEventListener('kyr-moneda', (e) => { this.modo = e.detail; }); },
                usd(soles) { return ((Number(soles) || 0) / this.tc).toFixed(2); },
                fmt(soles) { var v = Number(soles) || 0; return this.modo === 'USD' ? 'US$ ' + (v / this.tc).toFixed(2) : 'S/ ' + v.toFixed(2); },
            });
        }
    });
    document.addEventListener('DOMContentLoaded', function () { aplicar(guardada()); });
})();
</script>
@endonce
