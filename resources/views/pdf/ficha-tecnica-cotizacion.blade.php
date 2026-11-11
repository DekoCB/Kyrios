<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@include('pdf.partials.estilos-simples')
.prod { border: 1px solid #d9d6cc; border-radius: 3px; margin-bottom: 10px; page-break-inside: avoid; }
.prod-cab { background: #faf9f5; border-bottom: 1px solid #d9d6cc; padding: 5px 8px; }
.prod-nombre { font-size: 9.5pt; font-weight: bold; color: #3f3d37; }
.prod-cod { font-size: 7pt; color: #666; margin-top: 1px; }
.prod-cuerpo { display: table; width: 100%; }
.prod-foto { display: table-cell; width: 120px; vertical-align: top; padding: 8px; text-align: center; }
.prod-foto img { max-width: 104px; max-height: 104px; }
.sin-foto { width: 104px; height: 80px; border: 1px dashed #ccc; color: #aaa; font-size: 7pt; padding-top: 32px; }
.prod-datos { display: table-cell; vertical-align: top; padding: 6px 8px 6px 0; }
table.d { width: 100%; border-collapse: collapse; }
table.d td { font-size: 7.5pt; padding: 2px 4px; border-bottom: 1px solid #f0ede4; vertical-align: top; }
table.d td.e { color: #666; width: 22%; font-weight: bold; }
.cant { float: right; font-size: 8pt; font-weight: bold; color: #3f3d37; }
</style>
</head>
<body>

@include('pdf.partials.cabecera-simple', ['titulo' => 'FICHA TÉCNICA', 'numero' => 'Cotización ' . $cotizacion->codigo])

<div class="caja">
    <div class="fila"><div class="e">Cliente:</div><div class="v">{{ $cotizacion->cliente?->nombre ?? '—' }}</div></div>
    <div class="fila"><div class="e">Fecha:</div><div class="v">{{ $cotizacion->fecha->format('d/m/Y') }}</div></div>
    <div class="fila"><div class="e">Productos:</div><div class="v">{{ $cotizacion->detalles->count() }}</div></div>
</div>

@foreach($cotizacion->detalles as $i => $d)
    @php
        $producto = $d->producto;
        $variante = $d->variante;
        $foto = $producto ? \App\Support\FichaProducto::imagenDataUri($variante?->imagenPrincipal ?? $producto->imagenes->first()) : null;
        $datos = $producto ? (\App\Support\FichaProducto::producto($producto) + ($variante ? \App\Support\FichaProducto::variante($variante) : [])) : [];
        $pares = array_chunk(array_map(null, array_keys($datos), array_values($datos)), 2);
    @endphp
    <div class="prod">
        <div class="prod-cab">
            <span class="cant">Cant. {{ $d->cantidad }}</span>
            <div class="prod-nombre">{{ $i + 1 }}. {{ $d->nombre_mostrar }}</div>
            <div class="prod-cod">{{ $d->codigos_mostrar }}</div>
        </div>
        <div class="prod-cuerpo">
            <div class="prod-foto">
                @if($foto)<img src="{{ $foto }}" alt="">@else<div class="sin-foto">Sin foto</div>@endif
            </div>
            <div class="prod-datos">
                @if($pares)
                    <table class="d">
                        @foreach($pares as $par)
                            <tr>
                                @foreach($par as [$etiqueta, $valor])
                                    <td class="e">{{ $etiqueta }}</td><td>{{ $valor }}</td>
                                @endforeach
                                @if(count($par) === 1)<td></td><td></td>@endif
                            </tr>
                        @endforeach
                    </table>
                @else
                    <p style="font-size:7.5pt;color:#777;padding:6px 0">Sin características técnicas registradas.</p>
                @endif
            </div>
        </div>
    </div>
@endforeach

<div class="nota">Ficha técnica referencial generada por el sistema. Las especificaciones pueden variar según el fabricante; esta hoja será reemplazada por el formato oficial.</div>

</body>
</html>
