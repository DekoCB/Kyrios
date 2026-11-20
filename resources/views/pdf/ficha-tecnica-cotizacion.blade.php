<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
<style>
/* Carlito = clon libre de Calibri, la tipografía de la ficha original (se registra en FuentesPdf) */
/* sin «*»: un margin:0 universal anula el margen de @page en dompdf */
body, div, p, h1, h2, table, td, th, img, ul, li { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 41mm 20mm 40mm 21mm; }
body { font-family: Carlito, Calibri, sans-serif; font-size: 9.5pt; color: #000; }

/* Banda gris con el logo y pie con la web, en cada hoja */
.enc { position: fixed; top: -41mm; left: -21mm; width: 210mm; height: 36mm; }
.enc .banda { position: absolute; top: 9.2mm; left: 0; width: 210mm; height: 19.5mm; background: #efefef; }
.enc img { position: absolute; left: 13.5mm; top: 11.5mm; height: 15.5mm; }
.pie { position: fixed; top: 233mm; left: 0; width: 171mm; border-top: 2px solid #000; padding-top: 2mm; text-align: right; font-size: 8.5pt; }

.ficha { page-break-after: always; }
.ficha:last-child { page-break-after: auto; }
h1.titulo { font-size: 14.5pt; padding: 0 0 0.8mm; border-bottom: 2px solid #000; margin-bottom: 1.2mm; }
.sellos { text-align: left; margin: 1.5mm 0 0 1mm; height: 8mm; }
.sellos img { height: 6.5mm; margin-right: 1.2mm; }
.sellos img { max-width: none; max-height: none; margin: 0 1.2mm 0 0; }

table.cuerpo { width: 100%; border-collapse: collapse; }
table.cuerpo > tbody > tr > td { vertical-align: top; }
td.izq { width: 65mm; padding-right: 4mm; text-align: center; }
td.izq img.foto { max-width: 36mm; max-height: 38mm; margin: 4mm 0 0; }
.sin-foto { border: 1px dashed #bbb; color: #999; padding: 18mm 0; font-size: 8.5pt; }

.barra { background: #dedede; font-weight: bold; font-size: 8.5pt; padding: 0.9mm 2mm; margin-bottom: 1.5mm; }
.aplic { font-size: 8.5pt; line-height: 1.32; text-align: justify; height: 31mm; padding: 0 1mm; border-bottom: 1px solid #000; margin-bottom: 1.5mm; }
.espec { height: 122mm; border-bottom: 1px solid #000; }
table.spec { width: 100%; border-collapse: collapse; }
table.spec td { font-size: 9pt; padding: 0.7mm 2mm; border-bottom: 1px solid #e3e3e3; vertical-align: top; }
table.spec td.e { width: 30mm; font-weight: bold; }
.sin-datos { font-size: 8.5pt; color: #777; padding: 2mm; }
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

<div class="enc"><div class="banda"></div>@if($logo)<img src="{{ $logo }}" alt="Kyrios">@endif</div>
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

        <table class="cuerpo">
            <tr>
                <td class="izq">
                    <div class="sellos">@if($sello)<img src="{{ public_path('img/proforma/sello-led.png') }}" alt="LED">@endif</div>
                    @forelse($fotos as $f)<img class="foto" src="{{ $f }}" alt="">@empty<div class="sin-foto">Sin foto</div>@endforelse
                </td>
                <td>
                    <div class="barra">Aplicaciones</div>
                    <div class="aplic">{{ $aplicaciones ?: '—' }}</div>

                    <div class="barra">Especificaciones técnicas</div>
                    <div class="espec">
                    @if($filas)
                        <table class="spec">
                            @foreach($filas as $etiqueta => $valor)
                                <tr><td class="e">{{ $etiqueta }}:</td><td>{{ $valor }}</td></tr>
                            @endforeach
                        </table>
                    @else
                        <p class="sin-datos">Sin características técnicas registradas.</p>
                    @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>
@endforeach

</body>
</html>
