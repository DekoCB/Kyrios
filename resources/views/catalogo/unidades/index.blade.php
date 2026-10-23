{{-- resources/views/catalogo/unidades/index.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unidades de Medida - Catálogo</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8" x-data="unidadesPage()">
        <x-header title="Unidades de Medida" subtitle="Gestión de unidades de medida del catálogo" />

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
            $total     = \App\Models\Catalogo\UnidadMedida::count();
            $activos   = \App\Models\Catalogo\UnidadMedida::where('estado', 'activo')->count();
            $inactivos = \App\Models\Catalogo\UnidadMedida::where('estado', 'inactivo')->count();
        @endphp

        {{-- Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-blue-500 p-4 flex justify-between items-center">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-medium">Total Unidades</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $total }}</p>
                </div>
                <div class="bg-blue-100 p-3 rounded-full"><i class="fas fa-ruler text-blue-600 text-xl"></i></div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-green-500 p-4 flex justify-between items-center">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-medium">Activas</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $activos }}</p>
                </div>
                <div class="bg-green-100 p-3 rounded-full"><i class="fas fa-check-circle text-green-600 text-xl"></i></div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-400 p-4 flex justify-between items-center">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-medium">Inactivas</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $inactivos }}</p>
                </div>
                <div class="bg-red-100 p-3 rounded-full"><i class="fas fa-times-circle text-red-400 text-xl"></i></div>
            </div>
        </div>

        {{-- Filtros + Botón --}}
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                <h2 class="text-lg font-bold text-gray-800">Lista de Unidades</h2>
                <button @click="abrirCrear()"
                    class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition">
                    <i class="fas fa-plus"></i>Nueva Unidad
                </button>
            </div>
            <form method="GET" action="{{ route('catalogo.unidades.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                       placeholder="Buscar por nombre o abreviatura..."
                       class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                <select name="estado" class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Todos los estados</option>
                    <option value="activo"   {{ request('estado') == 'activo'   ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ request('estado') == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-900 hover:bg-blue-800 text-white text-sm px-3 py-2 rounded-lg transition">
                        <i class="fas fa-search mr-1"></i>Filtrar
                    </button>
                    @if(request()->hasAny(['buscar','estado']))
                        <a href="{{ route('catalogo.unidades.index') }}"
                           class="flex-1 text-center text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-600 hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-1"></i>Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Tabla --}}
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Abreviatura</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Decimales</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($unidades as $unidad)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $unidad->nombre }}</td>
                        <td class="px-6 py-4 font-mono text-sm font-bold text-blue-700">{{ $unidad->abreviatura }}</td>
                        <td class="px-6 py-4">
                            @php
                                $tipoColors = [
                                    'unidad'   => 'bg-blue-100 text-blue-700',
                                    'peso'     => 'bg-amber-100 text-amber-700',
                                    'volumen'  => 'bg-cyan-100 text-cyan-700',
                                    'longitud' => 'bg-purple-100 text-purple-700',
                                    'otros'    => 'bg-gray-100 text-gray-600',
                                ];
                                $colorBadge = $tipoColors[$unidad->categoria] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $colorBadge }}">
                                {{ ucfirst($unidad->categoria) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($unidad->permite_decimales)
                                <span class="inline-flex items-center gap-1 text-green-600 text-sm"><i class="fas fa-check-circle"></i> Sí</span>
                            @else
                                <span class="inline-flex items-center gap-1 text-gray-400 text-sm"><i class="fas fa-times-circle"></i> No</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                {{ $unidad->estado == 'activo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $unidad->estado == 'activo' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                {{ ucfirst($unidad->estado) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <button @click="abrirEditar(
                                        {{ $unidad->id }},
                                        '{{ addslashes($unidad->nombre) }}',
                                        '{{ addslashes($unidad->abreviatura) }}',
                                        '{{ $unidad->categoria }}',
                                        {{ $unidad->permite_decimales ? 'true' : 'false' }},
                                        '{{ $unidad->estado }}'
                                    )"
                                    class="text-yellow-600 hover:text-yellow-800 transition" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('catalogo.unidades.destroy', $unidad) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 transition" title="Eliminar"
                                            data-confirm="¿Eliminar la unidad «{{ $unidad->nombre }}»?">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <i class="fas fa-ruler text-4xl mb-3 block"></i>
                            <p class="font-medium">No se encontraron unidades de medida</p>
                            <button @click="abrirCrear()" class="text-blue-600 text-sm mt-1 inline-block hover:underline">
                                Crear la primera unidad
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $unidades->withQueryString()->links() }}</div>

        {{-- Modal --}}
        <div x-show="modalAbierto" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             @keydown.escape.window="modalAbierto = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                            <input type="text" name="nombre" x-model="form.nombre" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Ej: Kilogramo">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Abreviatura <span class="text-red-500">*</span></label>
                            <input type="text" name="abreviatura" x-model="form.abreviatura" required
                                   class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Ej: kg" maxlength="20">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categoría <span class="text-red-500">*</span></label>
                        <select name="categoria" x-model="form.categoria" required
                                class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="unidad">Unidad</option>
                            <option value="peso">Peso</option>
                            <option value="volumen">Volumen</option>
                            <option value="longitud">Longitud</option>
                            <option value="otros">Otros</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="permite_decimales" value="0">
                        <input type="checkbox" name="permite_decimales" id="permite_dec" value="1"
                               x-model="form.permite_decimales"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <label for="permite_dec" class="text-sm text-gray-700">Permite decimales</label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="estado" x-model="form.estado"
                                class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
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
    function unidadesPage() {
        return {
            modalAbierto: false,
            titulo: '',
            btnTexto: '',
            formAction: '',
            formMethod: 'POST',
            form: { nombre: '', abreviatura: '', categoria: 'unidad', permite_decimales: false, estado: 'activo' },

            abrirCrear() {
                this.titulo = 'Nueva Unidad de Medida';
                this.btnTexto = 'Guardar Unidad';
                this.formAction = '{{ route('catalogo.unidades.store') }}';
                this.formMethod = 'POST';
                this.form = { nombre: '', abreviatura: '', categoria: 'unidad', permite_decimales: false, estado: 'activo' };
                this.modalAbierto = true;
            },

            abrirEditar(id, nombre, abreviatura, categoria, permite_decimales, estado) {
                this.titulo = 'Editar Unidad de Medida';
                this.btnTexto = 'Actualizar Unidad';
                this.formAction = `/catalogo/unidades/${id}`;
                this.formMethod = 'PUT';
                this.form = { nombre, abreviatura, categoria, permite_decimales, estado };
                this.modalAbierto = true;
            }
        }
    }
    </script>
</body>
</html>
