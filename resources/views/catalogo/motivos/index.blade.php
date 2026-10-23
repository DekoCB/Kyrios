{{-- resources/views/catalogo/motivos/index.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motivos de Movimiento - Catálogo</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8" x-data="motivosPage()">
        <x-header title="Motivos de Movimiento" subtitle="Gestión de motivos para movimientos de inventario" />

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

        @php
            $total     = \App\Models\Catalogo\MotivoMovimiento::count();
            $activos   = \App\Models\Catalogo\MotivoMovimiento::where('estado', 'activo')->count();
            $inactivos = \App\Models\Catalogo\MotivoMovimiento::where('estado', 'inactivo')->count();
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-4 flex justify-between items-center">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-medium">Total Motivos</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $total }}</p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full"><i class="fas fa-exchange-alt text-blue-600 text-xl"></i></div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-green-500 p-4 flex justify-between items-center">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-medium">Activos</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $activos }}</p>
                </div>
                <div class="bg-green-100 p-3 rounded-full"><i class="fas fa-check-circle text-green-600 text-xl"></i></div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-400 p-4 flex justify-between items-center">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-medium">Inactivos</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $inactivos }}</p>
                </div>
                <div class="bg-red-100 p-3 rounded-full"><i class="fas fa-times-circle text-red-400 text-xl"></i></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                <h2 class="text-lg font-bold text-gray-800">Lista de Motivos</h2>
                <button @click="abrirCrear()"
                    class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition">
                    <i class="fas fa-plus"></i>Nuevo Motivo
                </button>
            </div>
            <form method="GET" action="{{ route('catalogo.motivos.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                       placeholder="Buscar por nombre..."
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <select name="tipo" class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todos los tipos</option>
                    <option value="ingreso"       {{ request('tipo') == 'ingreso'       ? 'selected' : '' }}>Ingreso</option>
                    <option value="salida"        {{ request('tipo') == 'salida'        ? 'selected' : '' }}>Salida</option>
                    <option value="transferencia" {{ request('tipo') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                    <option value="ajuste"        {{ request('tipo') == 'ajuste'        ? 'selected' : '' }}>Ajuste</option>
                    <option value="otros"         {{ request('tipo') == 'otros'         ? 'selected' : '' }}>Otros</option>
                </select>
                <select name="estado" class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todos los estados</option>
                    <option value="activo"   {{ request('estado') == 'activo'   ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ request('estado') == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-900 hover:bg-blue-800 text-white text-sm px-3 py-2 rounded-lg transition">
                        <i class="fas fa-search mr-1"></i>Filtrar
                    </button>
                    @if(request()->hasAny(['buscar','tipo','estado']))
                        <a href="{{ route('catalogo.motivos.index') }}"
                           class="flex-1 text-center text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-600 hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-1"></i>Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Afecta Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aprobación</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($motivos as $motivo)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $motivo->nombre }}</td>
                        <td class="px-6 py-4">
                            @php
                                $tipoColors = [
                                    'ingreso'       => 'bg-green-100 text-green-700',
                                    'salida'        => 'bg-red-100 text-red-700',
                                    'transferencia' => 'bg-blue-100 text-blue-700',
                                    'ajuste'        => 'bg-amber-100 text-amber-700',
                                    'otros'         => 'bg-gray-100 text-gray-600',
                                ];
                                $tipoCss = $tipoColors[$motivo->tipo] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $tipoCss }}">
                                {{ ucfirst($motivo->tipo) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($motivo->afecta_stock)
                                <span class="inline-flex items-center gap-1 text-green-600 text-sm"><i class="fas fa-check-circle"></i> Sí</span>
                            @else
                                <span class="inline-flex items-center gap-1 text-gray-400 text-sm"><i class="fas fa-times-circle"></i> No</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($motivo->requiere_aprobacion)
                                <span class="inline-flex items-center gap-1 text-amber-600 text-sm"><i class="fas fa-shield-alt"></i> Requerida</span>
                            @else
                                <span class="inline-flex items-center gap-1 text-gray-400 text-sm"><i class="fas fa-times-circle"></i> No</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                {{ $motivo->estado == 'activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $motivo->estado == 'activo' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                {{ ucfirst($motivo->estado) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <button @click="abrirEditar(
                                        {{ $motivo->id }},
                                        '{{ addslashes($motivo->nombre) }}',
                                        '{{ $motivo->tipo }}',
                                        '{{ addslashes($motivo->descripcion ?? '') }}',
                                        {{ $motivo->afecta_stock ? 'true' : 'false' }},
                                        {{ $motivo->requiere_aprobacion ? 'true' : 'false' }},
                                        '{{ $motivo->estado }}'
                                    )"
                                    class="text-yellow-600 hover:text-yellow-800 transition" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('catalogo.motivos.destroy', $motivo) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 transition" title="Eliminar"
                                            data-confirm="¿Eliminar el motivo «{{ $motivo->nombre }}»?">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <i class="fas fa-exchange-alt text-4xl mb-3 block"></i>
                            <p class="font-medium">No se encontraron motivos de movimiento</p>
                            <button @click="abrirCrear()" class="text-blue-600 text-sm mt-1 inline-block hover:underline">
                                Crear el primer motivo
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $motivos->withQueryString()->links() }}</div>

        {{-- Modal --}}
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

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                            <input type="text" name="nombre" x-model="form.nombre" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Ej: Compra directa, Pérdida...">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo <span class="text-red-500">*</span></label>
                            <select name="tipo" x-model="form.tipo" required
                                    class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="ingreso">Ingreso</option>
                                <option value="salida">Salida</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="ajuste">Ajuste</option>
                                <option value="otros">Otros</option>
                            </select>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                            <textarea name="descripcion" x-model="form.descripcion" rows="2"
                                      class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                      placeholder="Descripción opcional..."></textarea>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="hidden" name="afecta_stock" value="0">
                            <input type="checkbox" name="afecta_stock" id="afecta_stock" value="1"
                                   x-model="form.afecta_stock"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <label for="afecta_stock" class="text-sm text-gray-700">Afecta stock</label>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="hidden" name="requiere_aprobacion" value="0">
                            <input type="checkbox" name="requiere_aprobacion" id="req_aprobacion" value="1"
                                   x-model="form.requiere_aprobacion"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <label for="req_aprobacion" class="text-sm text-gray-700">Requiere aprobación</label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select name="estado" x-model="form.estado"
                                    class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
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
    function motivosPage() {
        return {
            modalAbierto: false,
            titulo: '',
            btnTexto: '',
            formAction: '',
            formMethod: 'POST',
            form: { nombre: '', tipo: 'ingreso', descripcion: '', afecta_stock: true, requiere_aprobacion: false, estado: 'activo' },

            abrirCrear() {
                this.titulo = 'Nuevo Motivo de Movimiento';
                this.btnTexto = 'Guardar Motivo';
                this.formAction = '{{ route('catalogo.motivos.store') }}';
                this.formMethod = 'POST';
                this.form = { nombre: '', tipo: 'ingreso', descripcion: '', afecta_stock: true, requiere_aprobacion: false, estado: 'activo' };
                this.modalAbierto = true;
            },

            abrirEditar(id, nombre, tipo, descripcion, afecta_stock, requiere_aprobacion, estado) {
                this.titulo = 'Editar Motivo de Movimiento';
                this.btnTexto = 'Actualizar Motivo';
                this.formAction = `/catalogo/motivos/${id}`;
                this.formMethod = 'PUT';
                this.form = { nombre, tipo, descripcion, afecta_stock, requiere_aprobacion, estado };
                this.modalAbierto = true;
            }
        }
    }
    </script>
</body>
</html>
