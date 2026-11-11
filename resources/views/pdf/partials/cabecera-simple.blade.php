{{-- Cabecera común de los PDF sencillos. Recibe $empresa, $titulo y $numero. --}}
@php $logoSrc = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null); @endphp
<div class="cab">
    <div class="cab-izq">
        @if($logoSrc)<img src="{{ $logoSrc }}" class="logo" alt="Logo">@endif
        <div class="empresa">{{ $empresa->razon_social }}</div>
        <div class="empresa-dir">
            @if($empresa->ruc)R.U.C.: {{ $empresa->ruc }}<br>@endif
            {{ $empresa->direccion }}<br>
            @if($empresa->telefono)Telf: {{ $empresa->telefono }} @endif @if($empresa->email)· {{ $empresa->email }}@endif
        </div>
    </div>
    <div class="cab-der">
        <div class="doc-tipo">{{ $titulo }}</div>
        <div class="doc-numero">{{ $numero }}</div>
    </div>
</div>
