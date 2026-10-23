{{-- resources/views/catalogo/motivos/edit.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Motivo - Catálogo</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <div class="mb-4">
            <a href="{{ route('catalogo.motivos.index') }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 w-fit">
                <i class="fas fa-arrow-left text-xs"></i> Volver a Motivos
            </a>
        </div>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-4">
                    <h1 class="text-xl font-bold text-white">Editar Motivo</h1>
                    <p class="text-blue-200 text-sm">Modificar motivo de movimiento de inventario</p>
                </div>

                <form action="{{ route('catalogo.motivos.update', $motivo) }}" method="POST" class="p-6 space-y-5">
                    @csrf
                    @method('PUT')

                    {{-- Tipo (radio cards) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo de Movimiento <span class="text-red-500">*</span>
                        </label>
                        @php $tipoOld = old('tipo', $motivo->tipo); @endphp
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                            @php
                                $tipos = [
                                    'ingreso'       => ['icono' => 'fa-arrow-down',     'label' => 'Ingreso',       'color' => 'peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700'],
                                    'salida'        => ['icono' => 'fa-arrow-up',        'label' => 'Salida',        'color' => 'peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700'],
                                    'transferencia' => ['icono' => 'fa-exchange-alt',    'label' => 'Transferencia', 'color' => 'peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-700'],
                                    'ajuste'        => ['icono' => 'fa-sliders-h',       'label' => 'Ajuste',        'color' => 'peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-700'],
                                    'otros'         => ['icono' => 'fa-ellipsis-h',      'label' => 'Otros',         'color' => 'peer-checked:border-gray-400 peer-checked:bg-gray-50 peer-checked:text-gray-700'],
                                ];
                            @endphp
                            @foreach($tipos as $valor => $info)
                                <label class="cursor-pointer">
                                    <input type="radio" name="tipo" value="{{ $valor }}"
                                           {{ $tipoOld == $valor ? 'checked' : '' }}
                                           class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center p-3 rounded-xl border-2 border-gray-200
                                                {{ $info['color'] }} hover:border-gray-300 transition text-gray-500 text-center">
                                        <i class="fas {{ $info['icono'] }} text-xl mb-1"></i>
                                        <span class="text-xs font-medium">{{ $info['label'] }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('tipo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Código --}}
                    <div>
                        <label for="codigo" class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                        <div class="flex gap-2">
                            <input type="text" name="codigo" id="codigo"
                                   value="{{ old('codigo', $motivo->codigo) }}"
                                   placeholder="Ej: ING-001, SAL-002"
                                   class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <button type="button" onclick="sugerirCodigo()"
                                    class="px-3 py-2 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg text-xs text-gray-600 transition whitespace-nowrap">
                                <i class="fas fa-magic mr-1"></i>Sugerir
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Código de referencia interno (opcional)</p>
                        @error('codigo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Nombre --}}
                    <div>
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nombre" id="nombre"
                               value="{{ old('nombre', $motivo->nombre) }}" required
                               placeholder="Ej: Compra de mercadería, Venta al cliente..."
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        @error('nombre')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Descripción --}}
                    <div>
                        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                        <textarea name="descripcion" id="descripcion" rows="2"
                                  placeholder="Descripción del motivo de movimiento..."
                                  class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">{{ old('descripcion', $motivo->descripcion) }}</textarea>
                    </div>

                    {{-- Comportamiento --}}
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 space-y-3">
                        <h3 class="text-sm font-semibold text-gray-700">Comportamiento</h3>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="afecta_stock" value="0">
                            <input type="checkbox" name="afecta_stock" id="afecta_stock" value="1"
                                   {{ old('afecta_stock', $motivo->afecta_stock) ? 'checked' : '' }}
                                   class="mt-0.5 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Afecta stock</span>
                                <p class="text-xs text-gray-400">Activa si este motivo modifica las cantidades de inventario</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="hidden" name="requiere_aprobacion" value="0">
                            <input type="checkbox" name="requiere_aprobacion" id="requiere_aprobacion" value="1"
                                   {{ old('requiere_aprobacion', $motivo->requiere_aprobacion) ? 'checked' : '' }}
                                   class="mt-0.5 w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-400">
                            <div>
                                <span class="text-sm font-medium text-gray-700">Requiere aprobación</span>
                                <p class="text-xs text-gray-400">Activa si los movimientos con este motivo deben ser aprobados por un supervisor</p>
                            </div>
                        </label>
                    </div>

                    {{-- Estado --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="estado" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="activo"   {{ old('estado', $motivo->estado) == 'activo'   ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ old('estado', $motivo->estado) == 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <a href="{{ route('catalogo.motivos.index') }}"
                           class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm">
                            Cancelar
                        </a>
                        <button type="submit" class="px-5 py-2 bg-blue-900 hover:bg-blue-800 text-white rounded-lg text-sm font-medium transition">
                            <i class="fas fa-save mr-2"></i>Actualizar Motivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const prefijos = { ingreso: 'ING', salida: 'SAL', transferencia: 'TRF', ajuste: 'AJU', otros: 'OTR' };
        function sugerirCodigo() {
            const sel  = document.querySelector('input[name="tipo"]:checked');
            const pref = prefijos[sel ? sel.value : 'otros'] || 'OTR';
            const num  = String(Math.floor(Math.random() * 900) + 100);
            document.getElementById('codigo').value = pref + '-' + num;
        }
    </script>
</body>
</html>
