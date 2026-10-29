<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Series de Comprobantes</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
<x-sidebar :role="auth()->user()->role->nombre" />

<div class="md:ml-64 p-4 md:p-8" x-data="seriesPage()">
    <x-header title="Series de Comprobantes" subtitle="Gestión de series y correlativos por sucursal" />

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg flex items-center gap-2">
            <i class="fas fa-check-circle"></i><span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg flex items-center gap-2">
            <i class="fas fa-times-circle"></i><span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="{{ route('facturacion.index') }}" class="hover:text-blue-600 transition">Facturación Electrónica</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-800 font-medium">Series de Comprobantes</span>
    </div>

    {{-- Filtro + Nuevo --}}
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <h2 class="text-lg font-bold text-gray-800">Series Configuradas</h2>
            @if(auth()->user()->role->nombre === \App\Models\Role::ADMINISTRACION)
            <button @click="abrirCrear()"
                class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition">
                <i class="fas fa-plus"></i>Nueva Serie
            </button>
            @endif
        </div>
        <form method="GET" class="flex gap-3">
            <select name="sucursal_id" class="rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Todas las sucursales</option>
                @foreach($sucursales as $suc)
                    <option value="{{ $suc->id }}" {{ request('sucursal_id') == $suc->id ? 'selected' : '' }}>{{ $suc->nombre }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-blue-900 hover:bg-blue-800 text-white text-sm px-4 py-2 rounded-lg transition">
                <i class="fas fa-filter mr-1"></i>Filtrar
            </button>
        </form>
    </div>

    {{-- Tabla --}}
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sucursal</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Serie</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Correlativo</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Formato</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($series as $serie)
                @php
                    $tipoCodes = ['01'=>'bg-blue-100 text-blue-800','03'=>'bg-purple-100 text-purple-800','07'=>'bg-orange-100 text-orange-700','08'=>'bg-red-100 text-red-700','09'=>'bg-teal-100 text-teal-700','NE'=>'bg-gray-100 text-gray-600'];
                    $tipoCss = $tipoCodes[$serie->tipo_comprobante] ?? 'bg-gray-100 text-gray-600';
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium text-gray-900">{{ $serie->sucursal?->nombre ?? 'N/A' }}</td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $tipoCss }}">
                            {{ $serie->tipo_nombre }}
                        </span>
                    </td>
                    <td class="px-5 py-3 font-mono font-bold text-blue-700 text-base">{{ $serie->serie }}</td>
                    <td class="px-5 py-3 text-right font-mono text-gray-800">{{ str_pad($serie->correlativo_actual, 8, '0', STR_PAD_LEFT) }}</td>
                    <td class="px-5 py-3 text-gray-600">{{ $serie->formato_impresion }}</td>
                    <td class="px-5 py-3">
                        @if($serie->activo)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Activa
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactiva
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            @if(auth()->user()->role->nombre === \App\Models\Role::ADMINISTRACION)
                            <button @click="abrirEditar({{ $serie->id }}, '{{ addslashes($serie->tipo_nombre) }}', {{ $serie->correlativo_actual }}, '{{ $serie->formato_impresion }}', {{ $serie->activo ? 'true' : 'false' }}, '{{ $serie->serie }}')"
                                class="text-yellow-600 hover:text-yellow-800 transition" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('facturacion.series.destroy', $serie) }}" method="POST" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 transition" title="Eliminar"
                                        data-confirm="¿Eliminar la serie {{ $serie->serie }}?">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-list-ol text-4xl mb-3 block"></i>
                        <p class="font-medium">No hay series configuradas</p>
                        <button @click="abrirCrear()" class="text-blue-600 text-sm mt-1 inline-block hover:underline">
                            Crear la primera serie
                        </button>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Crear / Editar --}}
    <div x-show="modalAbierto" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         @keydown.escape.window="modalAbierto = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="text-lg font-bold text-gray-800" x-text="titulo"></h3>
                <button @click="modalAbierto = false" class="text-gray-400 hover:text-gray-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form :action="formAction" method="POST" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="formMethod">

                <template x-if="formMethod === 'POST'">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sucursal <span class="text-red-500">*</span></label>
                            <select name="sucursal_id" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Seleccionar...</option>
                                @foreach($sucursales as $suc)
                                    <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo <span class="text-red-500">*</span></label>
                            <select name="tipo_comprobante" x-model="form.tipo_comprobante" required
                                    @change="actualizarTipoNombre()"
                                    class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                @foreach($tiposComprobante as $codigo => $info)
                                    <option value="{{ $codigo }}">{{ $info['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Serie <span class="text-red-500">*</span></label>
                            <input type="text" name="serie" x-model="form.serie" required maxlength="5"
                                   class="w-full rounded-lg border-gray-300 shadow-sm text-sm font-mono uppercase focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Ej: FA01">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Correlativo inicial</label>
                            <input type="number" name="correlativo_actual" x-model="form.correlativo_actual" min="1" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </template>

                <template x-if="formMethod === 'PUT'">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        Editando serie: <strong x-text="form.serie"></strong>
                    </div>
                </template>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del tipo <span class="text-red-500">*</span></label>
                    <input type="text" name="tipo_nombre" x-model="form.tipo_nombre" required
                           class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="Ej: Factura Electrónica">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Formato de impresión</label>
                        <select name="formato_impresion" x-model="form.formato_impresion"
                                class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="A4">A4</option>
                            <option value="ticket">Ticket</option>
                            <option value="A5">A5</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                        <div class="flex items-center gap-2 mt-1">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" name="activo" id="serie_activa" value="1"
                                   x-model="form.activo"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <label for="serie_activa" class="text-sm text-gray-700">Serie activa</label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="modalAbierto = false"
                            class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-5 py-2 text-sm bg-blue-900 hover:bg-blue-800 text-white rounded-lg font-medium transition">
                        <i class="fas fa-save mr-1"></i>
                        <span x-text="btnTexto"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const tiposComprobante = @json($tiposComprobante);

function seriesPage() {
    return {
        modalAbierto: false,
        titulo: '',
        btnTexto: '',
        formAction: '',
        formMethod: 'POST',
        form: {
            serie: '',
            tipo_comprobante: '01',
            tipo_nombre: 'Factura Electrónica',
            correlativo_actual: 1,
            formato_impresion: 'A4',
            activo: true
        },

        abrirCrear() {
            this.titulo = 'Nueva Serie de Comprobante';
            this.btnTexto = 'Crear Serie';
            this.formAction = '{{ route('facturacion.series.store') }}';
            this.formMethod = 'POST';
            this.form = { serie: '', tipo_comprobante: '01', tipo_nombre: 'Factura Electrónica', correlativo_actual: 1, formato_impresion: 'A4', activo: true };
            this.modalAbierto = true;
        },

        abrirEditar(id, tipo_nombre, correlativo_actual, formato_impresion, activo, serie) {
            this.titulo = 'Editar Serie ' + serie;
            this.btnTexto = 'Guardar Cambios';
            this.formAction = `/facturacion/series/${id}`;
            this.formMethod = 'PUT';
            this.form = { serie, tipo_nombre, correlativo_actual, formato_impresion, activo };
            this.modalAbierto = true;
        },

        actualizarTipoNombre() {
            const info = tiposComprobante[this.form.tipo_comprobante];
            if (info) this.form.tipo_nombre = info.nombre;
        }
    }
}
</script>
</body>
</html>
