<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// ===================== AUTH =====================
use App\Http\Controllers\Auth\MasterPasswordController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\DirectPasswordResetController;
use App\Http\Controllers\ProfileController;

// ===================== CORE =====================
use App\Http\Controllers\DashboardController;

// ===================== ADMIN =====================
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\SucursalController;
use App\Http\Controllers\Admin\AdminCajaController;

// ===================== INVENTARIO =====================
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\AlmacenController;

// ===================== NUEVOS MÓDULOS =====================
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\TrasladoController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\PrecioController;
use App\Http\Controllers\TiendaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CuentaPorPagarController;
use App\Http\Controllers\CuentaPorCobrarController;
use App\Http\Controllers\ReporteVentasController;
use App\Http\Controllers\DniLookupController;
use App\Http\Controllers\DevolucionController;

// ===================== MIDDLEWARE =====================
use App\Http\Middleware\VerifyMasterPassword;

Route::get('/', function () {
    return redirect()->route('login');
});

// Página de bloqueo por falta de pago
Route::get('/sistema-suspendido', function () {
    return view('sistema_bloqueado');
})->name('sistema.bloqueado');

// ========================================
// CONSULTA PÚBLICA DE COMPROBANTE (sin auth)
// ========================================
Route::get('/consultas/{codigo}', [VentaController::class, 'consultaPublica'])->name('consultas.venta');
/*
|--------------------------------------------------------------------------
| RUTA PRINCIPAL
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    $role = auth()->user()->role->nombre ?? null;
        return match ($role) {
        \App\Models\Role::ADMINISTRACION => redirect()->route('admin.dashboard'),
        \App\Models\Role::CONTADOR       => redirect()->route('contador.dashboard'),
        \App\Models\Role::LOGISTICA      => redirect()->route('logistica.dashboard'),
        \App\Models\Role::OPERACIONES    => redirect()->route('operaciones.dashboard'),
        \App\Models\Role::CLIENTE        => redirect()->route('cliente.dashboard'),
        default                            => redirect()->route('login'),
    };
})->name('dashboard')->middleware('auth');

/*
|--------------------------------------------------------------------------
| CONTRASEÑA MAESTRA
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/master-password', [MasterPasswordController::class, 'show'])->name('master-password.show');
    Route::post('/master-password', [MasterPasswordController::class, 'verify'])->middleware('throttle:6,1')->name('master-password.verify');
});

/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::middleware(VerifyMasterPassword::class)->group(function () {
        Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/register', [RegisteredUserController::class, 'store']);
        Route::get('/forgot-password', [DirectPasswordResetController::class, 'show'])->name('password.request');
        Route::post('/forgot-password', [DirectPasswordResetController::class, 'update'])->name('password.update-direct');
    });
});

// ========================================
// MÓDULO DE USUARIOS
// ========================================
Route::middleware(['auth', 'role:Administración'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [App\Http\Controllers\UserController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\UserController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\UserController::class, 'store'])->name('store');
    Route::get('/{user}/edit', [App\Http\Controllers\UserController::class, 'edit'])->name('edit');
    Route::get('/{user}', [App\Http\Controllers\UserController::class, 'show'])->name('show');
    Route::put('/{user}', [App\Http\Controllers\UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [App\Http\Controllers\UserController::class, 'destroy'])->name('destroy');
});

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS (AUTH)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DASHBOARDS POR ROL
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:Administración')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
    });

    Route::middleware('role:Contador')->prefix('contador')->name('contador.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'contador'])->name('dashboard');
    });

    Route::middleware('role:Logística')->prefix('logistica')->name('logistica.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'logistica'])->name('dashboard');
    });

    Route::middleware('role:Operaciones')->prefix('operaciones')->name('operaciones.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'operaciones'])->name('dashboard');
    });

    Route::middleware('role:Cliente')->prefix('cliente')->name('cliente.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'cliente'])->name('dashboard');
    });

    // Cola de ventas pendientes de cobro (antes del cajero; ahora la atiende Administración)
    Route::middleware('role:Administración')->prefix('cajero')->name('cajero.')->group(function () {
        Route::get('/cola', [\App\Http\Controllers\VentaController::class, 'colaCaja'])->name('cola');
        Route::get('/cola/json', [\App\Http\Controllers\VentaController::class, 'colaJson'])->name('cola.json');
    });

    // ========================================
    // RUTAS DE PERFIL
    // ========================================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ========================================
    // MÓDULO DE INVENTARIO
    // ========================================
    Route::prefix('inventario')->name('inventario.')->group(function () {
        
        // RECEPCIÓN RÁPIDA CON ESCÁNER (PDA)
        Route::middleware('role:Administración,Logística')->prefix('recepcion')->name('recepcion.')->group(function () {
            Route::get('/', [\App\Http\Controllers\RecepcionEscanerController::class, 'index'])->name('index');
            Route::get('/buscar', [\App\Http\Controllers\RecepcionEscanerController::class, 'buscar'])->name('buscar');
            Route::get('/variantes', [\App\Http\Controllers\RecepcionEscanerController::class, 'variantes'])->name('variantes');
            Route::post('/asociar', [\App\Http\Controllers\RecepcionEscanerController::class, 'asociar'])->name('asociar');
            Route::post('/confirmar', [\App\Http\Controllers\RecepcionEscanerController::class, 'confirmar'])->name('confirmar');
        });

        // SALIDA RÁPIDA CON ESCÁNER (PDA): reutiliza la búsqueda y asociación de códigos de la recepción
        Route::middleware('role:Administración,Logística')->prefix('salida-escaner')->name('salida.')->group(function () {
            Route::get('/', [\App\Http\Controllers\RecepcionEscanerController::class, 'salida'])->name('index');
            Route::post('/confirmar', [\App\Http\Controllers\RecepcionEscanerController::class, 'retirar'])->name('confirmar');
        });

        // CATEGORÍAS
        Route::middleware('role:Administración,Logística')->group(function () {
            Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
            Route::get('/categorias/create', [CategoriaController::class, 'create'])->name('categorias.create');
            Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
            Route::get('/categorias/{categoria}/edit', [CategoriaController::class, 'edit'])->name('categorias.edit');
            Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
            Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->middleware('role:Administración')->name('categorias.destroy');
            Route::get('/categorias/{categoria}', [CategoriaController::class, 'show'])->name('categorias.show');
        });

        // PRODUCTOS
            Route::middleware('role:Administración,Logística')->group(function () {
                Route::get('/productos/create', [ProductoController::class, 'create'])->name('productos.create');
                Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
                Route::get('/productos/{producto}/edit', [ProductoController::class, 'edit'])->name('productos.edit');
                Route::put('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
                Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->middleware('role:Administración')->name('productos.destroy');
                Route::patch('/productos/{producto}/reactivar', [ProductoController::class, 'reactivar'])->middleware('role:Administración')->name('productos.reactivar');
                
                // Gestión de códigos de barras múltiples
                Route::get('/productos/{producto}/codigos-barras', [ProductoController::class, 'codigosBarras'])->name('productos.codigos-barras');
                Route::post('/productos/{producto}/codigos-barras', [ProductoController::class, 'storeCodigoBarras'])->name('productos.codigos-barras.store');
                Route::delete('/productos/codigos-barras/{codigoBarras}', [ProductoController::class, 'destroyCodigoBarras'])->name('productos.codigos-barras.destroy');
                Route::post('/productos/codigos-barras/{codigoBarras}/principal', [ProductoController::class, 'setPrincipalCodigoBarras'])->name('productos.codigos-barras.principal');
                // 🔴 NUEVAS RUTAS PARA GENERAR CÓDIGOS DE BARRAS
                Route::post('/productos/generar-codigo-barras', [ProductoController::class, 'generarCodigoBarras'])->name('productos.generar-codigo-barras');
                Route::get('/productos/validar-codigo-barras', [ProductoController::class, 'validarCodigoBarras'])->name('productos.validar-codigo-barras');
                // Asociar un proveedor al producto (no hay pantalla de listado ni de desasociar)
                Route::post('/productos/{producto}/proveedores', [ProductoController::class, 'asociarProveedor'])->name('productos.proveedores.asociar');
            });

            Route::middleware('role:Administración,Logística,Operaciones')->group(function () {
                Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
                Route::get('/productos/buscar', [ProductoController::class, 'buscarAjax'])->name('productos.buscar-ajax');
                Route::get('/productos/consulta-tienda', [ProductoController::class, 'consultaTienda'])->middleware('role:Administración,Logística')->name('productos.consulta-tienda');
                Route::get('/productos/{producto}', [ProductoController::class, 'show'])->name('productos.show');
            });
        // MOVIMIENTOS DE INVENTARIO
        Route::middleware('role:Administración,Logística')->group(function () {
            Route::get('/movimientos', [MovimientoInventarioController::class, 'index'])->name('movimientos.index');
            Route::get('/movimientos/create', [MovimientoInventarioController::class, 'create'])->name('movimientos.create');
            Route::post('/movimientos', [MovimientoInventarioController::class, 'store'])->name('movimientos.store');
            Route::get('/movimientos/{movimiento}', [MovimientoInventarioController::class, 'show'])->name('movimientos.show');
            Route::get('/api/stock-actual', [MovimientoInventarioController::class, 'getStockActual'])->name('movimientos.stock-actual');
        });

        // ALMACENES
        Route::middleware('role:Administración,Logística')->group(function () {
            Route::get('/almacenes', [AlmacenController::class, 'index'])->name('almacenes.index');
            Route::get('/almacenes/create', [AlmacenController::class, 'create'])->name('almacenes.create');
            Route::post('/almacenes', [AlmacenController::class, 'store'])->name('almacenes.store');
            Route::get('/almacenes/{almacen}', [AlmacenController::class, 'show'])->name('almacenes.show');
            Route::get('/almacenes/{almacen}/edit', [AlmacenController::class, 'edit'])->name('almacenes.edit');
            Route::put('/almacenes/{almacen}', [AlmacenController::class, 'update'])->name('almacenes.update');
            Route::delete('/almacenes/{almacen}', [AlmacenController::class, 'destroy'])->middleware('role:Administración')->name('almacenes.destroy');
        });

        // VARIANTES DE PRODUCTOS
        Route::middleware('role:Administración,Logística')->group(function () {
            Route::get('/productos/{producto}/variantes', [ProductoController::class, 'variantes'])->name('productos.variantes');
            Route::post('/productos/{producto}/variantes', [ProductoController::class, 'storeVariante'])->name('productos.variantes.store');
            Route::put('/productos/variantes/{variante}', [ProductoController::class, 'updateVariante'])->name('productos.variantes.update');
            Route::delete('/productos/variantes/{variante}', [ProductoController::class, 'destroyVariante'])->name('productos.variantes.destroy');
            Route::post('/productos/variantes/{variante}/imagenes', [ProductoController::class, 'storeImagenes'])->name('productos.variantes.imagenes.store');
            Route::delete('/productos/imagenes/{imagen}', [ProductoController::class, 'destroyImagen'])->name('productos.imagenes.destroy');
            Route::post('/productos/imagenes/{imagen}/principal', [ProductoController::class, 'principalImagen'])->name('productos.imagenes.principal');
        });

        // REPORTES DE INVENTARIO (HU-INVENTARIO-06/07/08): Contador los ve todos; Logística los de su inventario
        Route::middleware('role:Administración,Contador,Logística')->group(function () {
            Route::get('/reportes/stock-valorizado',        [\App\Http\Controllers\Inventario\InventarioReportesController::class, 'stockValorizado'])->name('reportes.stock-valorizado');
            Route::get('/reportes/kardex',                  [\App\Http\Controllers\Inventario\InventarioReportesController::class, 'kardex'])->name('reportes.kardex');
            Route::get('/reportes/abc',                     [\App\Http\Controllers\Inventario\InventarioReportesController::class, 'analisisAbc'])->name('reportes.abc');
            Route::get('/reportes/valorizacion-prorateada', [\App\Http\Controllers\Inventario\InventarioReportesController::class, 'valorizacionProrateada'])->name('reportes.valorizacion-prorateada');
        });

    });

    // ========================================
    // MÓDULO DE PROVEEDORES
    // ========================================
    Route::prefix('proveedores')->name('proveedores.')->middleware('role:Administración,Logística')->group(function () {
        Route::get('/', [ProveedorController::class, 'index'])->name('index');
        Route::get('/create', [ProveedorController::class, 'create'])->name('create');
        Route::post('/', [ProveedorController::class, 'store'])->name('store');
        Route::get('/{proveedor}', [ProveedorController::class, 'show'])->name('show');
        Route::get('/{proveedor}/edit', [ProveedorController::class, 'edit'])->name('edit');
        Route::put('/{proveedor}', [ProveedorController::class, 'update'])->name('update');
        Route::delete('/{proveedor}', [ProveedorController::class, 'destroy'])->middleware('role:Administración')->name('destroy');
        Route::post('/consultar-sunat', [ProveedorController::class, 'consultarSunat'])->name('consultar-sunat');
    });

    // ========================================
    // MÓDULO DE CLIENTES
    // ========================================
    Route::prefix('clientes')->name('clientes.')->middleware('role:Administración')->group(function () {
        Route::get('/create', [ClienteController::class, 'create'])->name('create');
        Route::get('/difusion/resumen', [ClienteController::class, 'resumenDifusion'])->name('difusion.resumen');
        Route::get('/difusion/exportar', [ClienteController::class, 'exportarDifusion'])->name('difusion.exportar');
        Route::post('/', [ClienteController::class, 'store'])->name('store');
        Route::get('/{cliente}/edit', [ClienteController::class, 'edit'])->name('edit');
        Route::put('/{cliente}', [ClienteController::class, 'update'])->name('update');
        Route::delete('/{cliente}', [ClienteController::class, 'destroy'])->name('destroy');
        Route::post('/consultar-documento', [ClienteController::class, 'consultarDocumento'])->name('consultar-documento');
    });
    // Lectura: Administración y Contador (el listado, la exportación y la ficha)
    Route::prefix('clientes')->name('clientes.')->middleware('role:Administración,Contador')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('index');
        Route::get('/exportar', [ClienteController::class, 'exportar'])->name('exportar');
        Route::get('/{cliente}', [ClienteController::class, 'show'])->whereNumber('cliente')->name('show');
    });

    // ========================================
    // MÓDULO DE PEDIDOS
    // ========================================
    Route::prefix('pedidos')->name('pedidos.')->middleware('role:Administración,Logística')->group(function () {
        Route::get('/', [PedidoController::class, 'index'])->name('index');
        Route::get('/create', [PedidoController::class, 'create'])->name('create');
        Route::post('/', [PedidoController::class, 'store'])->name('store');
        Route::get('/{pedido}', [PedidoController::class, 'show'])->name('show');
        Route::patch('/{pedido}/estado', [PedidoController::class, 'cambiarEstado'])->name('cambiar-estado');
    });

    // ========================================
    // MÓDULO DE COMPRAS
    // ========================================
    Route::prefix('compras')->name('compras.')->middleware('role:Administración,Logística')->group(function () {
        Route::get('/create', [CompraController::class, 'create'])->name('create');
        Route::post('/', [CompraController::class, 'store'])->name('store');
        Route::get('buscar-productos', [CompraController::class, 'buscarProductos'])->name('buscar-productos');
        Route::get('producto/{id}', [CompraController::class, 'getProductoDetalle'])->name('producto-detalle');
        Route::post('crear-producto-rapido', [CompraController::class, 'crearProductoRapido'])->name('crear-producto-rapido');
        Route::get('tipo-cambio', [CompraController::class, 'tipoCambio'])->name('tipo-cambio');
        Route::get('/{compra}/edit', [CompraController::class, 'edit'])->name('edit');
        Route::put('/{compra}', [CompraController::class, 'update'])->name('update');
        Route::post('/{compra}/anular', [CompraController::class, 'anular'])->name('anular');
        Route::post('/{compra}/recalcular-prorrateo', [CompraController::class, 'recalcularProrrateo'])->name('recalcular-prorrateo');
        Route::delete('/{compra}', [CompraController::class, 'destroy'])->name('destroy');
        Route::delete('/{compra}/detalle/{detalle}', [CompraController::class, 'destroyDetalle'])->name('detalle.destroy');

    });
    // Lectura de compras: también el Contador (después de las rutas fijas para que «create» no se tome como id)
    Route::prefix('compras')->name('compras.')->middleware('role:Administración,Contador,Logística')->group(function () {
        Route::get('/', [CompraController::class, 'index'])->name('index');
        Route::get('/{compra}', [CompraController::class, 'show'])->name('show');
    });
   // ========================================
    // MÓDULO DE CUENTAS POR PAGAR
    // ========================================
    Route::prefix('cuentas-por-pagar')->name('cuentas-por-pagar.')->group(function () {
        $c = App\Http\Controllers\CuentaPorPagarController::class;

        // Registrar pagos: Administración y Contador
        Route::middleware('role:Administración,Contador')->group(function () use ($c) {
            Route::post('/cuotas/{cuota}/pagar', [$c, 'pagarCuota'])->name('cuotas.pagar');
            Route::post('/{cuenta}/registrar-pago', [$c, 'registrarPago'])->name('registrar-pago');
            Route::post('/{cuenta}/generar-cuotas', [$c, 'generarCuotas'])->name('generar-cuotas');
        });

        // Ver: además Logística (compras y proveedores)
        Route::middleware('role:Administración,Contador,Logística')->group(function () use ($c) {
            Route::get('/', [$c, 'index'])->name('index');
            Route::get('/{cuenta}', [$c, 'show'])->name('show');
        });
    });

    // Dashboard financiero
    Route::get('/finanzas', [App\Http\Controllers\CuentaPorPagarController::class, 'dashboard'])->middleware('role:Administración,Contador,Logística')->name('finanzas.dashboard');
    // ========================================
    // CAJA CHICA (general y asignadas al personal, con foto o escaneo de comprobantes)
    // ========================================
    Route::prefix('caja-chica')->name('caja-chica.')->middleware('role:Administración,Contador,Logística,Operaciones')->group(function () {
        $c = \App\Http\Controllers\CajaChicaController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/nueva', [$c, 'create'])->name('create');
        Route::post('/', [$c, 'store'])->name('store');
        Route::get('/movimientos/{mov}/editar', [$c, 'editarGasto'])->name('gasto.edit');
        Route::put('/movimientos/{mov}', [$c, 'actualizarGasto'])->name('gasto.update');
        Route::delete('/movimientos/{mov}', [$c, 'eliminarMovimiento'])->name('movimiento.destroy');
        Route::post('/movimientos/{mov}/revisar', [$c, 'revisar'])->name('revisar');
        Route::get('/adjuntos/{adjunto}', [$c, 'adjunto'])->name('adjunto');
        Route::delete('/adjuntos/{adjunto}', [$c, 'eliminarAdjunto'])->name('adjunto.destroy');
        Route::get('/{caja}', [$c, 'show'])->name('show');
        Route::post('/{caja}/ingreso', [$c, 'ingreso'])->name('ingreso');
        Route::post('/{caja}/gasto', [$c, 'gasto'])->name('gasto');
        Route::post('/{caja}/cerrar', [$c, 'cerrar'])->name('cerrar');
        Route::get('/{caja}/rendicion', [$c, 'rendicion'])->name('rendicion');
        Route::get('/{caja}/exportar', [$c, 'exportar'])->name('exportar');
    });
    // ========================================
    // MÓDULO DE COTIZACIONES (propio; se convierten a nota de venta, boleta o factura)
    // ========================================
    Route::prefix('cotizaciones')->name('cotizaciones.')->middleware('role:Administración')->group(function () {
        $c = \App\Http\Controllers\CotizacionController::class;
        Route::get('/create', [$c, 'create'])->name('create');
        Route::post('/', [$c, 'store'])->name('store');
        // Búsquedas JSON del formulario: antes del comodín /{cotizacion}
        Route::get('/api/productos', [$c, 'productos'])->name('productos');
        Route::get('/api/clientes', [$c, 'clientes'])->name('clientes');
        Route::get('/{cotizacion}/edit', [$c, 'edit'])->name('edit');
        Route::put('/{cotizacion}', [$c, 'update'])->name('update');
        Route::post('/{cotizacion}/estado', [$c, 'estado'])->name('estado');
        Route::post('/{cotizacion}/renovar', [$c, 'renovar'])->name('renovar');
        Route::post('/{cotizacion}/duplicar', [$c, 'duplicar'])->name('duplicar');
        Route::post('/{cotizacion}/convertir', [$c, 'convertir'])->name('convertir');
    });
    // Lectura: Administración y Contador
    Route::prefix('cotizaciones')->name('cotizaciones.')->middleware('role:Administración,Contador')->group(function () {
        $c = \App\Http\Controllers\CotizacionController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/{cotizacion}', [$c, 'show'])->name('show');
        Route::get('/{cotizacion}/pdf', [$c, 'pdf'])->name('pdf');
    });

    // ========================================
    // MÓDULO DE VENTAS
    // ========================================
    Route::prefix('ventas')->name('ventas.')->middleware('role:Administración')->group(function () {
        Route::get('/create', [VentaController::class, 'create'])->name('create');
        Route::post('/', [VentaController::class, 'store'])->name('store');
        Route::get('/api/mis-pendientes', [VentaController::class, 'misPendientes'])->name('mis-pendientes');
        Route::get('/api/dni/{dni}', [DniLookupController::class, 'buscar'])->name('dni.buscar');
        Route::get('/api/ruc/{ruc}', [TrasladoController::class, 'buscarRuc'])->name('ruc.buscar');
        Route::post('/{venta}/confirmar-pago', [VentaController::class, 'confirmarPago'])->name('confirmar-pago');
        // Verificación de clave antes de editar, anular o eliminar
        Route::post('/{venta}/verificar-clave', [VentaController::class, 'verificarClave'])->name('verificar-clave');
        Route::post('/{venta}/nota-credito', [VentaController::class, 'generarNotaCredito'])->name('nota-credito');
        Route::get('/{venta}/edit', [VentaController::class, 'editVenta'])->name('edit');
        Route::put('/{venta}', [VentaController::class, 'updateVenta'])->name('update');
        Route::post('/{venta}/anular', [VentaController::class, 'anularVenta'])->name('anular');
        Route::delete('/{venta}', [VentaController::class, 'eliminarVenta'])->name('destroy');
    });
    // Lectura (Contador incluido) y registro de pagos de crédito
    Route::prefix('ventas')->name('ventas.')->middleware('role:Administración,Contador')->group(function () {
        Route::get('/', [VentaController::class, 'index'])->name('index');
        // Bitácora de auditoría: debe ir antes del comodín /{venta}
        Route::get('/auditoria', [VentaController::class, 'bitacora'])->name('auditoria');
        Route::get('/{venta}', [VentaController::class, 'show'])->name('show');
        Route::get('/{venta}/pdf', [VentaController::class, 'pdf'])->name('pdf');
        Route::get('/{venta}/guia-pdf', [VentaController::class, 'guiaPdf'])->name('guia-pdf');
        Route::get('/{venta}/credito', [VentaController::class, 'showCredito'])->name('credito.show');
        Route::post('/{venta}/credito/pago', [VentaController::class, 'registrarPagoCredito'])->name('credito.pago');
    });

    // Cuentas por cobrar (crédito a clientes)
    Route::prefix('cuentas-por-cobrar')->name('cuentas-por-cobrar.')->middleware('role:Administración,Contador')->group(function () {
        Route::get('/', [CuentaPorCobrarController::class, 'index'])->name('index');
        Route::get('/{cuentaPorCobrar}', [CuentaPorCobrarController::class, 'show'])->name('show');
    });
    // ========================================
    // MÓDULO DE REPORTES DE VENTAS
    // ========================================
    Route::middleware('role:Administración,Contador')->prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/ventas',     [ReporteVentasController::class, 'index'])->name('ventas');
        Route::get('/ventas/csv', [ReporteVentasController::class, 'exportCsv'])->name('ventas.csv');
        Route::get('/ventas/pdf', [ReporteVentasController::class, 'exportPdf'])->name('ventas.pdf');

        Route::get('/compras',     [\App\Http\Controllers\ReporteComprasController::class, 'index'])->name('compras');
        Route::get('/compras/csv', [\App\Http\Controllers\ReporteComprasController::class, 'exportCsv'])->name('compras.csv');
    });

    // ========================================
    // MÓDULO DE FACTURACIÓN ELECTRÓNICA
    // ========================================
    Route::middleware('role:Administración,Contador')->prefix('facturacion')->name('facturacion.')->group(function () {
        $c = \App\Http\Controllers\FacturacionElectronicaController::class;
        Route::get('/',             [$c, 'index'])->name('index');
        Route::get('/series',       [$c, 'series'])->name('series');
        Route::post('/{venta}/reenviar',       [$c, 'reenviar'])->name('reenviar');
        Route::post('/{venta}/enviar-sunat',   [$c, 'enviarSunat'])->name('enviar-sunat');
        Route::post('/{venta}/consultar-sunat',[$c, 'consultarSunat'])->name('consultar-sunat');
        Route::get('/{venta}/xml',             [$c, 'downloadXml'])->name('xml');
    });
    // Configuración de series y estado de guía: solo Administración
    Route::middleware('role:Administración')->prefix('facturacion')->name('facturacion.')->group(function () {
        $c = \App\Http\Controllers\FacturacionElectronicaController::class;
        Route::post('/series',      [$c, 'storeSerie'])->name('series.store');
        Route::put('/series/{serie}',   [$c, 'updateSerie'])->name('series.update');
        Route::delete('/series/{serie}',[$c, 'destroySerie'])->name('series.destroy');
        Route::patch('/{venta}/guia-estado',   [$c, 'updateEstadoGuia'])->name('guia-estado');
        Route::get('/configuracion', [$c, 'configuracion'])->name('configuracion');
    });

    // ========================================
    // MÓDULO DE TIENDA (Inventario entre tiendas)
    // ========================================
    Route::middleware(['auth', 'role:Administración'])->prefix('tienda')->name('tienda.')->group(function () {
        Route::get('/inventario', [TiendaController::class, 'inventario'])->name('inventario.ver');
        Route::get('/solicitudes', [TiendaController::class, 'solicitudes'])->name('inventario.solicitudes');
        Route::post('/solicitar-traslado', [TiendaController::class, 'solicitarTraslado'])->name('inventario.solicitar-traslado');
        Route::post('/solicitudes/{traslado}/cancelar', [TiendaController::class, 'cancelarSolicitud'])->name('inventario.cancelar-solicitud');
        Route::get('/producto/{producto}', [TiendaController::class, 'verProducto'])->name('producto.ver');
        Route::get('/get-stock', [TiendaController::class, 'getStockProducto'])->name('get-stock');
    });
    // ========================================
    // MÓDULO DE TRASLADOS
    // ========================================
    Route::prefix('traslados')->name('traslados.')->middleware('auth')->group(function () {
        // Crear, recibir y anular: Administración y Logística
        Route::middleware('role:Administración,Logística')->group(function () {
            Route::get('/create', [TrasladoController::class, 'create'])->name('create');
            Route::post('/', [TrasladoController::class, 'store'])->name('store');
            Route::get('/buscar-productos', [TrasladoController::class, 'buscarProductos'])->name('buscar-productos');
            Route::get('/buscar-destinatario', [TrasladoController::class, 'buscarDestinatario'])->name('buscar-destinatario');
            Route::get('/api/ruc/{ruc}', [TrasladoController::class, 'buscarRuc'])->name('api.ruc');
            Route::get('/pendientes', [TrasladoController::class, 'pendientes'])->name('pendientes');
            Route::post('/{traslado}/confirmar', [TrasladoController::class, 'confirmar'])->name('confirmar');
            // Anular: decisión más sensible que iniciarlo o recibirlo
            Route::post('/{traslado}/anular', [TrasladoController::class, 'anular'])->name('anular');
        });

        // Eliminar es más sensible aún (oculta del historial): solo Administración
        Route::middleware('role:Administración')->group(function () {
            Route::delete('/{traslado}', [TrasladoController::class, 'eliminar'])->name('eliminar');
        });

        // Lectura: Operaciones también (después de las rutas fijas)
        Route::middleware('role:Administración,Logística,Operaciones')->group(function () {
            Route::get('/', [TrasladoController::class, 'index'])->name('index');
            Route::get('/stock', [TrasladoController::class, 'stock'])->name('stock');
            Route::get('/{traslado}', [TrasladoController::class, 'show'])->name('show');
            Route::get('/{traslado}/guia-pdf', [TrasladoController::class, 'guiaPdf'])->name('guia-pdf');
        });
    });

    // ========================================
    // MÓDULO GUÍAS DE REMISIÓN
    // ========================================
    Route::prefix('guias-remision')->name('guias-remision.')->middleware(['auth'])->group(function () {
        $c = \App\Http\Controllers\GuiaRemisionController::class;

        // Emitir y gestionar (también el envío a SUNAT): Administración y Logística
        Route::middleware('role:Administración,Logística')->group(function () use ($c) {
            Route::get('/create',              [$c, 'create'])->name('create');
            Route::post('/',                   [$c, 'store'])->name('store');
            // Antes que «/{guiasRemision}»: si no, «buscar-destinatario» se toma como el id de una guía y da 404
            Route::get('/buscar-destinatario', [$c, 'buscarDestinatario'])->name('buscar-destinatario');
            Route::get('/api/ruc/{ruc}',       [$c, 'buscarRuc'])->name('api.ruc');
            Route::get('/api/dni/{dni}',       [\App\Http\Controllers\DniLookupController::class, 'buscar'])->name('api.dni');
            Route::patch('/{guiasRemision}/estado',         [$c, 'updateEstado'])->name('update-estado');
            Route::post('/{guiasRemision}/enviar-sunat',    [$c, 'enviarSunat'])->name('enviar-sunat');
            Route::post('/{guiasRemision}/consultar-sunat', [$c, 'consultarSunat'])->name('consultar-sunat');
        });

        // Lectura: Operaciones también
        Route::middleware('role:Administración,Logística,Operaciones')->group(function () use ($c) {
            Route::get('/',                    [$c, 'index'])->name('index');
            Route::get('/{guiasRemision}',     [$c, 'show'])->name('show');
            Route::get('/{guiasRemision}/pdf', [$c, 'pdf'])->name('pdf');
        });
    });

    // ========================================
    // MÓDULO DE INVENTARIO FÍSICO (CONTEO)
    // ========================================
    Route::prefix('inventario-fisico')->name('inventario-fisico.')->middleware('role:Administración,Logística')->group(function () {
        Route::get('/',                                   [\App\Http\Controllers\InventarioFisicoController::class, 'index'])->name('index');
        Route::get('/create',                             [\App\Http\Controllers\InventarioFisicoController::class, 'create'])->name('create');
        Route::post('/',                                  [\App\Http\Controllers\InventarioFisicoController::class, 'store'])->name('store');
        Route::get('/{conteo}',                           [\App\Http\Controllers\InventarioFisicoController::class, 'show'])->name('show');
        Route::patch('/{conteo}/detalles/{detalle}',      [\App\Http\Controllers\InventarioFisicoController::class, 'updateDetalle'])->name('detalle.update');
        Route::post('/{conteo}/reiniciar',                [\App\Http\Controllers\InventarioFisicoController::class, 'reiniciar'])->name('reiniciar');
        Route::get('/{conteo}/pdf',                       [\App\Http\Controllers\InventarioFisicoController::class, 'exportPdf'])->name('pdf');
        Route::get('/{conteo}/excel',                     [\App\Http\Controllers\InventarioFisicoController::class, 'exportExcel'])->name('excel');
    });

    // ========================================
    // MÓDULO DE COMISIONES
    // ========================================
    // El Contador consulta el reporte de comisiones (solo lectura)
    Route::get('/comisiones/reporte', [\App\Http\Controllers\ComisionController::class, 'reporte'])->middleware('role:Administración,Contador')->name('comisiones.reporte');

    Route::prefix('comisiones')->name('comisiones.')->middleware('role:Administración')->group(function () {
        Route::get('/',                [\App\Http\Controllers\ComisionController::class, 'index'])->name('index');
        Route::post('/',               [\App\Http\Controllers\ComisionController::class, 'store'])->name('store');
        Route::put('/{regla}',         [\App\Http\Controllers\ComisionController::class, 'update'])->name('update');
        Route::delete('/{regla}',      [\App\Http\Controllers\ComisionController::class, 'destroy'])->name('destroy');
        Route::patch('/{regla}/toggle',[\App\Http\Controllers\ComisionController::class, 'toggle'])->name('toggle');
        Route::post('/recalcular',     [\App\Http\Controllers\ComisionController::class, 'recalcular'])->name('recalcular');
        Route::get('/progreso-bonos',  [\App\Http\Controllers\ComisionController::class, 'progresoBonos'])->name('progreso-bonos');
        Route::post('/marcar-pagado',  [\App\Http\Controllers\ComisionController::class, 'marcarPagado'])->name('marcar-pagado');
        // Bonus
        Route::post('/bonus',                    [\App\Http\Controllers\ComisionController::class, 'bonusStore'])->name('bonus.store');
        Route::put('/bonus/{bonus}',             [\App\Http\Controllers\ComisionController::class, 'bonusUpdate'])->name('bonus.update');
        Route::delete('/bonus/{bonus}',          [\App\Http\Controllers\ComisionController::class, 'bonusDestroy'])->name('bonus.destroy');
        Route::patch('/bonus/{bonus}/toggle',    [\App\Http\Controllers\ComisionController::class, 'bonusToggle'])->name('bonus.toggle');
        Route::post('/marcar-bonus-pagado',      [\App\Http\Controllers\ComisionController::class, 'marcarBonusPagado'])->name('marcar-bonus-pagado');
    });

    // ========================================
    // MÓDULO DE DEVOLUCIONES
    // ========================================
    Route::prefix('devoluciones')->name('devoluciones.')->middleware('role:Administración')->group(function () {
        Route::get('/create', [DevolucionController::class, 'create'])->name('create');
        Route::post('/', [DevolucionController::class, 'store'])->name('store');
        Route::post('/{devolucion}/anular', [DevolucionController::class, 'anular'])->name('anular');
    });
    // Lectura: Administración y Contador
    Route::prefix('devoluciones')->name('devoluciones.')->middleware('role:Administración,Contador')->group(function () {
        Route::get('/', [DevolucionController::class, 'index'])->name('index');
        Route::get('/{devolucion}', [DevolucionController::class, 'show'])->name('show');
    });

    // ========================================
    // MÓDULO DE CAJA
    // ========================================
    Route::prefix('caja')->name('caja.')->middleware('role:Administración')->group(function () {
        Route::get('/', [CajaController::class, 'index'])->name('index');
        Route::get('/abrir', [CajaController::class, 'abrir'])->name('abrir');
        Route::post('/abrir', [CajaController::class, 'store'])->name('store');
        Route::get('/actual', [CajaController::class, 'actual'])->name('actual');
        Route::post('/cerrar', [CajaController::class, 'cerrar'])->name('cerrar');
        Route::post('/ingreso', [CajaController::class, 'registrarIngreso'])->name('ingreso');
        Route::post('/gasto', [CajaController::class, 'registrarGasto'])->name('gasto');
        Route::get('/movimientos', [CajaController::class, 'movimientos'])->name('movimientos');
        Route::get('/{caja}', [CajaController::class, 'show'])->name('show');
    });

    // ========================================
    // MÓDULO DE CATÁLOGO
    // ========================================
    Route::prefix('catalogo')->name('catalogo.')->middleware('role:Administración,Logística')->group(function () {
        // Creación rápida desde formularios (deben ir ANTES de los resource)
        Route::post('marcas/rapida',  [App\Http\Controllers\Catalogo\MarcaController::class,  'storeRapida'])->name('marcas.rapida');
        Route::post('modelos/rapida', [App\Http\Controllers\Catalogo\ModeloController::class, 'storeRapida'])->name('modelos.rapida');
        Route::post('colores/rapida', [App\Http\Controllers\Catalogo\ColorController::class,  'storeRapida'])->name('colores.rapida');
        Route::post('unidades/rapida', [App\Http\Controllers\Catalogo\UnidadMedidaController::class,  'storeRapida'])->name('unidades.rapida');

        Route::resource('colores', App\Http\Controllers\Catalogo\ColorController::class)->parameters(['colores' => 'color'])->except(['show']);
        Route::resource('motivos', App\Http\Controllers\Catalogo\MotivoMovimientoController::class)->parameters(['motivos' => 'motivo'])->except(['show']);
        Route::resource('unidades', App\Http\Controllers\Catalogo\UnidadMedidaController::class)->parameters(['unidades' => 'unidade'])->except(['show']);
        Route::resource('marcas', App\Http\Controllers\Catalogo\MarcaController::class)->parameters(['marcas' => 'marca'])->except(['show']);
        Route::resource('modelos', App\Http\Controllers\Catalogo\ModeloController::class)->parameters(['modelos' => 'modelo'])->except(['show']);
        Route::get('modelos-por-marca/{marcaId}', [App\Http\Controllers\Catalogo\ModeloController::class, 'getModelosPorMarca'])->name('modelos.por-marca');
        Route::get('marcas-por-categoria/{categoriaId}', [App\Http\Controllers\Catalogo\MarcaController::class, 'getMarcasPorCategoria'])->name('marcas.por-categoria');

        Route::get('productos-sunat/buscar', [App\Http\Controllers\Catalogo\ProductoSunatController::class, 'buscar'])->name('productos-sunat.buscar');
    });

    // ========================================
    // MÓDULO ADMINISTRACIÓN (empresa y sucursales: solo Administración; cajas: el Contador las consulta)
    // ========================================
    Route::middleware('role:Administración')->prefix('admin')->name('admin.')->group(function () {
        // Empresa (singleton)
        Route::get('/empresa', [EmpresaController::class, 'edit'])->name('empresa.edit');
        Route::put('/empresa', [EmpresaController::class, 'update'])->name('empresa.update');
        Route::get('/consultar-ruc/{ruc}', [EmpresaController::class, 'consultarRuc'])->name('empresa.consultar-ruc');

        // Sucursales
        Route::get('/sucursales', [SucursalController::class, 'index'])->name('sucursales.index');
        Route::get('/sucursales/create', [SucursalController::class, 'create'])->name('sucursales.create');
        Route::post('/sucursales', [SucursalController::class, 'store'])->name('sucursales.store');
        Route::get('/sucursales/{sucursal}/edit', [SucursalController::class, 'edit'])->name('sucursales.edit');
        Route::put('/sucursales/{sucursal}', [SucursalController::class, 'update'])->name('sucursales.update');
        Route::delete('/sucursales/{sucursal}', [SucursalController::class, 'destroy'])->name('sucursales.destroy');
        Route::get('/sucursales/{sucursal}/comprobantes', [SucursalController::class, 'comprobantes'])->name('sucursales.comprobantes');
        Route::post('/sucursales/{sucursal}/generar-series', [SucursalController::class, 'generarSeries'])->name('sucursales.generar-series');

        // Series de comprobantes de una sucursal
        Route::put('/sucursales/{sucursal}/series/{serie}', [SucursalController::class, 'updateSerie'])->name('sucursales.series.update');
        Route::post('/sucursales/{sucursal}/series', [SucursalController::class, 'storeSerie'])->name('sucursales.series.store');

        // Pagos digitales de una sucursal
        Route::put('/sucursales/{sucursal}/pagos', [SucursalController::class, 'updatePagos'])->name('sucursales.pagos.update');

        // Almacenes secundarios de una sucursal
        Route::post('/sucursales/{sucursal}/almacenes', [SucursalController::class, 'storeAlmacen'])->name('sucursales.almacenes.store');

        // ── Admin Caja (acciones): solo Administración ───────────────────────
        Route::get('/cajas/apertura-remota', [AdminCajaController::class, 'aperturaRemota'])->name('cajas.apertura-remota');
        Route::post('/cajas/apertura-remota', [AdminCajaController::class, 'storeAperturaRemota'])->name('cajas.apertura-remota.store');
        Route::post('/cajas/{caja}/forzar-cierre', [AdminCajaController::class, 'forzarCierre'])->name('cajas.forzar-cierre');
        Route::post('/cajas/{caja}/ajustar-diferencia', [AdminCajaController::class, 'ajustarDiferencia'])->name('cajas.ajustar-diferencia');
    });

    // Supervisión de cajas: lectura para Administración y Contador
    Route::middleware('role:Administración,Contador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/cajas/dashboard', [AdminCajaController::class, 'dashboard'])->name('cajas.dashboard');
        Route::get('/cajas/alertas', [AdminCajaController::class, 'alertas'])->name('cajas.alertas');
        Route::get('/cajas/reportes', [AdminCajaController::class, 'reportes'])->name('cajas.reportes');
        Route::get('/cajas', [AdminCajaController::class, 'index'])->name('cajas.index');
    });
    Route::middleware('role:Administración,Contador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/cajas/{caja}', [AdminCajaController::class, 'show'])->name('cajas.show');
    });


    // ========================================
    // MÓDULO DE PRECIOS
    // ========================================
    Route::prefix('precios')->name('precios.')->middleware('role:Administración,Logística')->group(function () {
        Route::get('/', [PrecioController::class, 'index'])->name('index');
        Route::get('/proveedores/buscar', [PrecioController::class, 'buscarProveedores'])->name('proveedores.buscar');
        Route::get('/producto/{producto}/ultimo-precio-compra', [PrecioController::class, 'ultimoPrecioCompra'])->name('ultimo-precio-compra');
        Route::get('/producto/{producto}', [PrecioController::class, 'show'])->name('show');
        Route::post('/producto/{producto}', [PrecioController::class, 'store'])->name('store');
        Route::post('/producto/{producto}/calcular', [PrecioController::class, 'calcular'])->name('calcular');
        // Acciones masivas para precios
        Route::post('/producto/{producto}/bulk-action', [PrecioController::class, 'bulkAction'])->name('bulk-action');
        Route::post('/producto/{producto}/precio/{precio}/restore', [PrecioController::class, 'restoreSingle'])->name('restore-single');
        Route::get('/producto/{producto}/precio/{precio}/edit', [PrecioController::class, 'edit'])->name('edit');
        Route::put('/producto/{producto}/precio/{precio}', [PrecioController::class, 'update'])->name('update');
        Route::get('/producto/{producto}/historial', [PrecioController::class, 'historial'])->name('historial');
    });

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

// ========================================
// API INTERNAS (para AJAX del POS y escáner)
// ========================================
Route::prefix('api')->name('api.')->middleware(['auth', 'role:Administración,Contador,Logística,Operaciones'])->group(function () {

    // Búsqueda de productos por código (para escáner)
    Route::get('/productos/buscar', [App\Http\Controllers\Api\ProductoController::class, 'buscarPorCodigo'])
        ->name('productos.buscar');

    // Buscar cliente por documento
    Route::get('/clientes/buscar', [App\Http\Controllers\Api\ClienteController::class, 'buscarPorDocumento'])
        ->name('clientes.buscar');

    // Obtener tipo de cambio del día
    Route::get('/tipo-cambio', [App\Http\Controllers\Api\TipoCambioController::class, 'obtener'])
        ->name('tipo-cambio');

    // ── VARIANTES ──────────────────────────────────────────────────────────────
    Route::prefix('variantes')->name('variantes.')->group(function () {
        // Variantes de un producto base
        Route::get('/producto/{producto}', [App\Http\Controllers\Api\VarianteController::class, 'porProducto'])
            ->name('por-producto');

        // CRUD de variante individual
        Route::post('/', [App\Http\Controllers\Api\VarianteController::class, 'store'])
            ->middleware('role:Administración,Logística')
            ->name('store');

        Route::get('/{variante}', [App\Http\Controllers\Api\VarianteController::class, 'show'])
            ->name('show');

        Route::put('/{variante}', [App\Http\Controllers\Api\VarianteController::class, 'update'])
            ->middleware('role:Administración,Logística')
            ->name('update');

        Route::delete('/{variante}', [App\Http\Controllers\Api\VarianteController::class, 'destroy'])
            ->middleware('role:Administración,Logística')
            ->name('destroy');

        // Stock de variante
        Route::get('/{variante}/stock', [App\Http\Controllers\Api\VarianteController::class, 'stock'])
            ->name('stock');

    });
});

