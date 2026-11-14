<?php

namespace App\Support;

use App\Models\Aprobacion;
use App\Models\CajaChica;
use App\Models\CuentaPorCobrar;
use App\Models\Role;
use App\Models\SolicitudRrhh;

/**
 * Menú lateral por rol, ordenado por secciones: Principal, Comercial, Almacén, Finanzas, Equipo y Sistema.
 *
 * Cada entrada es uno de:
 *  - titulo: encabezado de sección,
 *  - enlace:  [etiqueta, icono, ruta, activo (patrones de routeIs), insignia],
 *  - grupo:   [id, etiqueta, icono, hijos (enlaces)]; en el menú solo hay un grupo abierto a la vez.
 */
class MenuLateral
{
    private static function titulo(string $texto): array
    {
        return ['tipo' => 'titulo', 'texto' => $texto];
    }

    private static function enlace(string $etiqueta, string $icono, string $ruta, ?array $activo = null, int $insignia = 0): array
    {
        return ['tipo' => 'enlace', 'etiqueta' => $etiqueta, 'icono' => $icono, 'ruta' => $ruta, 'activo' => $activo ?? [$ruta], 'insignia' => $insignia];
    }

    /** @param array[] $hijos */
    private static function grupo(string $id, string $etiqueta, string $icono, array $hijos): array
    {
        return ['tipo' => 'grupo', 'id' => $id, 'etiqueta' => $etiqueta, 'icono' => $icono, 'hijos' => $hijos];
    }

    public static function activo(array $item): bool
    {
        return request()->routeIs(...$item['activo']);
    }

    public static function grupoActivo(array $grupo): bool
    {
        foreach ($grupo['hijos'] as $h) {
            if (self::activo($h)) {
                return true;
            }
        }

        return false;
    }

    /** Id del grupo que contiene la página actual (para abrirlo al cargar). */
    public static function grupoAbierto(array $menu): string
    {
        foreach ($menu as $m) {
            if ($m['tipo'] === 'grupo' && self::grupoActivo($m)) {
                return $m['id'];
            }
        }

        return '';
    }

    public static function para(string $rol, ?int $userId = null): array
    {
        return match ($rol) {
            Role::ADMINISTRACION => self::administracion(),
            Role::CONTADOR       => self::contador($userId),
            Role::LOGISTICA      => self::logistica($userId),
            Role::OPERACIONES    => self::operaciones($userId),
            default              => [
                self::enlace('Mis pedidos', 'fa-box-open', 'cliente.dashboard'),
                self::enlace('Mi perfil', 'fa-user', 'profile.edit', ['profile.*']),
            ],
        };
    }

    private static function dashboard(string $ruta): array
    {
        return self::grupo('dashboard', 'Dashboard', 'fa-tachometer-alt', [
            self::enlace('Resumen', 'fa-chart-pie', $ruta),
            self::enlace('Tareas del día', 'fa-list-check', 'tareas.index', ['tareas.*']),
        ]);
    }

    private static function asistencias(bool $admin): array
    {
        $hijos = [self::enlace('Mi asistencia', 'fa-fingerprint', 'rrhh.mi-asistencia')];
        if ($admin) {
            $hijos[] = self::enlace('Control del día', 'fa-user-clock', 'rrhh.asistencia.control');
            $hijos[] = self::enlace('Reporte mensual', 'fa-chart-column', 'rrhh.asistencia.reporte');
        } else {
            $hijos[] = self::enlace('Mis solicitudes', 'fa-umbrella-beach', 'rrhh.mis-solicitudes');
        }

        return self::grupo('asistencias', 'Asistencias', 'fa-user-clock', $hijos);
    }

    private static function cajaChicaPropia(?int $userId): array
    {
        return $userId && CajaChica::where('responsable_id', $userId)->exists()
            ? [self::enlace('Mi caja chica', 'fa-wallet', 'caja-chica.index', ['caja-chica.*'])]
            : [];
    }

    private static function cuentasPorCobrarVencidas(): int
    {
        return CuentaPorCobrar::where(function ($q) {
            $q->where('estado', 'vencido')->orWhere(fn ($s) => $s->where('estado', 'vigente')->where('fecha_vencimiento_final', '<', now()));
        })->count();
    }

