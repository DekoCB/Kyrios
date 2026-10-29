@props(['role', 'caja' => null])

@php
    $empresa  = \App\Models\Empresa::instancia();
    $user     = auth()->user();
    $dashboard = route('admin.dashboard');
    $iniciales = collect(explode(' ', $user->name))->take(2)->map(fn($p) => strtoupper($p[0] ?? ''))->implode('');

    $navItems = [
        ['icon' => 'fa-tachometer-alt', 'label' => 'Inicio',      'href' => $dashboard,              'active' => false],
        ['icon' => 'fa-cash-register',  'label' => 'Nueva Venta', 'href' => route('ventas.create'),   'active' => request()->routeIs('ventas.create')],
        ['icon' => 'fa-list',           'label' => 'Historial',   'href' => route('ventas.index'),    'active' => request()->routeIs('ventas.index')],
        ['icon' => 'fa-users',          'label' => 'Clientes',    'href' => route('clientes.index'),  'active' => request()->routeIs('clientes.*')],
    ];
    $navItems[] = ['icon' => 'fa-cash-register', 'label' => 'Mi Caja', 'href' => route('caja.actual'), 'active' => request()->routeIs('caja.*')];
@endphp

{{-- Mobile hamburger --}}
<button onclick="document.getElementById('pos-sidebar-overlay').style.display='block'; document.getElementById('pos-sidebar').classList.remove('-translate-x-full');"
        class="md:hidden fixed top-3 left-3 z-50 bg-blue-600 text-white p-2 rounded-lg shadow-lg">
    <i class="fas fa-bars text-sm"></i>
</button>

{{-- Mobile overlay --}}
<div id="pos-sidebar-overlay"
     onclick="this.style.display='none'; document.getElementById('pos-sidebar').classList.add('-translate-x-full');"
     class="md:hidden fixed inset-0 bg-black/60 z-30"
     style="display:none"></div>

