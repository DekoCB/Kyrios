<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@include('pdf.partials.estilos-simples')
table.t td { height: 26px; border: 1px solid #cfcabb; }
table.t th { border: 1px solid #3f3d37; }
.datos { margin-bottom: 8px; }
.linea { border-bottom: 1px solid #888; display: inline-block; width: 220px; height: 11px; }
</style>
</head>
<body>

@include('pdf.partials.cabecera-simple', ['titulo' => 'PLANTILLA DE HOMOLOGACIÓN', 'numero' => 'Productos requeridos'])

<div class="caja datos">
    <div class="fila"><div class="e">Cliente / obra:</div><div class="v"><span class="linea" style="width:340px"></span></div></div>
    <div class="fila"><div class="e">Contacto:</div><div class="v"><span class="linea" style="width:340px"></span> &nbsp; Fecha: <span class="linea" style="width:90px"></span></div></div>
</div>

<p style="font-size:7.5pt;color:#555;margin-bottom:6px">Anote un producto por fila. Solo la descripción y la cantidad son indispensables; el resto de datos ayuda a encontrar el producto más parecido.</p>

<table class="t">
    <thead>
        <tr>
            <th class="c" style="width:22px">N.°</th>
            @foreach($columnas as $titulo)<th>{{ $titulo }}</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @for($i = 1; $i <= 14; $i++)
            <tr><td class="c">{{ $i }}</td>@foreach($columnas as $_)<td></td>@endforeach</tr>
        @endfor
    </tbody>
</table>

<div class="nota">Formato referencial: será reemplazado por la plantilla oficial.</div>

</body>
</html>