    private static function alertasCajas(): int
    {
        try {
            return (int) app(\App\Http\Controllers\Admin\AdminCajaController::class)->contarAlertas();
        } catch (\Throwable) {
            return 0;
        }
    }

    // ── Administración ───────────────────────────────────────────────────────

    private static function administracion(): array
    {
        $aprobaciones = Aprobacion::pendientes()->count();
        $solicitudesRrhh = SolicitudRrhh::where('estado', 'pendiente')->count();

        return [
            self::titulo('Principal'),
            self::dashboard('admin.dashboard'),
            self::enlace('Aprobaciones', 'fa-clipboard-check', 'aprobaciones.index', ['aprobaciones.index', 'aprobaciones.clientes'], $aprobaciones),

            self::titulo('Comercial'),
            self::grupo('cotizaciones', 'Cotizaciones', 'fa-file-contract', [
                self::enlace('Cotizaciones', 'fa-file-contract', 'cotizaciones.index', ['cotizaciones.*']),
                self::enlace('Notas de pedido', 'fa-clipboard-list', 'notas-pedido.index', ['notas-pedido.*']),
                self::enlace('Pedidos internos', 'fa-dolly', 'pedidos-internos.index', ['pedidos-internos.*']),
            ]),
            self::enlace('Clientes', 'fa-users', 'clientes.index', ['clientes.*']),
            self::grupo('ventas', 'Ventas', 'fa-cash-register', [
                self::enlace('Registrar ventas', 'fa-receipt', 'ventas.index', ['ventas.index', 'ventas.create', 'ventas.show', 'ventas.edit']),
                self::enlace('Cola de cobro', 'fa-list-ol', 'cajero.cola', ['cajero.cola*']),
                self::enlace('Cuentas por cobrar', 'fa-hand-holding-usd', 'cuentas-por-cobrar.index', ['cuentas-por-cobrar.*'], self::cuentasPorCobrarVencidas()),
                self::enlace('Devoluciones', 'fa-undo-alt', 'devoluciones.index', ['devoluciones.*']),
                self::enlace('Comisiones', 'fa-percentage', 'comisiones.index', ['comisiones.*']),
                self::enlace('Bitácora de ventas', 'fa-clipboard-list', 'ventas.auditoria'),
            ]),
            self::grupo('precios', 'Precios', 'fa-tags', [
                self::enlace('Gestión de precios', 'fa-tags', 'precios.index', ['precios.*']),
                self::enlace('Tipo de cambio', 'fa-dollar-sign', 'tipo-cambio.index', ['tipo-cambio.*']),
            ]),
            self::grupo('facturacion', 'Facturación', 'fa-file-invoice-dollar', [
                self::enlace('Comprobantes', 'fa-list-alt', 'facturacion.index'),
                self::enlace('Series', 'fa-list-ol', 'facturacion.series'),
                self::enlace('Configuración', 'fa-cog', 'facturacion.configuracion'),
            ]),

            self::titulo('Almacén y compras'),
            self::grupo('inventario', 'Inventario', 'fa-boxes', [
                self::enlace('Productos', 'fa-box', 'inventario.productos.index', ['inventario.productos.index', 'inventario.productos.show', 'inventario.productos.create', 'inventario.productos.edit', 'inventario.productos.variantes*', 'inventario.productos.fotos*', 'inventario.productos.codigos*']),
                self::enlace('Categorías', 'fa-tags', 'inventario.categorias.index', ['inventario.categorias.*']),
                self::enlace('Locales y stock', 'fa-warehouse', 'inventario.almacenes.index', ['inventario.almacenes.*', 'admin.sucursales.*']),
                self::enlace('Movimientos', 'fa-exchange-alt', 'inventario.movimientos.index', ['inventario.movimientos.*']),
                self::enlace('Recepción con escáner', 'fa-barcode', 'inventario.recepcion.index', ['inventario.recepcion.*']),
                self::enlace('Salida con escáner', 'fa-barcode', 'inventario.salida.index', ['inventario.salida.*']),
                self::enlace('Consulta de tienda', 'fa-store', 'inventario.productos.consulta-tienda'),
                self::enlace('Conteo físico', 'fa-clipboard-check', 'inventario-fisico.index', ['inventario-fisico.*']),
            ]),
            self::grupo('traslados', 'Traslados y guías', 'fa-truck-loading', [
                self::enlace('Historial', 'fa-exchange-alt', 'traslados.index'),
                self::enlace('Ver stock', 'fa-boxes', 'traslados.stock'),
                self::enlace('Pendientes', 'fa-clock', 'traslados.pendientes'),
                self::enlace('Nuevo traslado', 'fa-plus', 'traslados.create'),
                self::enlace('Guías de remisión', 'fa-file-invoice', 'guias-remision.index', ['guias-remision.*']),
            ]),
            self::grupo('compras', 'Compras', 'fa-shopping-bag', [
                self::enlace('Proveedores', 'fa-truck', 'proveedores.index', ['proveedores.*']),
                self::enlace('Registrar compras', 'fa-file-invoice', 'compras.index', ['compras.*']),
                self::enlace('Órdenes de compra', 'fa-clipboard-list', 'pedidos.index', ['pedidos.*']),
                self::enlace('Cuentas por pagar', 'fa-credit-card', 'cuentas-por-pagar.index', ['cuentas-por-pagar.*']),
            ]),

            self::titulo('Finanzas'),
            self::grupo('caja', 'Caja', 'fa-cash-register', [
                self::enlace('Caja activa', 'fa-door-open', 'caja.actual'),
                self::enlace('Historial de cajas', 'fa-history', 'caja.index'),
                self::enlace('Caja chica', 'fa-wallet', 'caja-chica.index', ['caja-chica.*']),
                self::enlace('Supervisión de cajas', 'fa-eye', 'admin.cajas.dashboard', ['admin.cajas.*'], self::alertasCajas()),
            ]),
            self::grupo('reportes', 'Reportes', 'fa-chart-line', [
                self::enlace('Ventas y márgenes', 'fa-chart-line', 'reportes.ventas'),
                self::enlace('Compras e importaciones', 'fa-shopping-cart', 'reportes.compras'),
                self::enlace('Stock valorizado', 'fa-coins', 'inventario.reportes.stock-valorizado'),
                self::enlace('Kardex', 'fa-book-open', 'inventario.reportes.kardex'),
                self::enlace('Análisis ABC', 'fa-chart-bar', 'inventario.reportes.abc'),
                self::enlace('Valorización prorrateada', 'fa-balance-scale', 'inventario.reportes.valorizacion-prorateada'),
            ]),

            self::titulo('Equipo'),
            self::grupo('proyectos', 'Proyectos', 'fa-diagram-project', [
                self::enlace('Cronograma', 'fa-calendar-days', 'cronograma.index', ['cronograma.*']),
                self::enlace('Herramientas', 'fa-screwdriver-wrench', 'herramientas.index', ['herramientas.*']),
                self::enlace('Etapas de seguimiento', 'fa-route', 'seguimiento.etapas.index', ['seguimiento.*']),
            ]),
            self::asistencias(true),
            self::grupo('rrhh', 'Recursos humanos', 'fa-id-card', [
                self::enlace('Personal', 'fa-id-card', 'rrhh.empleados.index', ['rrhh.empleados.*', 'rrhh.documentos.*']),
                self::enlace('Solicitudes', 'fa-umbrella-beach', 'rrhh.solicitudes.index', ['rrhh.solicitudes.*'], $solicitudesRrhh),
            ]),

            self::titulo('Sistema'),
            self::grupo('catalogo', 'Catálogo', 'fa-book', [
                self::enlace('Colores', 'fa-palette', 'catalogo.colores.index', ['catalogo.colores.*']),
                self::enlace('Marcas', 'fa-trademark', 'catalogo.marcas.index', ['catalogo.marcas.*']),
                self::enlace('Modelos', 'fa-lightbulb', 'catalogo.modelos.index', ['catalogo.modelos.*']),
                self::enlace('Unidades de medida', 'fa-ruler', 'catalogo.unidades.index', ['catalogo.unidades.*']),
                self::enlace('Motivos de movimiento', 'fa-exchange-alt', 'catalogo.motivos.index', ['catalogo.motivos.*']),
            ]),
            self::grupo('sistema', 'Administración', 'fa-cogs', [
                self::enlace('Usuarios', 'fa-users', 'users.index', ['users.*']),
                self::enlace('Empresa', 'fa-building', 'admin.empresa.edit', ['admin.empresa.*']),
                self::enlace('Mi perfil', 'fa-user-circle', 'profile.edit', ['profile.*']),
            ]),
        ];
    }