{{-- Sidebar --}}
<aside id="pos-sidebar"
       x-data="{
           collapsed: JSON.parse(localStorage.getItem('pos_sidebar_collapsed') ?? 'false'),
           toggleCollapse() {
               this.collapsed = !this.collapsed;
               localStorage.setItem('pos_sidebar_collapsed', this.collapsed);
               window.dispatchEvent(new CustomEvent('pos-sidebar-changed', { detail: { collapsed: this.collapsed } }));
           }
       }"
       :class="collapsed ? 'w-16' : 'w-64'"
       class="-translate-x-full md:translate-x-0 kyr-sidebar fixed left-0 top-0 h-full z-40 flex flex-col bg-blue-900 text-white shadow-2xl transition-all duration-300 ease-in-out">

    {{-- ── Header ── --}}
    <div class="flex items-center h-14 shrink-0 border-b border-white/10 overflow-hidden"
         :class="collapsed ? 'justify-center px-2' : 'justify-between px-3'">

        <div class="flex items-center gap-2 min-w-0 flex-1" x-show="!collapsed">
            <img src="{{ \App\Models\Empresa::logoUrlPara('oscuro') }}" alt="{{ $empresa?->nombre_display ?? config('app.name') }}"
                 style="height: 34px; width: auto; max-width: 8.5rem; object-fit: contain; display: block;">
            <span class="text-[10px] text-gray-400 shrink-0">POS</span>
        </div>

        <div x-show="collapsed" class="flex items-center justify-center">
            {{-- Barra contraída: el logo completo no cabe, se usa la inicial de la marca --}}
            <div class="h-8 w-8 rounded-lg flex items-center justify-center font-bold text-base"
                 style="background:#f2c80f; color:#3f3d37;" title="{{ $empresa?->nombre_display ?? config('app.name') }}">K</div>
        </div>

        <button @click="toggleCollapse()"
                class="hidden md:flex w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 items-center justify-center transition shrink-0">
            <i class="fas text-xs" :class="collapsed ? 'fa-chevron-right' : 'fa-chevron-left'"></i>
        </button>
    </div>

    {{-- ── User info ── --}}
    <div class="shrink-0 border-b border-white/10 py-3"
         :class="collapsed ? 'px-2' : 'px-3'">
        <div class="flex items-center gap-2" :class="collapsed ? 'justify-center' : ''">
            <div class="w-8 h-8 rounded-full bg-linear-to-br from-blue-500 to-blue-700 flex items-center justify-center text-xs font-bold shrink-0">
                {{ $iniciales }}
            </div>
            <div class="min-w-0" x-show="!collapsed">
                <p class="text-xs font-semibold truncate">{{ $user->name }}</p>
                <p class="text-[10px] text-gray-400 truncate">
                    {{ $role }}{{ $user->almacen ? ' · ' . $user->almacen->nombre : '' }}
                </p>
            </div>
        </div>

        {{-- Caja status badge --}}
        <div class="mt-2" x-show="!collapsed">
            @if($caja && $caja->estado === 'abierta')
                <div class="flex items-center gap-1.5 px-2 py-1.5 rounded-lg bg-green-500/10 border border-green-500/20">
                    <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse shrink-0"></span>
                    <div class="min-w-0">
                        <p class="text-[10px] text-green-400 font-semibold leading-tight">Caja abierta</p>
                        <p class="text-[10px] text-gray-300 truncate">S/ {{ number_format($caja->monto_final, 2) }}</p>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-1.5 px-2 py-1.5 rounded-lg bg-red-500/10 border border-red-500/20">
                    <span class="w-2 h-2 bg-red-400 rounded-full shrink-0"></span>
                    <p class="text-[10px] text-red-400 font-semibold">Caja cerrada</p>
                </div>
            @endif
        </div>

        {{-- Collapsed caja dot --}}
        <div class="flex justify-center mt-2" x-show="collapsed">
            @if($caja && $caja->estado === 'abierta')
                <span class="w-2.5 h-2.5 bg-green-400 rounded-full animate-pulse" title="Caja abierta"></span>
            @else
                <span class="w-2.5 h-2.5 bg-red-400 rounded-full" title="Caja cerrada"></span>
            @endif
        </div>
    </div>

    {{-- ── Nav items ── --}}
    <nav class="flex-1 overflow-y-auto py-3 space-y-1"
         :class="collapsed ? 'px-2' : 'px-2'">
        @foreach($navItems as $item)
            <a href="{{ $item['href'] }}"
               class="flex items-center rounded-xl py-2.5 transition-all duration-150 group relative"
               :class="collapsed ? 'justify-center px-2' : 'gap-3 px-3'"
               @class([
                   'bg-blue-600 text-white shadow-lg shadow-blue-600/20' => $item['active'],
                   'text-gray-400 hover:bg-white/8 hover:text-white' => !$item['active'],
               ])>
                <i class="fas {{ $item['icon'] }} w-4 text-center text-sm shrink-0"></i>
                <span x-show="!collapsed" class="text-sm font-medium truncate">{{ $item['label'] }}</span>
                {{-- Tooltip colapsado --}}
                <div x-show="collapsed"
                     class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-800 border border-white/10 text-white text-xs rounded-lg whitespace-nowrap
                            opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 shadow-xl">
                    {{ $item['label'] }}
                </div>
            </a>
        @endforeach
    </nav>

    {{-- ── Footer: logout ── --}}
    <div class="shrink-0 border-t border-white/10 py-3"
         :class="collapsed ? 'px-2' : 'px-2'">
        @include('partials.tema-toggle', ['clase' => 'mb-2'])
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full flex items-center rounded-xl py-2.5 text-gray-400 hover:bg-red-500/10 hover:text-red-400 transition group relative"
                    :class="collapsed ? 'justify-center px-2' : 'gap-3 px-3'">
                <i class="fas fa-sign-out-alt w-4 text-center text-sm shrink-0"></i>
                <span x-show="!collapsed" class="text-sm font-medium">Salir</span>
                <div x-show="collapsed"
                     class="absolute left-full ml-3 px-2.5 py-1.5 bg-gray-800 border border-white/10 text-white text-xs rounded-lg whitespace-nowrap
                            opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 shadow-xl">
                    Cerrar sesión
                </div>
            </button>
        </form>
    </div>
</aside>
