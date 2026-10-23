{{-- Botón para cambiar entre tema claro y oscuro. $clase: clases extra del contenedor --}}
<button type="button" onclick="kyrToggleTema()" title="Cambiar tema claro / oscuro" aria-label="Cambiar tema claro / oscuro"
        class="tema-toggle {{ $clase ?? '' }}">
    <i class="fas fa-moon tema-icono-luna"></i>
    <i class="fas fa-sun tema-icono-sol"></i>
    <span class="tema-toggle-texto">{{ $texto ?? '' }}</span>
</button>