    // ── Contador ─────────────────────────────────────────────────────────────

    private static function contador(?int $userId): array
    {
        return [
            self::titulo('Principal'),
            self::dashboard('contador.dashboard'),

            self::titulo('Comercial'),
            self::grupo('cotizaciones', 'Cotizaciones', 'fa-file-contract', [
                self::enlace('Cotizaciones', 'fa-file-contract', 'cotizaciones.index', ['cotizaciones.*']),
                self::enlace('Notas de pedido', 'fa-clipboard-list', 'notas-pedido.index', ['notas-pedido.*']),
            ]),
            self::enlace('Clientes', 'fa-users', 'clientes.index', ['clientes.*']),
            self::grupo('ventas', 'Ventas', 'fa-cash-register', [
                self::enlace('Ventas', 'fa-receipt', 'ventas.index', ['ventas.index', 'ventas.show']),
                self::enlace('Cuentas por cobrar', 'fa-hand-holding-usd', 'cuentas-por-cobrar.index', ['cuentas-por-cobrar.*'], self::cuentasPorCobrarVencidas()),
                self::enlace('Devoluciones', 'fa-undo-alt', 'devoluciones.index', ['devoluciones.*']),
                self::enlace('Bitácora de ventas', 'fa-clipboard-list', 'ventas.auditoria'),
            ]),
            self::grupo('facturacion', 'Facturación', 'fa-file-invoice', [
                self::enlace('Comprobantes', 'fa-list-alt', 'facturacion.index'),
                self::enlace('Series', 'fa-list-ol', 'facturacion.series'),
            ]),

            self::titulo('Finanzas'),
            self::grupo('compras', 'Compras', 'fa-shopping-bag', [
                self::enlace('Compras', 'fa-file-invoice', 'compras.index', ['compras.*']),
                self::enlace('Cuentas por pagar', 'fa-credit-card', 'cuentas-por-pagar.index', ['cuentas-por-pagar.*']),
            ]),
            self::enlace('Finanzas', 'fa-chart-pie', 'finanzas.dashboard', ['finanzas.*']),
            self::enlace('Supervisión de cajas', 'fa-cash-register', 'admin.cajas.dashboard', ['admin.cajas.*'], self::alertasCajas()),
            self::grupo('reportes', 'Reportes', 'fa-chart-line', [
                self::enlace('Ventas y márgenes', 'fa-chart-line', 'reportes.ventas'),
                self::enlace('Compras e importaciones', 'fa-shopping-cart', 'reportes.compras'),
                self::enlace('Inventario', 'fa-coins', 'inventario.reportes.stock-valorizado'),
                self::enlace('Comisiones', 'fa-percentage', 'comisiones.reporte'),
            ]),

            self::titulo('Equipo'),
            self::asistencias(false),
            ...self::cajaChicaPropia($userId),
        ];
    }

