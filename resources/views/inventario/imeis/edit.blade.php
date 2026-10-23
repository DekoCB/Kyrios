<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar IMEI - {{ $imei->codigo_imei }} - {{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    @include('partials.tema-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <x-sidebar :role="auth()->user()->role->nombre" />

    <div class="md:ml-64 p-4 md:p-8">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Editar IMEI</h1>
                    <p class="text-sm text-gray-600 mt-1">Modificando información del equipo {{ $imei->codigo_imei }}</p>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('inventario.imeis.show', $imei) }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Volver
                    </a>
                    <a href="{{ route('inventario.imeis.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-list mr-2"></i>Listado
                    </a>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-300 rounded-lg p-4">
                <p class="text-sm font-semibold text-red-700 mb-2">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    Por favor corrige los siguientes errores:
                </p>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li class="text-sm text-red-600">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-edit mr-3"></i>
                        Modificar Información del IMEI
                    </h2>
                </div>

                <form action="{{ route('inventario.imeis.update', $imei) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <!-- Información fija del equipo -->
                    <div class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h3 class="text-sm font-semibold text-blue-900 mb-3 flex items-center">
                            <i class="fas fa-lock mr-2"></i>
                            Información fija del equipo
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Código IMEI</p>
                                <p class="font-mono font-bold text-gray-900">{{ $imei->codigo_imei }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Producto</p>
                                <p class="font-semibold text-gray-900">{{ $imei->producto->nombre }}</p>
                                <p class="text-xs text-gray-400">{{ $imei->producto->codigo }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Variante actual</p>
                                @if($imei->variante)
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        @if($imei->variante->color?->codigo_hex)
                                            <span class="w-3 h-3 rounded-full border border-gray-300 shrink-0"
                                                  style="background:{{ $imei->variante->color->codigo_hex }}"></span>
                                        @endif
                                        <span class="font-semibold text-gray-900 text-sm">
                                            {{ trim(($imei->variante->color?->nombre ?? '') . ($imei->variante->capacidad ? ' / ' . $imei->variante->capacidad : '')) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-400 font-mono">{{ $imei->variante->sku }}</p>
                                @else
                                    <p class="text-sm text-gray-400 italic">Sin variante asignada</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Fecha de ingreso</p>
                                <p class="text-gray-900">{{ $imei->fecha_ingreso ? $imei->fecha_ingreso->format('d/m/Y') : $imei->created_at->format('d/m/Y') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Campos editables -->
                    <div class="space-y-6">

                        <!-- Selector de variante -->
                        @php $variantes = $variantesPorProducto[$imei->producto_id] ?? collect(); @endphp
                        @if($variantes->count() > 0)
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-layer-group mr-1 text-indigo-500"></i>
                                Variante (color + capacidad)
                            </label>
                            <input type="hidden" name="variante_id" id="variante_id" value="{{ old('variante_id', $imei->variante_id) }}">
                            <input type="hidden" name="color_id" id="color_id" value="{{ old('color_id', $imei->color_id) }}">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2" id="variantesGrid">
                                @foreach($variantes as $v)
                                    @php $seleccionada = old('variante_id', $imei->variante_id) == $v['id']; @endphp
                                    <button type="button" data-id="{{ $v['id'] }}" data-color-id="{{ $v['color_id'] }}"
                                            data-color-nombre="{{ $v['color_nombre'] }}" data-capacidad="{{ $v['capacidad'] }}"
                                            class="variante-card text-left border-2 rounded-xl p-3 transition
                                                   {{ $seleccionada ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-400 hover:bg-indigo-50' }}">
                                        <div class="flex items-center gap-2 mb-1">
                                            @if($v['color_hex'])
                                                <span class="w-4 h-4 rounded-full shrink-0 border border-gray-300"
                                                      style="background:{{ $v['color_hex'] }}"></span>
                                            @endif
                                            <span class="text-sm font-semibold text-gray-800 truncate">{{ $v['nombre'] }}</span>
                                        </div>
                                        <span class="text-xs font-mono text-gray-400">{{ $v['sku'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                            @error('variante_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        @else
                            <input type="hidden" name="variante_id" value="{{ $imei->variante_id }}">
                            <input type="hidden" name="color_id" value="{{ $imei->color_id }}">
                        @endif

                        <!-- Almacén y Serie -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="almacen_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-warehouse mr-1 text-gray-500"></i>
                                    Almacén <span class="text-red-500">*</span>
                                </label>
                                <select name="almacen_id" id="almacen_id"
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                        required>
                                    <option value="">Seleccione un almacén</option>
                                    @foreach($almacenes as $almacen)
                                        <option value="{{ $almacen->id }}"
                                                {{ old('almacen_id', $imei->almacen_id) == $almacen->id ? 'selected' : '' }}>
                                            {{ $almacen->nombre }}
                                            @if($almacen->ubicacion) ({{ $almacen->ubicacion }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('almacen_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="serie" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-barcode mr-1 text-gray-500"></i>
                                    Número de Serie
                                </label>
                                <input type="text" name="serie" id="serie"
                                       value="{{ old('serie', $imei->serie) }}"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                       placeholder="Número de serie adicional (opcional)">
                                @error('serie')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-tag mr-1 text-gray-500"></i>
                                Estado del equipo <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="estado_imei" value="en_stock" 
                                           class="hidden peer" 
                                           {{ old('estado_imei', $imei->estado_imei) == 'en_stock' ? 'checked' : '' }} required>
                                    <div class="border-2 border-gray-300 rounded-lg p-3 text-center peer-checked:border-green-500 peer-checked:bg-green-50 hover:bg-gray-50 transition-all">
                                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                                        <span class="block text-xs font-medium mt-1">En Stock</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="estado_imei" value="reservado" 
                                           class="hidden peer" 
                                           {{ old('estado_imei', $imei->estado_imei) == 'reservado' ? 'checked' : '' }}>
                                    <div class="border-2 border-gray-300 rounded-lg p-3 text-center peer-checked:border-yellow-500 peer-checked:bg-yellow-50 hover:bg-gray-50 transition-all">
                                        <i class="fas fa-clock text-yellow-500 text-xl"></i>
                                        <span class="block text-xs font-medium mt-1">Reservado</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="estado_imei" value="vendido" 
                                           class="hidden peer" 
                                           {{ old('estado_imei', $imei->estado_imei) == 'vendido' ? 'checked' : '' }}>
                                    <div class="border-2 border-gray-300 rounded-lg p-3 text-center peer-checked:border-red-500 peer-checked:bg-red-50 hover:bg-gray-50 transition-all">
                                        <i class="fas fa-shopping-cart text-red-500 text-xl"></i>
                                        <span class="block text-xs font-medium mt-1">Vendido</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="estado_imei" value="garantia" 
                                           class="hidden peer" 
                                           {{ old('estado_imei', $imei->estado_imei) == 'garantia' ? 'checked' : '' }}>
                                    <div class="border-2 border-gray-300 rounded-lg p-3 text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:bg-gray-50 transition-all">
                                        <i class="fas fa-shield-alt text-blue-500 text-xl"></i>
                                        <span class="block text-xs font-medium mt-1">Garantía</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="estado_imei" value="devuelto" 
                                           class="hidden peer" 
                                           {{ old('estado_imei', $imei->estado_imei) == 'devuelto' ? 'checked' : '' }}>
                                    <div class="border-2 border-gray-300 rounded-lg p-3 text-center peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:bg-gray-50 transition-all">
                                        <i class="fas fa-undo text-orange-500 text-xl"></i>
                                        <span class="block text-xs font-medium mt-1">Devuelto</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="estado_imei" value="reemplazado" 
                                           class="hidden peer" 
                                           {{ old('estado_imei', $imei->estado_imei) == 'reemplazado' ? 'checked' : '' }}>
                                    <div class="border-2 border-gray-300 rounded-lg p-3 text-center peer-checked:border-purple-500 peer-checked:bg-purple-50 hover:bg-gray-50 transition-all">
                                        <i class="fas fa-exchange-alt text-purple-500 text-xl"></i>
                                        <span class="block text-xs font-medium mt-1">Reemplazado</span>
                                    </div>
                                </label>
                            </div>
                            @error('estado_imei')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Fecha de Garantía (se muestra solo si estado es garantía o vendido) -->
                        <div id="fechaGarantiaContainer" class="{{ in_array(old('estado_imei', $imei->estado_imei), ['garantia', 'vendido']) ? '' : 'hidden' }}">
                            <label for="fecha_garantia" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt mr-1 text-gray-500"></i>
                                Fecha de vencimiento de garantía
                            </label>
                            <input type="date" 
                                   name="fecha_garantia" 
                                   id="fecha_garantia" 
                                   value="{{ old('fecha_garantia', $imei->fecha_garantia ? $imei->fecha_garantia->format('Y-m-d') : '') }}"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Fecha en que expira la garantía del equipo
                            </p>
                        </div>

                        <!-- Observaciones -->
                        <div>
                            <label for="observaciones" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-sticky-note mr-1 text-gray-500"></i>
                                Observaciones
                            </label>
                            <textarea name="observaciones" id="observaciones" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                      placeholder="Notas adicionales sobre el equipo...">{{ old('observaciones', $imei->observaciones) }}</textarea>
                            @error('observaciones')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Alerta de cambios en stock -->
                    <div class="mt-6 bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mt-0.5 mr-3"></i>
                            <div class="text-sm text-yellow-800">
                                <p class="font-medium">¡Importante!</p>
                                <p class="mt-1">Si cambias el estado, el stock se actualizará automáticamente:</p>
                                <ul class="list-disc list-inside mt-2 space-y-1 text-xs">
                                    <li><strong>En Stock:</strong> El equipo estará disponible para venta</li>
                                    <li><strong>Vendido:</strong> Se descontará del stock disponible</li>
                                    <li><strong>Devuelto:</strong> Se agregará nuevamente al stock</li>
                                    <li><strong>Garantía:</strong> El equipo no está disponible, pero cuenta con garantía</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                        <a href="{{ route('inventario.imeis.show', $imei) }}"
                           class="px-6 py-3 border-2 border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </a>
                        <button type="submit" 
                                class="px-8 py-3 bg-gradient-to-r from-blue-900 to-blue-700 text-white rounded-lg hover:from-blue-800 hover:to-blue-600 font-medium shadow-lg hover:shadow-xl transition-all">
                            <i class="fas fa-save mr-2"></i>Actualizar IMEI
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle garantía
            const radios = document.querySelectorAll('input[name="estado_imei"]');
            const garantiaContainer = document.getElementById('fechaGarantiaContainer');
            function toggleGarantiaField() {
                const sel = document.querySelector('input[name="estado_imei"]:checked');
                garantiaContainer.classList.toggle('hidden', !(sel && ['garantia','vendido'].includes(sel.value)));
            }
            radios.forEach(r => r.addEventListener('change', toggleGarantiaField));
            toggleGarantiaField();

            // Selector de variante
            document.querySelectorAll('.variante-card').forEach(function(card) {
                card.addEventListener('click', function() {
                    document.querySelectorAll('.variante-card').forEach(function(c) {
                        c.classList.remove('border-indigo-500', 'bg-indigo-50');
                        c.classList.add('border-gray-200');
                    });
                    this.classList.remove('border-gray-200');
                    this.classList.add('border-indigo-500', 'bg-indigo-50');
                    document.getElementById('variante_id').value = this.dataset.id;
                    document.getElementById('color_id').value = this.dataset.colorId || '';
                });
            });
        });
    </script>
</body>
</html>