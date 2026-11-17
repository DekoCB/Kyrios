<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
/* sin «*»: un margin:0 universal anula el margen de @page en dompdf */
body, div, p, h1, h2, table, td, th, img, ul, li { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 34mm 18mm 22mm 18mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #1a1a1a; }

/* Banda gris con el logo y pie con la web, en cada hoja */
.enc { position: fixed; top: -34mm; left: -18mm; right: -18mm; height: 27mm; background: #ededed; }
.enc img { position: absolute; left: 18mm; top: 6mm; height: 15mm; }
.pie { position: fixed; bottom: -17mm; left: 0; right: 0; border-top: 2px solid #000; padding-top: 2.5mm; text-align: right; font-size: 8pt; }

.ficha { page-break-after: always; }
.ficha:last-child { page-break-after: auto; }
h1.titulo { font-size: 14pt; padding: 2mm 0 1.5mm; border-bottom: 2px solid #000; margin-bottom: 3mm; }
.sellos { margin-bottom: 3mm; }
.sello { display: inline-block; border: 1px solid #555; padding: 1mm 2.5mm; font-size: 8pt; margin-right: 2mm; color: #333; }

table.cuerpo { width: 100%; border-collapse: collapse; }
table.cuerpo > tbody > tr > td { vertical-align: top; }
td.izq { width: 38%; padding-right: 6mm; text-align: center; }
td.izq img { max-width: 64mm; max-height: 72mm; margin-bottom: 4mm; }
.sin-foto { border: 1px dashed #bbb; color: #999; padding: 22mm 0; font-size: 8pt; margin-bottom: 4mm; }

.barra { background: #ececec; font-weight: bold; font-size: 7.5pt; padding: 1.2mm 2mm; margin-bottom: 2mm; }
.aplic { font-size: 7.5pt; line-height: 1.4; text-align: justify; margin-bottom: 8mm; padding: 0 1mm; }
table.spec { width: 100%; border-collapse: collapse; margin-bottom: 4mm; border-bottom: 1.5px solid #000; padding-bottom: 2mm; }
table.spec td { font-size: 8pt; padding: 1.7mm 2mm; border-bottom: 1px solid #e3e3e3; vertical-align: top; }
table.spec td.e { width: 38%; font-weight: bold; }
.cant { font-size: 8pt; color: #444; margin-bottom: 2mm; text-align: right; }
.codigos { font-size: 7pt; color: #666; margin-top: -1mm; margin-bottom: 3mm; }
</style>
</head>
<body>

@php
    $logo = \App\Models\Empresa::logoDocumentoDataUri($empresa ?? null);
    $web = preg_replace('#^https?://#', '', ($empresa->web ?? '') ?: 'www.kyrios.com.pe');
    $web = preg_replace('#^www\.#', '', $web);
    // Una hoja por producto (y variante) cotizado, en el orden de la proforma
    $detalles = \App\Support\SeccionesProforma::ordenar($cotizacion->detalles, $cotizacion->formato)
        ->unique(fn ($d) => $d->producto_id . '-' . ($d->variante_id ?? 0))->values();
@endphp

<div class="enc">@if($logo)<img src="{{ $logo }}" alt="Kyrios">@endif</div>
<div class="pie">Website: {{ $web }}</div>

@foreach($detalles as $d)
    @php
        $producto = $d->producto;
        $variante = $d->variante;
        $fotos = collect();
        if ($variante) { $fotos = $variante->imagenes()->orderByDesc('es_principal')->orderBy('orden')->limit(2)->get(); }
        if ($fotos->isEmpty() && $producto) { $fotos = $producto->imagenes->take(2); }
        $fotos = $fotos->map(fn ($im) => \App\Support\FichaProducto::imagenDataUri($im))->filter()->values();

        $datos = $producto ? (\App\Support\FichaProducto::producto($producto) + ($variante ? \App\Support\FichaProducto::variante($variante) : [])) : [];
        $mat = collect([$datos['Material principal'] ?? null, $datos['Material secundario'] ?? null])->filter()->implode(' | ');
        $medidas = collect([
            isset($datos['Diámetro']) ? 'Ø' . str_replace(' mm', '', $datos['Diámetro']) : null,
            isset($datos['Largo']) ? str_replace(' mm', '', $datos['Largo']) : null,
            isset($datos['Ancho']) ? str_replace(' mm', '', $datos['Ancho']) : null,
            isset($datos['Profundidad']) ? str_replace(' mm', '', $datos['Profundidad']) : null,
            isset($datos['Alto']) ? str_replace(' mm', '', $datos['Alto']) : null,
        ])->filter()->implode('*');
        $filas = array_filter([
            'Modelo' => $variante?->codigo_fabricante ?: ($variante?->codigo_kyrios ?: $producto?->codigo),
            'Montaje' => $datos['Tipos de instalación'] ?? null,
            'Material' => $mat ?: null,
            'Acabado' => $datos['Acabado'] ?? ($datos['Color'] ?? null),
            'Potencia' => $datos['Potencia'] ?? null,
            'Flujo luminoso' => $datos['Lúmenes'] ?? null,
            'Eficacia' => $datos['Eficiencia'] ?? null,
            'Voltaje' => $datos['Voltaje'] ?? null,
            'Temperatura de color' => $datos['Temperatura de color'] ?? null,
            'IP' => $datos['IP'] ?? null,
            'CRI' => $datos['CRI'] ?? null,
            'Ángulo' => $datos['Ángulo del haz'] ?? ($datos['Ángulo de apertura'] ?? null),
            'Dimensiones (mm)' => $medidas !== '' ? $medidas . ' mm' : null,
            'Vida útil' => $datos['Vida útil'] ?? null,
            'Garantía' => $datos['Garantía'] ?? null,
        ], fn ($x) => filled($x));
        $aplicaciones = filled($producto?->descripcion) ? $producto->descripcion
            : collect([$datos['Tipo de luminaria'] ?? null, isset($datos['Uso']) ? 'para uso ' . mb_strtolower($datos['Uso']) : null, $datos['Aplicación'] ?? null])->filter()->implode(' · ');
        $sello = str_contains(mb_strtoupper(($datos['Fuente de luz'] ?? '') . ' ' . ($datos['Tipo de lámpara'] ?? '')), 'LED');
    @endphp
    <div class="ficha">
        <h1 class="titulo">{{ $d->nombre_mostrar }}</h1>
        <div class="cant">Cantidad cotizada: {{ $d->cantidad }}</div>
        @if($sello)<div class="sellos"><span class="sello">LED</span></div>@endif

        <table class="cuerpo">
            <tr>
                <td class="izq">
                    @forelse($fotos as $f)<img src="{{ $f }}" alt="">@empty<div class="sin-foto">Sin foto</div>@endforelse
                </td>
                <td>
                    <div class="barra">Aplicaciones</div>
                    <div class="aplic">{{ $aplicaciones ?: '—' }}</div>

                    <div class="barra">Especificaciones técnicas</div>
                    @if($filas)
                        <table class="spec">
                            @foreach($filas as $etiqueta => $valor)
                                <tr><td class="e">{{ $etiqueta }}:</td><td>{{ $valor }}</td></tr>
                            @endforeach
                        </table>
                    @else
                        <p style="font-size:7.5pt;color:#777;padding:2mm">Sin características técnicas registradas.</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>
@endforeach

</body>
</html>