    // ── Logística ────────────────────────────────────────────────────────────

    private static function logistica(?int $userId): array
    {
        return [
            self::titulo('Principal'),
            self::dashboard('logistica.dashboard'),
            self::enlace('Mis solicitudes', 'fa-clipboard-check', 'aprobaciones.mias'),

            self::titulo('Almacén y compras'),
            self::grupo('pedidos', 'Pedidos', 'fa-dolly', [
                self::enlace('Notas de pedido', 'fa-clipboard-list', 'notas-pedido.index', ['notas-pedido.*']),
                self::enlace('Pedidos internos', 'fa-dolly', 'pedidos-internos.index', ['pedidos-internos.*']),
            ]),
            self::grupo('inventario', 'Inventario', 'fa-boxes', [
                self::enlace('Productos', 'fa-box', 'inventario.productos.index', ['inventario.productos.index', 'inventario.productos.show', 'inventario.productos.create', 'inventario.productos.edit', 'inventario.productos.variantes*', 'inventario.productos.fotos*', 'inventario.productos.codigos*']),
                self::enlace('Locales y stock', 'fa-warehouse', 'inventario.almacenes.index', ['inventario.almacenes.*']),
                self::enlace('Movimientos', 'fa-exchange-alt', 'inventario.movimientos.index', ['inventario.movimientos.*']),
                self::enlace('Recepción con escáner', 'fa-barcode', 'inventario.recepcion.index', ['inventario.recepcion.*']),
                self::enlace('Salida con escáner', 'fa-barcode', 'inventario.salida.index', ['inventario.salida.*']),
                self::enlace('Consulta de tienda', 'fa-store', 'inventario.productos.consulta-tienda'),
                self::enlace('Conteo físico', 'fa-clipboard-check', 'inventario-fisico.index', ['inventario-fisico.*']),
                self::enlace('Reportes de inventario', 'fa-coins', 'inventario.reportes.stock-valorizado', ['inventario.reportes.*']),
            ]),
            self::grupo('traslados', 'Traslados y guías', 'fa-truck-loading', [
                self::enlace('Historial', 'fa-exchange-alt', 'traslados.index'),
                self::enlace('Ver stock', 'fa-boxes', 'traslados.stock'),
                self::enlace('Pendientes', 'fa-clock', 'traslados.pendientes'),
                self::enlace('Nuevo traslado', 'fa-plus', 'traslados.create'),
                self::enlace('Guías de remisión', 'fa-file-invoice', 'guias-remision.index', ['guias-remision.*']),
            ]),
            self::grupo('compras', 'Compras', 'fa-shopping-bag', [
                self::enlace('Proveedores', 'fa-truck', 'proveedores.index', ['proveedores.*']),
                self::enlace('Registrar compras', 'fa-file-invoice', 'compras.index', ['compras.*']),
                self::enlace('Órdenes de compra', 'fa-clipboard-list', 'pedidos.index', ['pedidos.*']),
                self::enlace('Cuentas por pagar', 'fa-credit-card', 'cuentas-por-pagar.index', ['cuentas-por-pagar.*']),
            ]),
            self::grupo('catalogo', 'Consultar catálogo', 'fa-book', [
                self::enlace('Colores', 'fa-palette', 'catalogo.colores.index', ['catalogo.colores.*']),
                self::enlace('Marcas', 'fa-trademark', 'catalogo.marcas.index', ['catalogo.marcas.*']),
                self::enlace('Modelos', 'fa-lightbulb', 'catalogo.modelos.index', ['catalogo.modelos.*']),
            ]),

            self::titulo('Equipo'),
            self::grupo('proyectos', 'Proyectos', 'fa-diagram-project', [
                self::enlace('Cronograma', 'fa-calendar-days', 'cronograma.index', ['cronograma.*']),
                self::enlace('Herramientas', 'fa-screwdriver-wrench', 'herramientas.index', ['herramientas.*']),
            ]),
            self::asistencias(false),
            ...self::cajaChicaPropia($userId),
        ];
    }

