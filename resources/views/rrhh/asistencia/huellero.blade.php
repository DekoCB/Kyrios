@extends('layouts.modulo')
@section('titulo', 'Huellero')
@section('subtitulo', 'Asistencia desde el reloj biométrico ZKTeco')

@section('contenido')
    @include('rrhh.partials.menu', ['activo' => 'huellero'])

    @if($analisis)
        {{-- ───────── Revisión antes de importar ───────── --}}
        <section class="bg-white rounded-xl shadow-sm p-5 mb-6">
            <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-list-check text-gray-400 mr-1"></i> Revise antes de importar</h2>
            <p class="text-sm text-gray-600 mb-4">
                Archivo <strong>{{ $analisis['nombre'] }}</strong>: {{ $analisis['total'] }} marcaciones leídas
                @if($analisis['repetidas']), {{ $analisis['repetidas'] }} repetidas (se cuentan una vez)@endif
                @if($analisis['ignoradas']), {{ $analisis['ignoradas'] }} línea(s) sin fecha que se ignoraron @endif.
                De cada día, la primera marcación es la <strong>entrada</strong> y la última la <strong>salida</strong>
                (si pasaron al menos {{ $minutosSalida }} minutos entre ambas).
            </p>

            @if($analisis['desconocidos'])
                <div class="mb-4 text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3">
                    <p class="font-semibold mb-1"><i class="fas fa-triangle-exclamation mr-1"></i>Números del reloj sin empleado ({{ count($analisis['desconocidos']) }})</p>
                    <p>Estas marcaciones no se importarán hasta que cada persona tenga su <strong>código en el huellero</strong> en su ficha:
                        @foreach($analisis['desconocidos'] as $pin => $n)<span class="inline-block bg-white border border-amber-200 rounded px-2 py-0.5 mr-1">N.° {{ $pin }} ({{ $n }} marcaciones)</span>@endforeach</p>
                </div>
            @endif

            <div class="overflow-x-auto border border-gray-200 rounded-lg max-h-96">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0"><tr>
                        <th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Empleado</th><th class="px-3 py-2 text-left">Entrada</th><th class="px-3 py-2 text-left">Salida</th><th class="px-3 py-2 text-right">Marcaciones</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($analisis['dias'] as $d)
                            <tr>
                                <td class="px-3 py-1.5">{{ \Carbon\Carbon::parse($d->fecha)->isoFormat('ddd D/M/YYYY') }}</td>
                                <td class="px-3 py-1.5">{{ $d->empleado->nombre_completo }}</td>
                                <td class="px-3 py-1.5">{{ substr($d->entrada, 0, 5) }}</td>
                                <td class="px-3 py-1.5">{{ $d->salida ? substr($d->salida, 0, 5) : '—' }}</td>
                                <td class="px-3 py-1.5 text-right text-gray-500">{{ $d->marcaciones }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-gray-400">Ninguna marcación corresponde a un empleado con código de huellero.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-3 mt-4">
                <a href="{{ route('rrhh.asistencia.huellero') }}" class="px-4 py-2 text-sm text-gray-600 bg-gray-200 hover:bg-gray-300 rounded-lg">Cancelar</a>
                @if($analisis['dias']->isNotEmpty())
                    <form method="POST" action="{{ route('rrhh.asistencia.huellero.importar') }}">@csrf <input type="hidden" name="token" value="{{ $token }}">
                        <button class="px-5 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg"><i class="fas fa-check mr-1"></i>Importar {{ $analisis['dias']->count() }} día(s)</button></form>
                @endif
            </div>
        </section>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-3"><i class="fas fa-plug text-gray-400 mr-1"></i> Importar el archivo del reloj</h2>
                <ol class="text-sm text-gray-700 list-decimal pl-5 space-y-1 mb-4">
                    <li>En el reloj, inserte una memoria USB y descargue las marcaciones (menú <em>Administrador de USB → Descargar → Datos de asistencia</em>; el nombre exacto cambia según el modelo).</li>
                    <li>Lleve la memoria a una computadora y suba aquí el archivo (suele llamarse <code class="bg-gray-100 px-1 rounded">1_attlog.dat</code> o <code class="bg-gray-100 px-1 rounded">ATTLOG.TXT</code>).</li>
                    <li>Revise el resumen y confirme. Importar el mismo archivo dos veces no duplica nada.</li>
                </ol>
                <form method="POST" action="{{ route('rrhh.asistencia.huellero.revisar') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="file" name="archivo" required accept=".dat,.txt,.csv,.log" class="text-sm">
                    <button class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg"><i class="fas fa-magnifying-glass mr-1"></i>Leer y revisar</button>
                </form>
                @error('archivo')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
            </section>

            <section class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-link text-gray-400 mr-1"></i> Recepción automática (puente en la red del cliente)</h2>
                @if($apiActiva)
                    <p class="text-sm text-gray-700 mb-2"><span class="inline-flex items-center gap-1 text-green-700 font-semibold"><i class="fas fa-circle-check"></i> Activada.</span>
                        Un programa pequeño dentro de la red del cliente lee el reloj y envía las marcaciones nuevas a:</p>
                    <p class="text-sm"><code class="bg-gray-100 px-2 py-1 rounded break-all">POST {{ url('/api/huellero/marcaciones') }}</code></p>
                    <p class="text-xs text-gray-500 mt-2">Con el encabezado <code>Authorization: Bearer &lt;HUELLERO_TOKEN&gt;</code> y un JSON <code>{"marcaciones":[{"pin":"35","fecha_hora":"2026-11-17 08:31:00"}]}</code>. El script de ejemplo está en <code>deploy/huellero/</code>.</p>
                @else
                    <p class="text-sm text-gray-600">Desactivada. Para activarla, defina <code class="bg-gray-100 px-1 rounded">HUELLERO_TOKEN</code> (una clave larga y secreta) en el archivo <code class="bg-gray-100 px-1 rounded">.env</code> del servidor. El reloj está dentro de la red del cliente y este sistema en internet, por eso hace falta ese puente; la importación por USB funciona sin él.</p>
                @endif
            </section>
        </div>

        <section class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-semibold text-gray-900 mb-1"><i class="fas fa-id-badge text-gray-400 mr-1"></i> Quién es quién en el reloj</h2>
            <p class="text-xs text-gray-500 mb-3">Cada persona debe tener en su ficha el número con el que está registrada en el reloj.</p>
            <p class="text-sm font-medium text-gray-700">Con código ({{ $conCodigo->count() }})</p>
            <ul class="text-sm text-gray-600 mb-3 max-h-40 overflow-y-auto">
                @foreach($conCodigo as $e)<li class="flex justify-between py-0.5"><a href="{{ route('rrhh.empleados.edit', $e) }}" class="hover:text-blue-700">{{ $e->nombre_completo }}</a><span class="font-mono text-gray-500">{{ $e->codigo_huellero }}</span></li>@endforeach
            </ul>
            <p class="text-sm font-medium {{ $sinCodigo->isNotEmpty() ? 'text-amber-700' : 'text-gray-700' }}">Sin código ({{ $sinCodigo->count() }})</p>
            <ul class="text-sm text-gray-600 max-h-48 overflow-y-auto">
                @foreach($sinCodigo as $e)<li class="py-0.5"><a href="{{ route('rrhh.empleados.edit', $e) }}" class="hover:text-blue-700">{{ $e->nombre_completo }} <i class="fas fa-pen text-xs text-gray-300"></i></a></li>@endforeach
            </ul>
        </section>
    </div>
@endsection
