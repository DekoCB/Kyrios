<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Código</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Nombre</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Sucursal</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tipo</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Personal asignado</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
            @foreach($items as $almacen)
            @php
                $tipoBadge = match($almacen->tipo) {
                    'principal' => ['bg-purple-100 text-purple-800', 'fa-star',      'Principal'],
                    'tienda'    => ['bg-orange-100 text-orange-800', 'fa-store',     'Tienda'],
                    'deposito'  => ['bg-teal-100 text-teal-800',    'fa-boxes',     'Depósito'],
                    'temporal'  => ['bg-gray-100 text-gray-700',    'fa-clock',     'Temporal'],
                    default     => ['bg-gray-100 text-gray-600',    'fa-warehouse', ucfirst($almacen->tipo)],
                };
                $roleBadge = [
                    'Administrador' => 'bg-purple-100 text-purple-700',
                    'Almacenero'    => 'bg-blue-100 text-blue-700',
                    'Cajero'        => 'bg-orange-100 text-orange-700',
                    'Proveedor'     => 'bg-teal-100 text-teal-700',
                    'Tienda'        => 'bg-emerald-100 text-emerald-700',
                    'Vendedor'      => 'bg-amber-100 text-amber-700',
                ];
                // Combinar encargado + trabajadores sin duplicar
                $personal = $almacen->trabajadores->keyBy('id');
                if ($almacen->encargado && !$personal->has($almacen->encargado_id)) {
                    $personal->put($almacen->encargado_id, $almacen->encargado);
                }
            @endphp
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-5 py-4 whitespace-nowrap">
                    <span class="text-xs font-mono font-semibold text-gray-600 bg-gray-100 px-2 py-0.5 rounded">{{ $almacen->codigo }}</span>
                </td>
                <td class="px-5 py-4">
                    <p class="text-sm font-semibold text-gray-900">{{ $almacen->nombre }}</p>
                    @if($almacen->telefono)
                        <p class="text-xs text-gray-400 mt-0.5"><i class="fas fa-phone mr-1"></i>{{ $almacen->telefono }}</p>
                    @endif
                </td>
                <td class="px-5 py-4 whitespace-nowrap hidden md:table-cell">
                    @if($almacen->sucursal)
                        <span class="inline-flex items-center gap-1 text-sm text-blue-700 font-medium">
                            <i class="fas fa-store text-blue-400 text-xs"></i>{{ $almacen->sucursal->nombre }}
                        </span>
                    @else
                        <span class="text-sm text-gray-400 italic">Sin sucursal</span>
                    @endif
                </td>
                <td class="px-5 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $tipoBadge[0] }}">
                        <i class="fas {{ $tipoBadge[1] }} text-[9px]"></i>{{ $tipoBadge[2] }}
                    </span>
                </td>
                <td class="px-5 py-4">
                    @if($personal->isEmpty())
                        <span class="text-xs text-gray-400 italic">Sin personal</span>
                    @else
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($personal as $persona)
                            @php
                                $rolNombre = $persona->role?->nombre ?? '';
                                $rc = $roleBadge[$rolNombre] ?? 'bg-gray-100 text-gray-600';
                                $esEncargado = $persona->id === $almacen->encargado_id;
                            @endphp
                            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $rc }} {{ $esEncargado ? 'ring-1 ring-offset-1 ring-current' : '' }}"
                                 title="{{ $persona->name }} — {{ $rolNombre }}{{ $esEncargado ? ' (Encargado)' : '' }}">
                                @if($esEncargado)
                                    <i class="fas fa-star text-[8px]"></i>
                                @endif
                                {{ explode(' ', $persona->name)[0] }}
                                <span class="opacity-60 text-[10px]">({{ $rolNombre }})</span>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td class="px-5 py-4 whitespace-nowrap text-center">
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold
                        {{ $almacen->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $almacen->estado === 'activo' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                        {{ ucfirst($almacen->estado) }}
                    </span>
                </td>
                <td class="px-5 py-4 whitespace-nowrap text-center">
                    <div class="inline-flex items-center gap-1">
                        <a href="{{ route('inventario.almacenes.show', $almacen) }}"
                            class="p-2 rounded-lg text-purple-500 hover:bg-purple-100 transition-colors" title="Ver detalle">
                            <i class="fas fa-eye text-sm"></i>
                        </a>
                        @if($canEdit)
                            <button onclick="openAlmacenEdit({{ $almacen->id }})"
                                class="p-2 rounded-lg text-blue-500 hover:bg-blue-100 transition-colors" title="Editar">
                                <i class="fas fa-pen text-sm"></i>
                            </button>
                        @endif
                        @if($canDelete)
                            <form action="{{ route('inventario.almacenes.destroy', $almacen) }}" method="POST"
                                class="inline" data-confirm="¿Eliminar {{ $almacen->nombre }}?">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 rounded-lg text-red-400 hover:bg-red-100 transition-colors" title="Eliminar">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