    // ── Operaciones ──────────────────────────────────────────────────────────

    private static function operaciones(?int $userId): array
    {
        return [
            self::titulo('Principal'),
            self::dashboard('operaciones.dashboard'),

            self::titulo('Trabajo'),
            self::enlace('Pedidos internos', 'fa-dolly', 'pedidos-internos.index', ['pedidos-internos.*']),
            self::enlace('Productos y stock', 'fa-boxes', 'inventario.productos.index', ['inventario.productos.*']),
            self::grupo('traslados', 'Traslados y guías', 'fa-truck-loading', [
                self::enlace('Historial', 'fa-exchange-alt', 'traslados.index'),
                self::enlace('Ver stock', 'fa-boxes', 'traslados.stock'),
                self::enlace('Guías de remisión', 'fa-file-invoice', 'guias-remision.index', ['guias-remision.*']),
            ]),
            self::grupo('proyectos', 'Proyectos', 'fa-diagram-project', [
                self::enlace('Cronograma', 'fa-calendar-days', 'cronograma.index', ['cronograma.*']),
                self::enlace('Herramientas', 'fa-screwdriver-wrench', 'herramientas.index', ['herramientas.*']),
            ]),
            self::enlace('Mi caja chica', 'fa-wallet', 'caja-chica.index', ['caja-chica.*']),

            self::titulo('Equipo'),
            self::asistencias(false),
        ];
    }
}
