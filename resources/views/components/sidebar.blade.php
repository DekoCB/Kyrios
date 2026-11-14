@props(['role'])

{{-- Mobile hamburger button --}}
<div x-data="{ sidebarOpen: false }">
    <button @click="sidebarOpen = true"
            class="md:hidden fixed top-4 left-4 z-40 bg-blue-900 text-white p-2 rounded-lg shadow-lg">
        <i class="fas fa-bars text-xl"></i>
    </button>

    {{-- Overlay --}}
    <div x-show="sidebarOpen" @click="sidebarOpen = false"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="md:hidden fixed inset-0 bg-black/50 z-40" style="display: none;"></div>

    @php
        $menu = \App\Support\MenuLateral::para($role, auth()->id());
        $grupoAbierto = \App\Support\MenuLateral::grupoAbierto($menu);
    @endphp

    {{-- Sidebar --}}
    <div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="kyr-sidebar fixed left-0 top-0 h-full w-64 flex flex-col bg-gradient-to-b from-blue-900 to-blue-800 text-white shadow-xl z-50 transition-transform duration-300 ease-in-out"
            x-data="{ abierto: '{{ $grupoAbierto }}' }">

        @php $empresa = \App\Models\Empresa::instancia(); @endphp
        <div class="p-4 border-b border-blue-700 flex items-center justify-between gap-2 shrink-0">
            <div class="flex items-center gap-2 min-w-0">
                <a href="/" class="block min-w-0" title="{{ $empresa?->nombre_display ?? config('app.name') }}">
                    <img src="{{ \App\Models\Empresa::logoUrlPara('oscuro') }}" alt="{{ $empresa?->nombre_display ?? config('app.name') }}"
                         style="height: 42px; width: auto; max-width: 11rem; object-fit: contain; display: block;">
                </a>
            </div>
            <button @click="sidebarOpen = false" class="md:hidden text-blue-300 hover:text-white shrink-0">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="p-4 bg-blue-800/50 shrink-0">
            <div class="flex items-center space-x-3">
                <div class="bg-blue-600 rounded-full p-2">
                    <i class="fas fa-user text-white"></i>
                </div>
                <a href="{{ route('profile.edit') }}" class="flex-1 hover:opacity-80" title="Mi perfil">
                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-blue-300">{{ $role }}</p>
                </a>
                @if($role !== 'Cliente')
                @php $avisosSinLeer = auth()->user()->unreadNotifications()->count(); @endphp
                <a href="{{ route('notificaciones.index') }}" title="Avisos" aria-label="Avisos{{ $avisosSinLeer ? ' (' . $avisosSinLeer . ' sin leer)' : '' }}"
                   class="relative w-9 h-9 flex items-center justify-center rounded-lg text-blue-300 hover:text-white hover:bg-blue-700 transition-colors shrink-0">
                    <i class="fas fa-bell"></i>
                    @if($avisosSinLeer > 0)
                        <span class="absolute -top-1 -right-1 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center">{{ $avisosSinLeer > 99 ? '99+' : $avisosSinLeer }}</span>
                    @endif
                </a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" title="Cerrar sesión" aria-label="Cerrar sesión"
                            class="w-9 h-9 flex items-center justify-center rounded-lg text-blue-300 hover:text-white hover:bg-red-600 transition-colors">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </form>
            </div>
        </div>

        <nav class="flex-1 min-h-0 overflow-y-auto p-4" aria-label="Menú principal">
            <ul class="space-y-1">
                @foreach($menu as $item)
                    @if($item['tipo'] === 'titulo')
                        <li class="pt-4 pb-1 px-4 text-[10px] font-semibold uppercase tracking-wider text-blue-300/70 select-none" aria-hidden="true">{{ $item['texto'] }}</li>
                    @elseif($item['tipo'] === 'enlace')
                        <li>
                            <a href="{{ route($item['ruta']) }}"
                               class="flex items-center px-4 py-2.5 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ \App\Support\MenuLateral::activo($item) ? 'bg-blue-700' : '' }}">
                                <i class="fas {{ $item['icono'] }} w-5 mr-3 text-center"></i><span class="flex-1">{{ $item['etiqueta'] }}</span>
                                @if($item['insignia'] > 0)
                                    <span class="ml-2 bg-red-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none">{{ $item['insignia'] > 99 ? '99+' : $item['insignia'] }}</span>
                                @endif
                            </a>
                        </li>
                    @else
                        @php
                            $activoGrupo = \App\Support\MenuLateral::grupoActivo($item);
                            $insigniaGrupo = collect($item['hijos'])->sum('insignia');
                        @endphp
                        <li>
                            <button type="button" @click="abierto = (abierto === '{{ $item['id'] }}' ? '' : '{{ $item['id'] }}')"
                                    :aria-expanded="abierto === '{{ $item['id'] }}'"
                                    class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ $activoGrupo ? 'bg-blue-700/70' : '' }}">
                                <span class="flex items-center">
                                    <i class="fas {{ $item['icono'] }} w-5 mr-3 text-center"></i>{{ $item['etiqueta'] }}
                                    @if($insigniaGrupo > 0)
                                        <span x-show="abierto !== '{{ $item['id'] }}'" class="ml-2 bg-red-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none">{{ $insigniaGrupo > 99 ? '99+' : $insigniaGrupo }}</span>
                                    @endif
                                </span>
                                <i class="fas fa-chevron-down text-xs transition-transform duration-200" :class="{ 'rotate-180': abierto === '{{ $item['id'] }}' }"></i>
                            </button>
                            <ul x-show="abierto === '{{ $item['id'] }}'" x-transition class="ml-4 mt-1 mb-1 space-y-0.5 border-l border-blue-700/60 pl-2" @if(!$activoGrupo) style="display: none;" @endif>
                                @foreach($item['hijos'] as $hijo)
                                    <li>
                                        <a href="{{ route($hijo['ruta']) }}"
                                           class="flex items-center px-3 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ \App\Support\MenuLateral::activo($hijo) ? 'bg-blue-600' : '' }}">
                                            <i class="fas {{ $hijo['icono'] }} w-4 mr-3 text-center text-xs"></i><span class="flex-1">{{ $hijo['etiqueta'] }}</span>
                                            @if($hijo['insignia'] > 0)
                                                <span class="ml-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none">{{ $hijo['insignia'] > 99 ? '99+' : $hijo['insignia'] }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="p-4 border-t border-blue-700 space-y-2 shrink-0">
            @include('partials.tema-toggle', ['texto' => 'Cambiar tema'])
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center px-4 py-3 text-sm rounded-lg hover:bg-red-600 transition-colors">
                    <i class="fas fa-sign-out-alt mr-3"></i>Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
</div>