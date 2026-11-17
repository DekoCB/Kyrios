<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
body, div, p, h1, h2, h3, table, td, th, img, ul, li { margin: 0; padding: 0; box-sizing: border-box; }
@page { margin: 16mm 16mm 18mm 16mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
.pie { position: fixed; bottom: -13mm; left: 0; right: 0; height: 9mm; border-top: 2px solid #999; }
.pie .izq { position: absolute; left: 0; top: 1.5mm; font-size: 7pt; color: #444; }
.pie .rev { position: absolute; right: 0; top: 1.5mm; font-size: 7pt; color: #444; }
.pg:after { content: counter(page); }
.pag { page-break-before: always; }
.pag.primera { page-break-before: auto; }

.cab { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
.cab td { vertical-align: top; }
.cab .anexo { border: 1px solid #333; padding: 3mm 4mm; font-weight: bold; font-size: 9pt; text-align: center; }
.cab .foto { width: 24mm; height: 28mm; border: 1px solid #333; text-align: center; font-size: 8pt; vertical-align: middle; }
.cab .foto img { width: 24mm; height: 28mm; }
h1.t { text-align: center; font-size: 12pt; margin: 1mm 0 1.5mm; }
h2.s { font-size: 8.5pt; font-style: italic; margin: 2mm 0 1mm; }

table.f { width: 100%; border-collapse: separate; border-spacing: 1.2mm; }
table.f td.e { background: #1f497d; color: #fff; font-size: 7.5pt; padding: 1.1mm 2mm; text-align: center; }
table.f td.v { border: 1px solid #333; padding: 1.1mm 2mm; font-size: 8pt; height: 5.5mm; }
table.g { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
table.g th { background: #1f497d; color: #fff; font-size: 7pt; padding: 1.5mm; border: 1px solid #1f497d; }
table.g td { border: 1px solid #333; font-size: 7.5pt; padding: 1mm 1.6mm; height: 5.6mm; vertical-align: top; }
table.n { width: 100%; border-collapse: collapse; }
table.n td { border: 1px dotted #666; padding: 1.5mm 2mm; font-size: 7.5pt; vertical-align: top; }
.x { display: inline-block; width: 8px; height: 8px; border: 1px solid #333; text-align: center; font-size: 7pt; line-height: 8px; margin-right: 1mm; }
.cuadro { border: 1px solid #333; padding: 2mm 3mm; margin-bottom: 2mm; }
.dj h3 { font-size: 9.5pt; margin: 4mm 0 1mm; }
.dj p { font-size: 8.5pt; line-height: 1.35; text-align: justify; margin-bottom: 1.5mm; }
.firma { margin-top: 12mm; }
.firma .linea { border-top: 1px solid #000; width: 60mm; margin-bottom: 1mm; }
</style>
</head>
<body>

@php
    $e = $empleado;
    $f = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';
    $marca = fn ($cond) => '<span class="x">' . ($cond ? 'X' : '&nbsp;') . '</span>';
    $nivel = fn ($n) => \App\Models\Empleado::NIVELES[$n] ?? '';
    $firmada = fn ($k) => $e->declaraciones[$k] ?? null;
    $lugarFecha = 'Lima, ' . ($firmada('ficha') ? $f($firmada('ficha')) : '___ de _____________ del 20___');
    $nombre = $e->nombre_completo;
@endphp

<div class="pie"><span class="izq">Página <span class="pg"></span></span><span class="rev">Ficha del colaborador · {{ $e->codigo }} · generada el {{ now()->format('d/m/Y') }}</span></div>

{{-- ───────── 1. Datos personales y nóminas ───────── --}}
<div class="pag primera">
<table class="cab"><tr>
    <td style="width:55mm"><strong style="font-size:13pt">{{ $empresa->razon_social ?: 'KYRIOS LUCES S.A.C.' }}</strong></td>
    <td><div class="anexo">ANEXO N°1: Ficha del Colaborador</div></td>
    <td class="foto" style="width:28mm">@if($fotoDataUri)<img src="{{ $fotoDataUri }}" alt="">@else FOTO @endif</td>
</tr></table>
<h1 class="t">FICHA DEL COLABORADOR</h1>

<h2 class="s">DATOS PERSONALES</h2>
<table class="f">
    <tr><td class="e" style="width:30%">Código</td><td class="e">Fecha de ingreso</td><td class="e">Fecha de cese</td></tr>
    <tr><td class="v">{{ $e->codigo }}</td><td class="v">{{ $f($e->fecha_ingreso) }}</td><td class="v">{{ $f($e->fecha_cese) }}</td></tr>
</table>
<table class="f">
    <tr><td class="e">Apellidos y nombres</td></tr>
    <tr><td class="v">{{ mb_strtoupper($e->apellidos . ', ' . $e->nombres) }}</td></tr>
</table>
<table class="f">
    <tr><td class="e">Puesto</td><td class="e">Área</td><td class="e">Nombre y V°B° persona que contrata</td></tr>
    <tr><td class="v">{{ $e->cargo }}</td><td class="v">{{ $e->area }}</td><td class="v">{{ $e->jefe_contrata }}</td></tr>
</table>
<table class="f">
    <tr><td class="e" style="width:22%">Documento de identidad</td><td class="v" style="width:28%">DNI {{ $e->dni }}</td><td class="e" style="width:18%">Nacionalidad</td><td class="v">{{ $e->nacionalidad }}</td></tr>
    <tr><td class="e">Estado civil</td><td class="v">{{ \App\Models\Empleado::ESTADOS_CIVILES[$e->estado_civil] ?? '' }}</td><td class="e">Correo electrónico</td><td class="v">{{ $e->email_personal }}</td></tr>
    <tr><td class="e">Grado de instrucción</td><td class="v">{{ $e->grado_instruccion }}</td><td class="e">Número de celular</td><td class="v">{{ $e->telefono }}</td></tr>
    <tr><td class="e">Lugar de nacimiento</td><td class="v">{{ $e->lugar_nacimiento }}</td><td class="e">Fecha de nacimiento</td><td class="v">{{ $f($e->fecha_nacimiento) }}</td></tr>
    <tr><td class="e">Dirección</td><td class="v" colspan="2">{{ $e->direccion }}</td><td class="v">Sexo: {{ ['M' => 'Masculino', 'F' => 'Femenino'][$e->sexo] ?? '' }}</td></tr>
</table>
<p style="font-size:8pt;margin:2mm 0 1mm">En caso de emergencias:</p>
<table class="f">
    <tr><td class="e" style="width:30%">Dirección domicilio alterno</td><td class="v">{{ $e->domicilio_alterno }}</td></tr>
    <tr><td class="e">Nombre y teléfono de referencia</td><td class="v">{{ trim($e->contacto_emergencia . ' · ' . $e->telefono_emergencia . ($e->parentesco_emergencia ? ' (' . $e->parentesco_emergencia . ')' : ''), ' ·') }} @if($e->tipo_sangre)&nbsp;&nbsp;&nbsp; Tipo de sangre: <strong>{{ $e->tipo_sangre }}</strong>@endif</td></tr>
</table>

<h2 class="s">DATOS PARA NOMINAS: llenado por Recursos Humanos</h2>
<table class="n">
    <tr><td style="width:34%"><strong>Centro de costos:</strong> {{ $e->centro_costos }}</td><td style="width:34%"><strong>Tiempo de contratación:</strong> {{ $e->tipo_contrato_nombre }}@if($e->fecha_fin_contrato) (hasta {{ $f($e->fecha_fin_contrato) }})@endif</td><td><strong>Horario:</strong> {{ substr($e->hora_entrada, 0, 5) }} a {{ substr($e->hora_salida, 0, 5) }}</td></tr>
    <tr><td colspan="3"><strong>Periodo de prueba:</strong> {{ $e->periodo_prueba }}</td></tr>
    <tr><td><strong>Remuneración:</strong><br>{!! $marca($e->tipo_remuneracion === 'empleado') !!} Empleado &nbsp; {!! $marca($e->tipo_remuneracion === 'operario') !!} Operario</td>
        <td colspan="2"><strong>Pagos adicionales:</strong>
            {!! $marca($e->modalidad_pago === 'tiempo_parcial') !!} Tiempo parcial &nbsp; {!! $marca($e->modalidad_pago === 'tiempo_completo') !!} Tiempo completo &nbsp; {!! $marca($e->modalidad_pago === 'ejecutivo') !!} Ejecutivo &nbsp;
            {!! $marca($e->modalidad_pago === 'practicante') !!} Practicante &nbsp; {!! $marca($e->modalidad_pago === 'otro') !!} Otro{{ $e->modalidad_pago === 'otro' && $e->modalidad_pago_otro ? ': ' . $e->modalidad_pago_otro : '' }}</td></tr>
    <tr><td><strong>Local:</strong><br>
            {!! $marca($e->local_trabajo === 'san_isidro') !!} San Isidro<br>{!! $marca($e->local_trabajo === 'la_molina') !!} La Molina<br>{!! $marca($e->local_trabajo === 'la_victoria') !!} La Victoria<br>{!! $marca($e->local_trabajo === 'otros') !!} Otros{{ $e->local_trabajo === 'otros' && $e->local_otro ? ': ' . $e->local_otro : '' }}</td>
        <td colspan="2"><strong>Calificación:</strong><br>
            @foreach(\App\Models\Empleado::CALIFICACIONES as $k => $t){!! $marca($e->calificacion === $k) !!} {{ $t }}<br>@endforeach</td></tr>
</table>
</div>

{{-- ───────── 2. Educación ───────── --}}
<div class="pag">
<h2 class="s" style="font-style:normal;font-weight:bold">EDUCACIÓN</h2>
@php $edu = collect($e->educacion ?? []); @endphp
<table class="g">
    <tr><th style="width:25mm">Nivel</th><th>Centro de estudios (sólo en el que concluyó)</th><th style="width:24mm">Fecha de inicio</th><th style="width:24mm">Fecha de término</th></tr>
    @foreach(['Superior', 'Técnico', 'Secundaria'] as $n)
        @php $fila = $edu->firstWhere('nivel', $n) ?? []; @endphp
        <tr><td>{{ $n }}</td><td>{{ $fila['centro'] ?? '' }}</td><td>{{ $fila['inicio'] ?? '' }}</td><td>{{ $fila['fin'] ?? '' }}</td></tr>
    @endforeach
</table>
<table class="g"><tr><th>Profesión (indicar la profesión y si es egresado, bachiller o titulado)</th></tr>
    <tr><td style="height:12mm">{{ $e->profesion }}@if($e->profesion_condicion) ({{ \App\Models\Empleado::CONDICIONES_PROFESION[$e->profesion_condicion] ?? '' }})@endif</td></tr></table>

<h2 class="s" style="font-style:normal;font-weight:bold">Habilidades que posee (indicar nivel de dominio)</h2>
<table class="g">
    <tr><th rowspan="2" style="width:32mm">Idioma</th><th colspan="3">Habla</th><th colspan="3">Lee</th><th colspan="3">Escribe</th></tr>
    <tr>@foreach(['habla', 'lee', 'escribe'] as $c)<th>Básico</th><th>Intermedio</th><th>Avanzado</th>@endforeach</tr>
    @for($i = 0; $i < max(3, count($e->idiomas ?? [])); $i++)
        @php $id = ($e->idiomas ?? [])[$i] ?? []; @endphp
        <tr><td>{{ $id['idioma'] ?? '' }}</td>
            @foreach(['habla', 'lee', 'escribe'] as $c)@foreach(['basico', 'intermedio', 'avanzado'] as $n)<td style="text-align:center">{{ ($id[$c] ?? '') === $n ? 'X' : '' }}</td>@endforeach @endforeach</tr>
    @endfor
</table>
<table class="g">
    <tr><th>Software</th><th style="width:22mm">Básico</th><th style="width:22mm">Intermedio</th><th style="width:22mm">Avanzado</th></tr>
    @for($i = 0; $i < max(5, count($e->software ?? [])); $i++)
        @php $sw = ($e->software ?? [])[$i] ?? []; @endphp
        <tr><td>{{ $sw['nombre'] ?? '' }}</td>@foreach(['basico', 'intermedio', 'avanzado'] as $n)<td style="text-align:center">{{ ($sw['nivel'] ?? '') === $n ? 'X' : '' }}</td>@endforeach</tr>
    @endfor
</table>
<table class="g"><tr><th>Otros estudios (incluir seminarios, cursos, especializaciones, maestrías, etc.)</th></tr>
    @for($i = 0; $i < max(4, count($e->otros_estudios ?? [])); $i++)<tr><td>{{ ($e->otros_estudios ?? [])[$i] ?? '' }}</td></tr>@endfor
</table>
</div>

{{-- ───────── 3. Experiencia laboral ───────── --}}
<div class="pag">
<h2 class="s" style="font-style:normal;font-weight:bold">EXPERIENCIA LABORAL <span style="font-weight:normal">(mencione los tres últimos empleos, desde el más reciente, inclusive el actual si está laborando)</span></h2>
@for($i = 0; $i < 3; $i++)
    @php $x = ($e->experiencia ?? [])[$i] ?? []; @endphp
    <table class="g">
        <tr><td colspan="2">Empresa: {{ $x['empresa'] ?? '' }}</td><td>Teléfono: {{ $x['telefono'] ?? '' }}</td></tr>
        <tr><td colspan="2">Último cargo: {{ $x['cargo'] ?? '' }}</td><td>Jefe inmediato: {{ $x['jefe'] ?? '' }}</td></tr>
        <tr><td>Fecha de ingreso: {{ $x['ingreso'] ?? '' }}</td><td>Fecha de salida: {{ $x['salida'] ?? '' }}</td><td>Última remuneración: {{ $x['remuneracion'] ?? '' }}</td></tr>
        <tr><td colspan="3" style="height:20mm">Funciones principales: {{ $x['funciones'] ?? '' }}</td></tr>
        <tr><td colspan="3">Motivo de cese: {{ $x['motivo_cese'] ?? '' }}@if($i === 0) <br>En caso de estar laborando actualmente ¿Se puede solicitar información a su empleador? {!! $marca(!empty($x['consultar'])) !!} SÍ &nbsp; {!! $marca(!empty($x) && empty($x['consultar'])) !!} NO @endif</td></tr>
    </table>
@endfor
</div>

{{-- ───────── 4–6. Postulación, familia y referencias ───────── --}}
<div class="pag">
<table class="g">
    <tr><td colspan="2">Puesto solicitado: {{ $e->puesto_solicitado }}</td></tr>
    <tr><td>Fecha de disponibilidad: {{ $f($e->fecha_disponibilidad) }}</td><td>Sueldo mínimo que aceptaría: {{ $e->sueldo_minimo !== null ? 'S/ ' . number_format((float) $e->sueldo_minimo, 2) : '' }}</td></tr>
    <tr><td>Viajaría a provincias {!! $marca($e->viajaria_provincias === true) !!} SÍ {!! $marca($e->viajaria_provincias === false) !!} NO</td><td>¿Trabajó o practicó antes en KYRIOS LUCES & ENTORNO? {!! $marca($e->trabajo_antes_kyrios === true) !!} SÍ {!! $marca($e->trabajo_antes_kyrios === false) !!} NO</td></tr>
    <tr><td colspan="2">Tiene conocidos en KYRIOS LUCES & ENTORNO ¿Quiénes? {{ $e->conocidos_kyrios }}</td></tr>
    <tr><td colspan="2">Tiene familiares en KYRIOS LUCES & ENTORNO ¿Quiénes? {{ $e->familiares_kyrios }}</td></tr>
</table>

<h2 class="s" style="font-style:normal;font-weight:bold">DATOS FAMILIARES (Personas que viven y/o dependen económicamente de Ud.)</h2>
<table class="g">
    <tr><th>Nombres y apellidos</th><th>Parentesco</th><th>No. DNI</th><th>Fecha nacimiento</th><th>Centro de estudios o trabajo</th><th>Ocupación</th></tr>
    @for($i = 0; $i < max(5, count($e->dependientes ?? [])); $i++)
        @php $d = ($e->dependientes ?? [])[$i] ?? []; @endphp
        <tr><td>{{ $d['nombres'] ?? '' }}</td><td>{{ $d['parentesco'] ?? '' }}</td><td>{{ $d['dni'] ?? '' }}</td><td>{{ $f($d['nacimiento'] ?? null) }}</td><td>{{ $d['centro'] ?? '' }}</td><td>{{ $d['ocupacion'] ?? '' }}</td></tr>
    @endfor
</table>

<h2 class="s" style="font-style:normal;font-weight:bold">DATOS FAMILIARES (esposo(a), hijos(as), padre, madre, hermanos(as), suegra y suegro que están vivos)</h2>
<table class="g">
    <tr><th>Nombres y apellidos</th><th>Parentesco</th><th>No. DNI</th><th>Nacimiento</th><th>Teléfono</th><th>Estudios o trabajo</th><th>Ocupación</th><th>Domicilio</th><th>Otros</th></tr>
    @for($i = 0; $i < max(5, count($e->familiares ?? [])); $i++)
        @php $d = ($e->familiares ?? [])[$i] ?? []; @endphp
        <tr style="font-size:6.5pt"><td>{{ $d['nombres'] ?? '' }}</td><td>{{ $d['parentesco'] ?? '' }}</td><td>{{ $d['dni'] ?? '' }}</td><td>{{ $f($d['nacimiento'] ?? null) }}</td><td>{{ $d['telefono'] ?? '' }}</td><td>{{ $d['centro'] ?? '' }}</td><td>{{ $d['ocupacion'] ?? '' }}</td><td>{{ $d['direccion'] ?? '' }}</td><td>{{ $d['otros'] ?? '' }}</td></tr>
    @endfor
</table>

<h2 class="s" style="font-style:normal;font-weight:bold">REFERENCIAS PERSONALES</h2>
<table class="g">
    <tr><th>Nombres y apellidos</th><th>Empresa en que labora</th><th>Cargo</th><th>Teléfono</th><th>Tiempo de conocerlo</th></tr>
    @for($i = 0; $i < max(2, count($e->referencias ?? [])); $i++)
        @php $d = ($e->referencias ?? [])[$i] ?? []; @endphp
        <tr><td>{{ $d['nombres'] ?? '' }}</td><td>{{ $d['empresa'] ?? '' }}</td><td>{{ $d['cargo'] ?? '' }}</td><td>{{ $d['telefono'] ?? '' }}</td><td>{{ $d['tiempo'] ?? '' }}</td></tr>
    @endfor
</table>
</div>

{{-- ───────── 7–8. Pago de haberes, pensiones y domicilio ───────── --}}
<div class="pag">
<h2 class="s" style="font-style:normal;font-weight:bold">CUENTA BANCARIA PARA PAGO DE HABERES</h2>
<div class="cuadro">
    Poseo cuenta de ahorro sueldo: {!! $marca($e->posee_cuenta_sueldo === true) !!} Sí &nbsp;&nbsp; {!! $marca($e->posee_cuenta_sueldo === false) !!} No<br><br>
    N° Cuenta BBVA o CCI: <strong>{{ $e->numero_cuenta }}</strong> &nbsp;&nbsp; Banco: <strong>{{ $e->banco }}</strong> &nbsp;&nbsp; Moneda: <strong>{{ ['PEN' => 'Soles', 'USD' => 'Dólares'][$e->moneda_pago] ?? '' }}</strong>
    <p style="font-size:7pt;color:#555;margin-top:2mm">Si no posee cuenta, autoriza que el abono mensual de sus remuneraciones, conceptos no remunerativos, otros ingresos y CTS se efectúe en el banco y la moneda indicados.</p>
</div>

<h2 class="s" style="font-style:normal;font-weight:bold">SISTEMA DE PENSIONES</h2>
<div class="cuadro">
    {!! $marca($e->pension_situacion === 'jubilado') !!} <strong>Opción 1:</strong> Me encuentro en situación de Jubilado; régimen pensionario actual: {{ $e->pension_situacion === 'jubilado' ? $e->pension_regimen : '' }}<br>
    {!! $marca($e->pension_situacion === 'afiliado_afp') !!} <strong>Opción 2:</strong> Estoy actualmente afiliado a la A.F.P. {{ $e->pension_situacion === 'afiliado_afp' ? $e->afp_nombre : '' }}<br>
    {!! $marca($e->pension_situacion === 'afiliado_onp') !!} <strong>Opción 3:</strong> Estoy actualmente afiliado al Sistema Nacional de Pensiones - O.N.P.<br>
    {!! $marca($e->pension_situacion === 'desea_onp') !!} <strong>Opción 4:</strong> Deseo aportar en el Sistema Nacional de Pensiones - O.N.P.<br>
    {!! $marca($e->pension_situacion === 'desea_afp') !!} <strong>Opción 5:</strong> Deseo afiliarme al Sistema Privado de Pensiones - A.F.P. Por ley será a la A.F.P. de menor comisión, AFP Habitat.
    @if($e->cuspp)<p style="margin-top:2mm">CUSPP: {{ $e->cuspp }}</p>@endif
</div>

<h2 class="s" style="font-style:normal;font-weight:bold">CROQUIS DE DOMICILIO</h2>
<div class="cuadro" style="height:70mm;text-align:center;color:#999;padding-top:30mm">Dibuje el croquis con las calles cercanas a su domicilio</div>
<table class="g"><tr><td style="height:14mm">Referencia domiciliaria: {{ $e->referencia_domiciliaria }}</td></tr></table>
</div>

{{-- ───────── 9. Indicaciones generales ───────── --}}
<div class="pag dj">
<h3>INDICACIONES GENERALES:</h3>
<p>1. De conformidad con las disposiciones legales vigentes, toda persona que sea admitida para ocupar un puesto en la empresa, estará sujeto a un periodo de prueba mínimo de tres meses, el cual podrá ser mayor dependiendo de la calificación del cargo a ocupar o de la complejidad de las labores a desarrollar de conformidad a la normativa aplicable. Si durante el transcurso de este plazo, la Empresa no considera conveniente la admisión definitiva, dicha persona cesará en el puesto sin responsabilidad alguna para ambas partes.</p>
<p>2. La tergiversación u omisión de datos relacionados con esta ficha se considera suficiente motivo para anularla.</p>
<p>Declaro formalmente que toda información que consigno en la presente ficha de empleo así como los certificados de estudios y de trabajo que presento son ciertos; en caso contrario será causal de despido por proporcionar información falsa a la Institución.</p>
<div class="firma"><p>Fecha: {{ $firmada('ficha') ? $f($firmada('ficha')) : '____ - ____ - ______' }} &nbsp;&nbsp;&nbsp;&nbsp; Firma: ________________________</p></div>
</div>

{{-- ───────── 10. Declaraciones juradas ───────── --}}
<div class="pag dj">
<h3>DECLARACIÓN JURADA ANTECEDENTES PENALES</h3>
<p>Declaro bajo juramento no registrar antecedentes penales y/o judiciales, tanto dentro como fuera del territorio nacional, así como tampoco encontrarme bajo investigación de ninguna índole.</p>
<h3>DECLARACIÓN JURADA DELITOS</h3>
<p>Declaro bajo juramento que no he sido condenado(a), con sentencia consentida o ejecutoriada, por los delitos de terrorismo, delito de apología del terrorismo, delitos de violación de la libertad sexual y/o delitos de tráfico ilícito de drogas. Realizo la presente declaración para los fines establecidos en la Ley Nº 29988, y asumiendo responsabilidad por la veracidad de la misma.</p>
<h3>DECLARACIÓN JURADA DE RIESGOS GENERALES DE SEGURIDAD Y SALUD EN EL TRABAJO</h3>
<p>De conformidad con la Ley N° 29783, Ley de Seguridad y Salud en el Trabajo, declaro bajo juramento me encuentro plenamente informado de los riesgos generales de seguridad y salud de mi puesto de trabajo. Así mismo me comprometo con cumplir con todas las normas, reglamentos, procedimientos e instructivos relacionados a con temas de Seguridad, Salud y Medio Ambiente.</p>
<h3>DECLARACIÓN JURADA DE RELACIÓN DE PARENTESCO Y DE RELACIÓN SENTIMENTAL (I)</h3>
<p>Declaro bajo juramento que no me une parentesco alguno de consanguinidad, afinidad o por razón de matrimonio o uniones de hecho, con personal que labora en KYRIOS LUCES & ENTORNO, bajo cualquier forma de contratación (a plazo indeterminado, sujeto a modalidad, jornada de trabajo a tiempo parcial, etc.).</p>
<p>Asimismo, declaro que no mantengo ninguna clase de relación sentimental con algún(a) colaborador(a) de KYRIOS LUCES & ENTORNO. Al respecto, reconozco y acepto como mi obligación de trabajo que debo informar, inmediatamente, al Departamento de Recursos Humanos de la empresa si durante mi relación laboral dicha situación cambiara, para lo cual utilizaré el formato correspondiente que se adjunta.</p>
<p>Ratifico la veracidad de lo declarado, por lo que acepto que toda falsedad de la información brindada genera una falta grave causal de despido. Formulo la siguiente Declaración Jurada para los fines pertinentes.</p>
<h3>DECLARACIÓN JURADA DE CONOCIMIENTO DE PERFIL DE PUESTO</h3>
<p>Confirmo que he leído el perfil del puesto al que postulo y estoy de acuerdo con la información vertida en el mismo.</p>
<div class="firma"><div class="linea"></div><p><strong>FIRMA</strong></p><p>NOMBRE: {{ $nombre }}<br>DNI: {{ $e->dni }}<br>FECHA: {{ $firmada('antecedentes') ? $f($firmada('antecedentes')) : '___________' }}</p></div>
</div>

{{-- ───────── 11. Vida Ley ───────── --}}
<div class="pag dj">
<h3 style="text-align:center;text-decoration:underline">DECLARACIÓN JURADA DE CONTRATACIÓN COMO EXTRABAJADOR DE PÓLIZA DE SEGURO VIDA LEY</h3>
<p style="text-align:center">(ARTÍCULO 6º DEL DECRETO SUPREMO Nº 003-2011-TR)</p>
<p style="margin-top:5mm">Yo, <strong>{{ $nombre }}</strong>, identificado con DNI Nº <strong>{{ $e->dni }}</strong>, declaro bajo juramento lo siguiente:</p>
@php $vl = $e->vida_ley ?? []; @endphp
<p>1. {!! $marca($e->vida_ley_extrabajador === false) !!} No he adquirido la Póliza del Seguro de Vida Ley en mi calidad de extrabajador.</p>
<p>2. {!! $marca($e->vida_ley_extrabajador === true) !!} Si, tengo contratada la Póliza del Seguro de Vida Ley en mi calidad de extrabajador y los datos de la misma son los siguientes:</p>
<p>- Compañía de Seguros: {{ $vl['compania'] ?? '______________________________' }}<br>
   - Número de la Póliza: {{ $vl['poliza'] ?? '______________________________' }}<br>
   - Vigencia de la Póliza: {{ $vl['vigencia'] ?? '______________________________' }}<br>
   - Remuneración asegurable: {{ $vl['remuneracion'] ?? '______________________________' }}</p>
<table class="g" style="margin-top:3mm"><tr><th>Beneficiarios: Nombres y Apellidos</th><th style="width:40mm">Parentesco</th></tr>
    @for($i = 0; $i < max(5, count($vl['beneficiarios'] ?? [])); $i++)<tr><td>{{ $vl['beneficiarios'][$i]['nombres'] ?? '' }}</td><td>{{ $vl['beneficiarios'][$i]['parentesco'] ?? '' }}</td></tr>@endfor
</table>
<p style="text-align:right;margin-top:4mm">Lima, {{ $firmada('vida_ley') ? $f($firmada('vida_ley')) : '___ de _____________ del 20___' }}</p>
<div class="firma"><div class="linea"></div><p>Firma</p></div>
</div>

{{-- ───────── 12. Reglamento interno ───────── --}}
<div class="pag dj">
<h3 style="text-align:center">REGLAMENTO INTERNO DE TRABAJO, CÓDIGO DE CONDUCTA Y REGLAMENTO INTERNO DE SEGURIDAD Y SALUD EN EL TRABAJO</h3>
<h3 style="text-align:center">DECLARACIÓN DE CONFIRMACIÓN</h3>
<p>Confirmo que he recibido y leído el Reglamento Interno de Trabajo de KYRIOS LUCES S.A.C, así como he entendido las políticas y normas contenidas en los mismos. Reconozco que como colaborador de KYRIOS LUCES SAC, es mi obligación:</p>
<p>● No violar conscientemente las políticas y normas contenidas en los documentos recibidos.</p>
<p>● Buscar orientación siempre que surjan cuestiones inherentes con la interpretación o aplicación de las políticas y normas contenidas en los mismos.</p>
<p>● Informar inmediatamente a la instancia superior y/o Recursos Humanos de cualquier situación que constituya una violación a estos documentos o ley, ya sea por uno mismo o por terceros siendo testigo y/o conocedor del hecho y cumplir cualquier directiva emitida por la empresa.</p>
<div class="firma"><p>Firma ________________________</p><p>Fecha: {{ $firmada('reglamento') ? $f($firmada('reglamento')) : '____________' }}</p><p>Nombre del Trabajador: {{ $nombre }}</p></div>
</div>

{{-- ───────── 13. Declaración de salud ───────── --}}
<div class="pag dj">
<h3 style="text-align:center;text-decoration:underline">DECLARACIÓN DE SALUD</h3>
<p style="margin-top:4mm">YO, <strong>{{ $nombre }}</strong> identificado con DNI No <strong>{{ $e->dni }}</strong> con domicilio en <strong>{{ $e->direccion }}</strong></p>
<p>Declaro que tengo las siguientes enfermedades al momento de iniciar con el puesto:</p>
@for($i = 0; $i < 5; $i++)<p>{{ $i + 1 }}) {{ ($e->enfermedades ?? [])[$i] ?? '' }}</p>@endfor
<p>Declaro que no tengo ninguna enfermedad que impida el desempeño de mis funciones</p>
<p>{!! $marca($e->sin_enfermedades === true) !!} No tengo ninguna enfermedad</p>
<div class="firma"><p>Firma ________________________</p><p>Fecha: {{ $firmada('salud') ? $f($firmada('salud')) : '____________' }}</p><p>Nombre del Trabajador: {{ $nombre }}</p></div>
</div>
</body>
</html>
