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

    {{-- Sidebar --}}
    <div :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="fixed left-0 top-0 h-full w-64 bg-gradient-to-b from-blue-900 to-blue-800 text-white shadow-xl z-50 transition-transform duration-300 ease-in-out"
            x-data="{
                inventarioOpen: {{ request()->routeIs('inventario.*') ? 'true' : 'false' }},
                comprasOpen: {{ request()->routeIs('compras.*') || request()->routeIs('pedidos.*') || request()->routeIs('proveedores.*') || request()->routeIs('cuentas-por-pagar.*') ? 'true' : 'false' }},
                ventasOpen: {{ request()->routeIs('ventas.*') || request()->routeIs('clientes.*') || request()->routeIs('precios.*') || request()->routeIs('cuentas-por-cobrar.*') ? 'true' : 'false' }},
                facturacionOpen: {{ request()->routeIs('facturacion.*') ? 'true' : 'false' }},
                reportesOpen: {{ request()->routeIs('reportes.*') ? 'true' : 'false' }},
                trasladosOpen: {{ request()->routeIs('traslados.*') || request()->routeIs('guias-remision.*') ? 'true' : 'false' }},
                cajaOpen: {{ request()->routeIs('caja.*') ? 'true' : 'false' }},
                catalogoOpen: {{ request()->routeIs('catalogo.*') ? 'true' : 'false' }},
                tiendaOpen: {{ request()->routeIs('tienda.*') ? 'true' : 'false' }},
                adminOpen: {{ request()->routeIs('admin.empresa.*') || request()->routeIs('admin.sucursales.*') || request()->routeIs('admin.cajas.*') ? 'true' : 'false' }}
            }">

        @php $empresa = \App\Models\Empresa::instancia(); @endphp
        <div class="p-4 border-b border-blue-700 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                @if($empresa?->logo_url)
                    <img src="{{ $empresa->logo_url }}" alt="Logo" class="h-10 w-10 object-contain rounded-lg bg-white/10 p-0.5 shrink-0">
                @else
                    <i class="fas fa-home text-2xl text-blue-300 shrink-0"></i>
                @endif
                <div class="min-w-0">
                    <h1 class="text-sm font-bold leading-tight truncate">{{ $empresa?->nombre_display ?? 'CORPORACIÓN' }}</h1>
                    @if($empresa && $empresa->nombre_comercial && $empresa->nombre_comercial !== $empresa->razon_social)
                        <p class="text-[11px] text-blue-300 truncate">{{ $empresa->razon_social }}</p>
                    @elseif(!$empresa)
                        <p class="text-[11px] text-blue-300 truncate">ADIVON SAC</p>
                    @endif
                </div>
            </div>
            <button @click="sidebarOpen = false" class="md:hidden text-blue-300 hover:text-white shrink-0">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="p-4 bg-blue-800/50">
            <div class="flex items-center space-x-3">
                <div class="bg-blue-600 rounded-full p-2">
                    <i class="fas fa-user text-white"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-blue-300">{{ $role }}</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto p-4" style="max-height: calc(100vh - 220px);">
            <ul class="space-y-2">

                @if($role == 'Administrador')
                    {{-- Dashboard --}}
                    <li>
                        <a href="{{ route('admin.dashboard') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                        </a>
                    </li>
                    
                    {{-- Reportes --}}
                    <li>
                        <button @click="reportesOpen = !reportesOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('reportes.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-chart-line mr-3"></i>Reportes
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': reportesOpen }"></i>
                        </button>
                        <ul x-show="reportesOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('reportes.ventas') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('reportes.ventas') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-chart-line mr-3 text-sm"></i>Ventas / Márgenes
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('reportes.compras') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('reportes.compras') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-shopping-cart mr-3 text-sm"></i>Compras / Importaciones
                                </a>
                            </li>
                        </ul>
                    </li>
                    {{-- Ventas y Precios --}}
                    <li>
                        <button @click="ventasOpen = !ventasOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('ventas.*') || request()->routeIs('clientes.*') || request()->routeIs('precios.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-cash-register mr-3"></i>Ventas
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': ventasOpen }"></i>
                        </button>
                        <ul x-show="ventasOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('clientes.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('clientes.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-users mr-3 text-sm"></i>Clientes
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('ventas.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('ventas.index') || request()->routeIs('ventas.show') || request()->routeIs('ventas.create') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-receipt mr-3 text-sm"></i>Registrar Ventas
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('ventas.cotizaciones') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('ventas.cotizaciones') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-file-contract mr-3 text-sm"></i>Cotizaciones
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('precios.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('precios.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-tags mr-3 text-sm"></i>Gestión de Precios
                                </a>
                            </li>
                            @php
                                $cpcVencidas = \App\Models\CuentaPorCobrar::where(function($q) {
                                    $q->where('estado', 'vencido')
                                      ->orWhere(fn($s) => $s->where('estado', 'vigente')->where('fecha_vencimiento_final', '<', now()));
                                })->count();
                            @endphp
                            <li>
                                <a href="{{ route('cuentas-por-cobrar.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('cuentas-por-cobrar.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-hand-holding-usd mr-3 text-sm"></i>Cuentas por Cobrar
                                    @if($cpcVencidas > 0)
                                        <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none">
                                            {{ $cpcVencidas > 99 ? '99+' : $cpcVencidas }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('ventas.auditoria') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('ventas.auditoria') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-clipboard-list mr-3 text-sm"></i>Bitácora de Ventas
                                </a>
                            </li>
                        </ul>
                    </li>
                      {{-- Facturación Electrónica --}}
                    @php $mostrarNuevo = \Carbon\Carbon::now()->lt(\Carbon\Carbon::parse('2026-05-05')); @endphp
                    <li>
                        <button @click="facturacionOpen = !facturacionOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('facturacion.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-file-invoice-dollar mr-3"></i>Facturación
                                @if($mostrarNuevo)
                                <span class="ml-2 text-[9px] font-bold bg-emerald-400 text-emerald-900 px-1.5 py-0.5 rounded-full leading-none">NEW</span>
                                @endif
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': facturacionOpen }"></i>
                        </button>
                        <ul x-show="facturacionOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('facturacion.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('facturacion.index') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-list-alt mr-3 text-sm"></i>Comprobantes
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('facturacion.series') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('facturacion.series') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-list-ol mr-3 text-sm"></i>Series
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('facturacion.configuracion') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('facturacion.configuracion') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-cog mr-3 text-sm"></i>Configuración
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Compras --}}
                    <li>
                        <button @click="comprasOpen = !comprasOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('compras.*') || request()->routeIs('pedidos.*') || request()->routeIs('proveedores.*') || request()->routeIs('cuentas-por-pagar.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-shopping-bag mr-3"></i>Compras
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': comprasOpen }"></i>
                        </button>
                        <ul x-show="comprasOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('proveedores.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('proveedores.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-truck mr-3 text-sm"></i>Proveedores
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('compras.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('compras.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-file-invoice mr-3 text-sm"></i>Registrar Compras
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('cuentas-por-pagar.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('cuentas-por-pagar.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-credit-card mr-3 text-sm"></i>Cuentas por Pagar
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('pedidos.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('pedidos.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-clipboard-list mr-3 text-sm"></i>Pedidos a Proveedor
                                </a>
                            </li>
                        </ul>
                    </li>


                    {{-- Inventario --}}
                    <li>
                        <button @click="inventarioOpen = !inventarioOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-boxes mr-3"></i>Inventario
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': inventarioOpen }"></i>
                        </button>
                        <ul x-show="inventarioOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('inventario.categorias.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.categorias.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-tags mr-3 text-sm"></i>Categorías
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.productos.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.productos.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-box mr-3 text-sm"></i>Productos
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.almacenes.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.almacenes.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-warehouse mr-3 text-sm"></i>Locales y Stock
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.imeis.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.imeis.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-mobile-alt mr-3 text-sm"></i>IMEIs
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.movimientos.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.movimientos.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-exchange-alt mr-3 text-sm"></i>Movimientos
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.reportes.stock-valorizado') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.reportes.stock-valorizado') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-coins mr-3 text-sm"></i>Stock Valorizado
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.reportes.kardex') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.reportes.kardex') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-book-open mr-3 text-sm"></i>Kardex
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.reportes.abc') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.reportes.abc') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-chart-bar mr-3 text-sm"></i>Análisis ABC
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.reportes.valorizacion-prorateada') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.reportes.valorizacion-prorateada') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-balance-scale mr-3 text-sm"></i>Valorizacion Prorateada
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario-fisico.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario-fisico.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-clipboard-check mr-3 text-sm"></i>Conteo Físico
                                    <span class="ml-auto text-[10px] font-bold bg-green-400 text-green-900 px-1.5 py-0.5 rounded-full leading-none">Nuevo</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    {{-- Traslados --}}
                    <li>
                        <button @click="trasladosOpen = !trasladosOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-truck-loading mr-3"></i>Traslados
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': trasladosOpen }"></i>
                        </button>
                        <ul x-show="trasladosOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('traslados.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.index') || request()->routeIs('traslados.show') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-exchange-alt mr-3 text-sm"></i>Historial
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('traslados.stock') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.stock') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-boxes mr-3 text-sm"></i>Ver Stock
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('traslados.pendientes') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.pendientes') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-clock mr-3 text-sm"></i>Pendientes
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('traslados.create') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.create') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-plus mr-3 text-sm"></i>Nuevo Traslado
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('guias-remision.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('guias-remision.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-file-invoice mr-3 text-sm"></i>Guías de Remisión
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Devoluciones --}}
                    <li>
                        <a href="{{ route('devoluciones.index') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('devoluciones.*') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-undo-alt mr-3"></i>Devoluciones
                            <span class="ml-auto text-[10px] font-bold bg-green-400 text-green-900 px-1.5 py-0.5 rounded-full leading-none">Nuevo</span>
                        </a>
                    </li>

                    {{-- Comisiones --}}
                    <li>
                        <a href="{{ route('comisiones.index') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('comisiones.*') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-percentage mr-3"></i>Comisiones
                            <span class="ml-auto text-[10px] font-bold bg-green-400 text-green-900 px-1.5 py-0.5 rounded-full leading-none">Nuevo</span>
                        </a>
                    </li>

                    {{-- Caja --}}
                    <li>
                        <button @click="cajaOpen = !cajaOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('caja.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-cash-register mr-3"></i>Caja
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': cajaOpen }"></i>
                        </button>
                        <ul x-show="cajaOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('caja.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('caja.index') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-history mr-3 text-sm"></i>Historial de Cajas
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('caja.actual') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('caja.actual') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-door-open mr-3 text-sm"></i>Caja Activa
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Catálogo --}}
                    <li>
                        <button @click="catalogoOpen = !catalogoOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('catalogo.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-book mr-3"></i>
                                <span>
                                    Catálogo
                                    @if($mostrarNuevo)
                                    <span class="ml-1 text-[9px] font-bold bg-emerald-400 text-emerald-900 px-1.5 py-0.5 rounded-full leading-none">UPD</span>
                                    @endif
                                </span>
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': catalogoOpen }"></i>
                        </button>
                        <ul x-show="catalogoOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('catalogo.colores.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('catalogo.colores.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-palette mr-3 text-sm"></i>Colores
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('catalogo.marcas.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('catalogo.marcas.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-trademark mr-3 text-sm"></i>Marcas
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('catalogo.modelos.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('catalogo.modelos.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-mobile-alt mr-3 text-sm"></i>Modelos
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('catalogo.unidades.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('catalogo.unidades.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-ruler mr-3 text-sm"></i>Unidades de Medida
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('catalogo.motivos.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('catalogo.motivos.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-exchange-alt mr-3 text-sm"></i>Motivos de Movimiento
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Administración --}}
                    <li>
                        <button @click="adminOpen = !adminOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('admin.empresa.*') || request()->routeIs('admin.sucursales.*') || request()->routeIs('admin.cajas.*') || request()->routeIs('inventario.almacenes.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-cogs mr-3"></i>Administración
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': adminOpen }"></i>
                        </button>
                        <ul x-show="adminOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('admin.empresa.edit') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('admin.empresa.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-building mr-3 text-sm"></i>Empresa
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.sucursales.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('admin.sucursales.*') || request()->routeIs('inventario.almacenes.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-map-marker-alt mr-3 text-sm"></i>Locales y Almacenes
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.cajas.dashboard') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('admin.cajas.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-cash-register mr-3 text-sm"></i>
                                    <span>Supervisión Cajas</span>
                                    @php $_alertasCaja = app(\App\Http\Controllers\Admin\AdminCajaController::class)->contarAlertas(); @endphp
                                    @if($_alertasCaja > 0)
                                        <span class="ml-auto bg-red-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center font-bold shrink-0">
                                            {{ $_alertasCaja > 9 ? '9+' : $_alertasCaja }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Usuarios --}}
                    <li>
                        <a href="{{ route('users.index') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('users.*') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-users mr-3"></i>Usuarios
                        </a>
                    </li>

                @elseif($role == 'Almacenero')
                    {{-- Dashboard --}}
                    <li>
                        <a href="{{ route('almacenero.dashboard') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('almacenero.dashboard') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                        </a>
                    </li>

                    {{-- Inventario --}}
                    <li>
                        <button @click="inventarioOpen = !inventarioOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-boxes mr-3"></i>Inventario
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': inventarioOpen }"></i>
                        </button>
                        <ul x-show="inventarioOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('inventario.productos.index') }}"
                                     class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.productos.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-box mr-3 text-sm"></i>Productos
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.almacenes.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.almacenes.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-warehouse mr-3 text-sm"></i>Locales y Stock
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.imeis.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.imeis.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-mobile-alt mr-3 text-sm"></i>IMEIs
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario.movimientos.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario.movimientos.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-exchange-alt mr-3 text-sm"></i>Movimientos
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('inventario-fisico.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('inventario-fisico.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-clipboard-check mr-3 text-sm"></i>Conteo Físico
                                    <span class="ml-auto text-[10px] font-bold bg-green-400 text-green-900 px-1.5 py-0.5 rounded-full leading-none">Nuevo</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Compras --}}
                    <li>
                        <button @click="comprasOpen = !comprasOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('compras.*') || request()->routeIs('pedidos.*') || request()->routeIs('proveedores.*') || request()->routeIs('cuentas-por-pagar.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-shopping-bag mr-3"></i>Compras
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': comprasOpen }"></i>
                        </button>
                        <ul x-show="comprasOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('proveedores.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('proveedores.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-truck mr-3 text-sm"></i>Proveedores
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('compras.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('compras.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-file-invoice mr-3 text-sm"></i>Registrar Compras
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('cuentas-por-pagar.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('cuentas-por-pagar.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-credit-card mr-3 text-sm"></i>Cuentas por Pagar
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('pedidos.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('pedidos.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-clipboard-list mr-3 text-sm"></i>Pedidos a Proveedor
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Traslados --}}
                    <li>
                        <button @click="trasladosOpen = !trasladosOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-truck-loading mr-3"></i>Traslados
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': trasladosOpen }"></i>
                        </button>
                        <ul x-show="trasladosOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('traslados.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.index') || request()->routeIs('traslados.show') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-exchange-alt mr-3 text-sm"></i>Historial
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('traslados.stock') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.stock') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-boxes mr-3 text-sm"></i>Ver Stock
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('traslados.pendientes') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.pendientes') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-clock mr-3 text-sm"></i>Pendientes
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('traslados.create') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('traslados.create') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-plus mr-3 text-sm"></i>Nuevo Traslado
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('guias-remision.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('guias-remision.*') ? 'bg-blue-600' : '' }}">
                                    <i class="fas fa-file-invoice mr-3 text-sm"></i>Guías de Remisión
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Devoluciones --}}
                    <li>
                        <a href="{{ route('devoluciones.index') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('devoluciones.*') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-undo-alt mr-3"></i>Devoluciones
                            <span class="ml-auto text-[10px] font-bold bg-green-400 text-green-900 px-1.5 py-0.5 rounded-full leading-none">Nuevo</span>
                        </a>
                    </li>

                    {{-- Catálogo --}}
                    <li>
                        <button @click="catalogoOpen = !catalogoOpen"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('catalogo.*') ? 'bg-blue-700' : '' }}">
                            <span class="flex items-center">
                                <i class="fas fa-book mr-3"></i>Consultar Catálogo
                            </span>
                            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': catalogoOpen }"></i>
                        </button>
                        <ul x-show="catalogoOpen" x-transition class="ml-4 mt-2 space-y-1">
                            <li>
                                <a href="{{ route('catalogo.colores.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-palette mr-3 text-sm"></i>Colores
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('catalogo.marcas.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-trademark mr-3 text-sm"></i>Marcas
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('catalogo.modelos.index') }}"
                                    class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-mobile-alt mr-3 text-sm"></i>Modelos
                                </a>
                            </li>
                        </ul>
                    </li>
                @elseif($role == 'Tienda')
                    {{-- Dashboard de Tienda --}}
                    <li>
                        <a href="{{ route('tienda.dashboard') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('tienda.dashboard') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-store mr-3 w-5"></i>Dashboard Tienda
                        </a>
                    </li>

                    {{-- Separador visual --}}
                    <li class="px-4 py-2">
                        <div class="border-t border-blue-800"></div>
                    </li>

                    {{-- GRUPO: VENTAS --}}
                    <li class="px-4 text-xs font-semibold text-blue-300 uppercase tracking-wider">Ventas</li>
                    
                    <li>
                        <a href="{{ route('ventas.create') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('ventas.create') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-plus-circle mr-3 text-sm w-4"></i>Nueva Venta
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('ventas.index') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('ventas.index') || request()->routeIs('ventas.show') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-receipt mr-3 text-sm w-4"></i>Historial Ventas
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('ventas.cotizaciones') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('ventas.cotizaciones') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-file-contract mr-3 text-sm w-4"></i>Cotizaciones
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('clientes.index') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('clientes.*') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-users mr-3 text-sm w-4"></i>Clientes
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('devoluciones.index') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('devoluciones.*') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-undo-alt mr-3 text-sm w-4"></i>Devoluciones
                        </a>
                    </li>

                    {{-- Separador visual --}}
                    <li class="px-4 py-2 mt-2">
                        <div class="border-t border-blue-800"></div>
                    </li>

                    {{-- GRUPO: INVENTARIO --}}
                    <li class="px-4 text-xs font-semibold text-blue-300 uppercase tracking-wider">Inventario</li>

                    <li>
                        <a href="{{ route('tienda.inventario.ver') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('tienda.inventario.ver') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-boxes mr-3 text-sm w-4"></i>Ver Stock
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('tienda.inventario.solicitudes') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('tienda.inventario.solicitudes') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-clipboard-list mr-3 text-sm w-4"></i>Mis Solicitudes
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('traslados.pendientes') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('traslados.pendientes') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-truck-loading mr-3 text-sm w-4"></i>Traslados Pendientes
                            
                            {{-- Badge seguro sin modelo --}}
                            @if(auth()->user() && auth()->user()->tienda_id)
                                @php
                                    try {
                                        // Intenta usar el modelo Traslado si existe
                                        if (class_exists('App\Models\Traslado')) {
                                            $pendientesCount = App\Models\Traslado::where('tienda_origen_id', auth()->user()->tienda_id)
                                                                ->where('estado', 'pendiente')
                                                                ->count();
                                        } else {
                                            $pendientesCount = 0; // Valor por defecto
                                        }
                                    } catch (\Exception $e) {
                                        $pendientesCount = 0; // Si hay error, mostrar 0
                                    }
                                @endphp
                                
                                @if($pendientesCount > 0)
                                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">{{ $pendientesCount }}</span>
                                @endif
                            @endif
                        </a>
                    </li>

                    {{-- Separador visual --}}
                    <li class="px-4 py-2 mt-2">
                        <div class="border-t border-blue-800"></div>
                    </li>

                    {{-- GRUPO: CAJA --}}
                    <li class="px-4 text-xs font-semibold text-blue-300 uppercase tracking-wider">Caja</li>
                    
                    <li>
                        <a href="{{ route('caja.actual') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('caja.actual') || request()->routeIs('caja.abrir') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-door-open mr-3 text-sm w-4"></i>Caja Actual
                            
                            {{-- Badge seguro para caja --}}
                            @if(auth()->user() && auth()->user()->tienda_id)
                                @php
                                    try {
                                        if (class_exists('App\Models\Caja')) {
                                            $cajaAbierta = App\Models\Caja::where('tienda_id', auth()->user()->tienda_id)
                                                            ->where('estado', 'abierta')
                                                            ->first();
                                        } else {
                                            $cajaAbierta = null;
                                        }
                                    } catch (\Exception $e) {
                                        $cajaAbierta = null;
                                    }
                                @endphp
                                
                                @if(!$cajaAbierta)
                                    <span class="ml-auto bg-yellow-500 text-white text-xs px-2 py-1 rounded-full">Cerrada</span>
                                @else
                                    <span class="ml-auto bg-green-500 text-white text-xs px-2 py-1 rounded-full">Abierta</span>
                                @endif
                            @endif
                        </a>
                    </li>
                    
                    <li>
                        <a href="{{ route('caja.index') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('caja.index') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-history mr-3 text-sm w-4"></i>Historial de Caja
                        </a>
                    </li>

                    {{-- Mis Comisiones --}}
                    <li>
                        <a href="{{ route('mis-comisiones') }}"
                            class="flex items-center px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors ml-4 {{ request()->routeIs('mis-comisiones') ? 'bg-blue-600' : '' }}">
                            <i class="fas fa-percentage mr-3 text-sm w-4"></i>Mis Comisiones
                        </a>
                    </li>

                @elseif($role == 'Vendedor')
                    {{-- Dashboard --}}
                    <li>
                        <a href="{{ route('vendedor.dashboard') }}"
                           class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('vendedor.dashboard') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                        </a>
                    </li>

                    {{-- Ventas --}}
                    <li>
                        <a href="{{ route('ventas.index') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('ventas.*') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-shopping-cart mr-3"></i>Mis Ventas
                        </a>
                    </li>

                    {{-- Clientes --}}
                    <li>
                        <a href="{{ route('clientes.index') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('clientes.*') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-users mr-3"></i>Clientes
                        </a>
                    </li>

                    {{-- Devoluciones --}}
                    <li>
                        <a href="{{ route('devoluciones.index') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('devoluciones.*') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-undo-alt mr-3"></i>Devoluciones
                            <span class="ml-auto text-[10px] font-bold bg-green-400 text-green-900 px-1.5 py-0.5 rounded-full leading-none">Nuevo</span>
                        </a>
                    </li>

                    {{-- Mis Comisiones --}}
                    <li>
                        <a href="{{ route('mis-comisiones') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('mis-comisiones') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-percentage mr-3"></i>Mis Comisiones
                        </a>
                    </li>

                @elseif($role == 'Cajero')
                    {{-- Dashboard --}}
                    <li>
                        <a href="{{ route('cajero.dashboard') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('cajero.dashboard') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                        </a>
                    </li>

                    {{-- Cola de Caja --}}
                    <li>
                        <a href="{{ route('cajero.cola') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('cajero.cola') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-stream mr-3"></i>
                            <span class="flex items-center gap-2">
                                Cola de Caja
                                <span class="text-[10px] font-bold bg-green-400 text-green-900 px-1.5 py-0.5 rounded-full leading-none">Nuevo</span>
                            </span>
                            @php
                                try {
                                    $colaPendientes = \App\Models\Venta::where('estado_pago', 'pendiente')
                                        ->when(auth()->user()->almacen_id, fn($q) => $q->where('almacen_id', auth()->user()->almacen_id))
                                        ->count();
                                } catch (\Exception $e) {
                                    $colaPendientes = 0;
                                }
                            @endphp
                            @if($colaPendientes > 0)
                                <span class="ml-auto bg-amber-500 text-white text-[10px] rounded-full w-5 h-5 flex items-center justify-center font-bold shrink-0">
                                    {{ $colaPendientes > 9 ? '9+' : $colaPendientes }}
                                </span>
                            @endif
                        </a>
                    </li>

                    {{-- Nueva Venta --}}
                    <li>
                        <a href="{{ route('ventas.create') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('ventas.create') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-plus-circle mr-3"></i>Nueva Venta (POS)
                        </a>
                    </li>

                    {{-- Mi Caja --}}
                    <li>
                        <a href="{{ route('caja.actual') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('caja.actual') || request()->routeIs('caja.abrir') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-cash-register mr-3"></i>Mi Caja
                        </a>
                    </li>

                    {{-- Mis Comisiones --}}
                    <li>
                        <a href="{{ route('mis-comisiones') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('mis-comisiones') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-percentage mr-3"></i>Mis Comisiones
                        </a>
                    </li>

                @elseif($role == 'Proveedor')
                    {{-- Dashboard --}}
                    <li>
                        <a href="{{ route('proveedor.dashboard') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('proveedor.dashboard') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                        </a>
                    </li>

                    {{-- Pedidos --}}
                    <li>
                        <a href="{{ route('proveedor.pedidos') }}"
                            class="flex items-center px-4 py-3 text-sm rounded-lg hover:bg-blue-700 transition-colors {{ request()->routeIs('proveedor.pedidos') ? 'bg-blue-700' : '' }}">
                            <i class="fas fa-file-invoice mr-3"></i>Mis Pedidos
                        </a>
                    </li>
                @endif
            </ul>
        </nav>

        <div class="p-4 border-t border-blue-700">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center px-4 py-3 text-sm rounded-lg hover:bg-red-600 transition-colors">
                    <i class="fas fa-sign-out-alt mr-3"></i>Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
</div>