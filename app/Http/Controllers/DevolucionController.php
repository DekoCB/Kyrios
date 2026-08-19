<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\GuiaRemision;
use App\Models\Imei;
use App\Models\MovimientoInventario;
use App\Models\StockAlmacen;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevolucionController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Administrador,Almacenero,Vendedor,Tienda');
    }

    public function index(Request $request)
    {
        $query = MovimientoInventario::query()
            ->selectRaw("
                numero_guia,
                MIN(id)           AS id,
                MIN(almacen_id)   AS almacen_id,
                MIN(user_id)      AS user_id,
                MIN(created_at)   AS created_at,
                MIN(observaciones) AS observaciones,
                COUNT(*)          AS total_items,
                SUM(cantidad)     AS total_cantidad,
                MAX(CASE WHEN estado = 'anulado' THEN 1 ELSE 0 END) AS anulada
            ")
            ->where('tipo_movimiento', 'devolucion')
            ->groupBy('numero_guia');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($w) use ($q) {
                $w->where('numero_guia', 'like', "%{$q}%")
                  ->orWhere('documento_referencia', 'like', "%{$q}%")
                  ->orWhere('observaciones', 'like', "%{$q}%")
                  ->orWhereHas('producto', fn($p) => $p->where('nombre', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        $devoluciones = $query->orderByRaw('MIN(created_at) DESC')
            ->paginate(20)
            ->withQueryString();

        // Cargar nombres sin eager loading (grupos ya aggregados)
        $almacenIds   = $devoluciones->pluck('almacen_id')->filter()->unique();
        $userIds      = $devoluciones->pluck('user_id')->filter()->unique();
        $almacenesMap = Almacen::whereIn('id', $almacenIds)->pluck('nombre', 'id');
        $usersMap     = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');

        // Stats globales (sin filtros)
        $stats = [
            'total_guias'    => MovimientoInventario::where('tipo_movimiento', 'devolucion')
                                    ->where('estado', '!=', 'anulado')
                                    ->distinct('numero_guia')->count('numero_guia'),
            'total_unidades' => MovimientoInventario::where('tipo_movimiento', 'devolucion')
                                    ->where('estado', '!=', 'anulado')
                                    ->sum('cantidad'),
            'hoy'            => MovimientoInventario::where('tipo_movimiento', 'devolucion')
                                    ->where('estado', '!=', 'anulado')
                                    ->whereDate('created_at', today())
                                    ->distinct('numero_guia')->count('numero_guia'),
        ];

        $almacenes = Almacen::activos()->orderBy('nombre')->get();

        return view('devoluciones.index', compact(
            'devoluciones', 'almacenes', 'almacenesMap', 'usersMap', 'stats'
        ));
    }

    public function create(Request $request)
    {
        $clientes  = Cliente::activos()->orderBy('nombre')->get();
        $almacenes = Almacen::activos()->orderBy('nombre')->get();

        $ventas    = collect();
        $clienteId = $request->input('cliente_id');

        if ($clienteId) {
            $ventas = Venta::with([
                    'detalles.producto',
                    'detalles.variante',
                    'detalles.imei',
                    'detalles.movimientosDevolucion',
                ])
                ->where('cliente_id', $clienteId)
                ->where('estado_pago', 'pagado')
                ->orderBy('fecha', 'desc')
                ->get()
                // Solo mostrar ventas que aún tienen algo devolvible
                ->filter(fn($v) => $v->detalles->some(fn($d) => $d->cantidad_disponible > 0));
        }

        return view('devoluciones.create', compact('clientes', 'almacenes', 'ventas', 'clienteId'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'   => 'required|exists:clientes,id',
            'almacen_id'   => 'required|exists:almacenes,id',
            'cantidades'   => 'required|array|min:1',
            'cantidades.*' => 'integer|min:0',
            'observaciones'              => 'nullable|string',
            'guia.motivo_traslado'       => 'nullable|string|max:50',
            'guia.modalidad'             => 'nullable|in:privado,publico',
            'guia.fecha_traslado'        => 'nullable|date',
            'guia.direccion_partida'     => 'nullable|string|max:300',
            'guia.ubigeo_partida'        => 'nullable|string|max:6',
            'guia.direccion_llegada'     => 'nullable|string|max:300',
            'guia.ubigeo_llegada'        => 'nullable|string|max:6',
            'guia.conductor_dni'         => 'nullable|string|max:8',
            'guia.conductor_nombre'      => 'nullable|string|max:200',
            'guia.conductor_licencia'    => 'nullable|string|max:20',
            'guia.placa_vehiculo'        => 'nullable|string|max:20',
        ]);

        // Filtrar solo los que tienen cantidad > 0
        $cantidades = collect($request->cantidades)->filter(fn($c) => (int) $c > 0);

        if ($cantidades->isEmpty()) {
            return back()->withInput()->with('error', 'Debe indicar al menos 1 unidad a devolver.');
        }

        try {
            $resultado = DB::transaction(function () use ($request, $cantidades) {
                $almacenId = $request->almacen_id;

                // Solo consumir un correlativo real de la serie SUNAT '09' cuando de
                // verdad se va a emitir una guía de remisión (se completaron los datos
                // de traslado); si no, usar un tag interno solo para agrupar los
                // movimientos de inventario, sin gastar numeración oficial.
                $emiteGuiaReal = $request->filled('guia.fecha_traslado');
                $guiaResuelta  = $emiteGuiaReal
                    ? app(\App\Services\GuiaRemisionService::class)->resolverNumeroGuia($almacenId)
                    : null;
                $numeroGuia = $guiaResuelta['numero'] ?? ('DEV-' . strtoupper(uniqid()));

                $montosPorVenta = [];

                foreach ($cantidades as $detalleId => $cantidadSolicitada) {
                    $detalle = DetalleVenta::with(['producto', 'variante', 'movimientosDevolucion', 'venta'])
                                           ->findOrFail($detalleId);

                    $disponible = $detalle->cantidad_disponible;

                    if ($disponible <= 0) {
                        throw new \Exception("El producto «{$detalle->producto?->nombre}» ya fue devuelto en su totalidad.");
                    }

                    $cantidad = min((int) $cantidadSolicitada, $disponible);

                    $stockAnterior = StockAlmacen::obtenerOCrear($detalle->producto_id, $almacenId)->cantidad;

                    MovimientoInventario::create([
                        'producto_id'          => $detalle->producto_id,
                        'variante_id'          => $detalle->variante_id,
                        'detalle_venta_id'     => $detalle->id,
                        'almacen_id'           => $almacenId,
                        'user_id'              => auth()->id(),
                        'tipo_movimiento'      => 'devolucion',
                        'cantidad'             => $cantidad,
                        'stock_anterior'       => $stockAnterior,
                        'stock_nuevo'          => $stockAnterior + $cantidad,
                        'motivo'               => 'Devolución de cliente',
                        'documento_referencia' => $detalle->venta?->codigo,
                        'numero_guia'          => $numeroGuia,
                        'observaciones'        => $request->observaciones,
                        'estado'               => 'confirmado',
                    ]);

                    // Para productos tipo serie (IMEI): detalle_ventas.imei_id nunca se
                    // llena al vender (bug histórico), así que no sirve para ubicar la
                    // unidad física. Se busca por venta_id + producto_id (+ variante_id),
                    // igual que en VentaService::revertirStock().
                    $esSerie = $detalle->producto?->tipo_inventario === 'serie';

                    if ($esSerie) {
                        $imeisDevueltos = Imei::where('venta_id', $detalle->venta_id)
                            ->where('producto_id', $detalle->producto_id)
                            ->when($detalle->variante_id, fn($q) => $q->where('variante_id', $detalle->variante_id))
                            ->where('estado_imei', Imei::ESTADO_VENDIDO)
                            ->limit($cantidad)
                            ->pluck('id');

                        if ($imeisDevueltos->count() < $cantidad) {
                            throw new \Exception(
                                "No se encontraron {$cantidad} IMEI(s) vendidos de «{$detalle->producto?->nombre}» para devolver."
                            );
                        }

                        Imei::whereIn('id', $imeisDevueltos)->update([
                            'estado_imei' => Imei::ESTADO_EN_STOCK,
                            'almacen_id'  => $almacenId,
                            'venta_id'    => null,
                            'fecha_venta' => null,
                        ]);

                        $totalStock = Imei::where('producto_id', $detalle->producto_id)
                            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                            ->count();
                        $detalle->producto->update(['stock_actual' => $totalStock]);

                        if ($detalle->variante_id && $detalle->variante) {
                            $varianteStock = Imei::where('producto_id', $detalle->producto_id)
                                ->where('variante_id', $detalle->variante_id)
                                ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                                ->count();
                            $detalle->variante->update(['stock_actual' => $varianteStock]);
                        }
                    } else {
                        // Para productos por cantidad
                        StockAlmacen::obtenerOCrear($detalle->producto_id, $almacenId)
                            ->incrementar($cantidad);

                        if ($detalle->variante) {
                            $detalle->variante->incrementarStock($cantidad);
                        } else {
                            $totalStock = StockAlmacen::where('producto_id', $detalle->producto_id)->sum('cantidad');
                            $detalle->producto->update(['stock_actual' => $totalStock]);
                        }
                    }

                    // Acumular el monto a devolver por venta (una devolución puede
                    // incluir líneas de varias ventas del mismo cliente).
                    if ($detalle->venta && $detalle->venta->estado_pago === 'pagado') {
                        $montosPorVenta[$detalle->venta_id] = ($montosPorVenta[$detalle->venta_id] ?? 0)
                            + $cantidad * (float) $detalle->precio_con_igv;
                    }

                    // Prorratear (o eliminar) la comisión del vendedor por las
                    // unidades devueltas — no se le paga comisión por lo que el
                    // cliente terminó devolviendo.
                    app(\App\Services\ComisionService::class)->recalcularPorDevolucion($detalle);
                }

                if ($emiteGuiaReal) {
                    // Descarta el bloque (conductor vs. transportista) que no
                    // corresponde a la modalidad elegida — este formulario solo
                    // tiene campos de conductor/placa, así que sin esto una
                    // devolución marcada "pública" igual guardaba lo que haya
                    // quedado en esos inputs.
                    GuiaRemision::create(array_merge(
                        [
                            'numero_guia'   => $numeroGuia,
                            'guia_serie_id' => $guiaResuelta['serie_id'],
                            'motivo_traslado' => 'DEVOLUCION',
                        ],
                        app(\App\Services\GuiaRemisionService::class)->sanitizarPorModalidad($request->input('guia', []))
                    ));
                }

                // Registrar el egreso en la caja donde se registró el ingreso
                // original de cada venta (aunque ya esté cerrada) — no en "la
                // que esté abierta ahora", que puede ser otra sesión distinta
                // u otro día. Solo queda pendiente de registro manual si esa
                // venta nunca generó ingreso de caja y tampoco hay ninguna
                // caja abierta a la que atribuir la devolución.
                $cajaPendiente  = false;
                $montoPendiente = 0.0;
                if (!empty($montosPorVenta)) {
                    $cajaService = app(\App\Services\CajaService::class);
                    $metodosValidos = ['efectivo', 'yape', 'plin', 'transferencia', 'mixto'];

                    foreach ($montosPorVenta as $ventaId => $monto) {
                        $caja = $cajaService->cajaDeVenta((int) $ventaId)
                            ?? \App\Models\Caja::where('user_id', auth()->id())->where('estado', 'abierta')->first();

                        if (!$caja) {
                            $cajaPendiente   = true;
                            $montoPendiente += $monto;
                            continue;
                        }

                        $ventaOrigen = \App\Models\Venta::find($ventaId);
                        $metodoPago  = in_array($ventaOrigen->metodo_pago ?? '', $metodosValidos)
                            ? $ventaOrigen->metodo_pago
                            : 'efectivo';
                        $cajaService->registrarCorreccion(
                            $caja,
                            'egreso',
                            round($monto, 2),
                            'Devolución de cliente - Venta #' . ($ventaOrigen->codigo ?? $ventaId),
                            $ventaId,
                            $metodoPago,
                            null
                        );
                    }
                }

                return ['caja_pendiente' => $cajaPendiente, 'monto_pendiente' => $montoPendiente];
            });

            $mensaje = 'Devolución registrada exitosamente.';
            if ($resultado['caja_pendiente']) {
                $mensaje .= ' Aviso: no se encontró ninguna caja asociada a esas ventas — registra manualmente el egreso de S/ '
                    . number_format($resultado['monto_pendiente'], 2) . ' cuando corresponda.';
            }

            return redirect()
                ->route('devoluciones.index')
                ->with('success', $mensaje);

        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error al registrar la devolución: ' . $e->getMessage());
        }
    }

    public function show(MovimientoInventario $devolucion)
    {
        $devolucion->load('producto', 'almacen', 'usuario');

        $todosMovimientos = MovimientoInventario::with(['producto'])
            ->where('numero_guia', $devolucion->numero_guia)
            ->where('tipo_movimiento', 'devolucion')
            ->get();

        $guia = GuiaRemision::where('numero_guia', $devolucion->numero_guia)->first();

        $estaAnulada = $todosMovimientos->contains(fn($m) => $m->estado === 'anulado');

        return view('devoluciones.show', compact('devolucion', 'todosMovimientos', 'guia', 'estaAnulada'));
    }

    /**
     * Anular una devolución: revierte el stock (y los IMEIs) que había
     * devuelto, revierte el egreso de caja que generó, y marca sus
     * movimientos como 'anulado' (no se borran, para no perder rastro
     * de auditoría). Si alguna unidad ya se volvió a vender/trasladar
     * desde entonces, no hay suficiente stock para revertir y se bloquea
     * con un mensaje claro en vez de dejar stock negativo.
     */
    public function anular(Request $request, MovimientoInventario $devolucion)
    {
        $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

        try {
            $resultado = DB::transaction(function () use ($request, $devolucion) {
                $grupo = MovimientoInventario::with(['producto', 'variante', 'detalleVenta.venta'])
                    ->where('numero_guia', $devolucion->numero_guia)
                    ->where('tipo_movimiento', 'devolucion')
                    ->get();

                if ($grupo->isEmpty()) {
                    throw new \Exception('No se encontraron movimientos para esta devolución.');
                }

                if ($grupo->contains(fn($m) => $m->estado === 'anulado')) {
                    throw new \Exception('Esta devolución ya fue anulada.');
                }

                // Si de verdad se emitió una guía de remisión y ya fue transmitida
                // a SUNAT, el sistema no puede comunicar la baja del documento —
                // hay que resolverlo manualmente antes de anular aquí.
                $guia = GuiaRemision::where('numero_guia', $devolucion->numero_guia)->first();
                if ($guia && in_array($guia->sunat_estado, ['enviado', 'aceptado'])) {
                    throw new \Exception(
                        'Esta devolución generó una guía de remisión ya enviada a SUNAT (' . $guia->numero_guia . '). ' .
                        'No puede anularse desde aquí — gestione la baja del documento primero.'
                    );
                }

                $montosPorVenta = [];
                $observacionAnulacion = 'ANULADO' . ($request->filled('motivo') ? ": {$request->motivo}" : '')
                    . ' — por ' . auth()->user()?->name . ' el ' . now()->format('d/m/Y H:i');

                foreach ($grupo as $mov) {
                    $detalle = $mov->detalleVenta;
                    $esSerie = $mov->producto?->tipo_inventario === 'serie';

                    if ($esSerie) {
                        // Igual que al registrar la devolución: no hay trazabilidad
                        // por IMEI individual, así que se toman como máximo $cantidad
                        // unidades que sigan en stock en ese almacén (no necesariamente
                        // las mismas piezas físicas que se devolvieron).
                        $imeisDisponibles = Imei::where('producto_id', $mov->producto_id)
                            ->where('almacen_id', $mov->almacen_id)
                            ->when($mov->variante_id, fn($q) => $q->where('variante_id', $mov->variante_id))
                            ->where('estado_imei', Imei::ESTADO_EN_STOCK)
                            ->limit($mov->cantidad)
                            ->pluck('id');

                        if ($imeisDisponibles->count() < $mov->cantidad) {
                            throw new \Exception(
                                "No se puede anular: solo hay {$imeisDisponibles->count()} de {$mov->cantidad} unidad(es) de «{$mov->producto?->nombre}» " .
                                "aún disponibles (parte ya se volvió a vender o trasladar)."
                            );
                        }

                        Imei::whereIn('id', $imeisDisponibles)->update([
                            'estado_imei' => Imei::ESTADO_VENDIDO,
                            'venta_id'    => $detalle?->venta_id,
                            'fecha_venta' => $detalle?->venta?->fecha ?? now(),
                        ]);

                        $totalStock = Imei::where('producto_id', $mov->producto_id)
                            ->where('estado_imei', Imei::ESTADO_EN_STOCK)->count();
                        $mov->producto->update(['stock_actual' => $totalStock]);

                        if ($mov->variante_id && $mov->variante) {
                            $varianteStock = Imei::where('variante_id', $mov->variante_id)
                                ->where('estado_imei', Imei::ESTADO_EN_STOCK)->count();
                            $mov->variante->update(['stock_actual' => $varianteStock]);
                        }
                    } else {
                        $stockAlm = StockAlmacen::where('producto_id', $mov->producto_id)
                            ->where('almacen_id', $mov->almacen_id)
                            ->first();

                        if (!$stockAlm || $stockAlm->cantidad < $mov->cantidad) {
                            throw new \Exception(
                                "No se puede anular: el stock de «{$mov->producto?->nombre}» ya cambió y no queda suficiente para revertir."
                            );
                        }

                        $stockAlm->decrement('cantidad', $mov->cantidad);

                        if ($mov->variante) {
                            $mov->variante->decrementarStock($mov->cantidad);
                        } else {
                            $totalStock = StockAlmacen::where('producto_id', $mov->producto_id)->sum('cantidad');
                            $mov->producto->update(['stock_actual' => $totalStock]);
                        }
                    }

                    if ($detalle?->venta && $detalle->venta->estado_pago === 'pagado') {
                        $montosPorVenta[$detalle->venta_id] = ($montosPorVenta[$detalle->venta_id] ?? 0)
                            + $mov->cantidad * (float) $detalle->precio_con_igv;
                    }

                    $obsActual = $mov->observaciones;
                    $mov->update([
                        'estado'        => 'anulado',
                        'observaciones' => $obsActual ? "{$obsActual}\n{$observacionAnulacion}" : $observacionAnulacion,
                    ]);

                    // Al anular la devolución, esas unidades vuelven a contar
                    // como vendidas — se recalcula la comisión (puede volver a
                    // subir). No toca comisiones ya pagadas.
                    if ($detalle) {
                        app(\App\Services\ComisionService::class)->recalcularPorDevolucion($detalle);
                    }
                }

                if ($guia) {
                    $guia->update(['estado' => 'anulada']);
                }

                // Revertir el egreso que la devolución había registrado: un
                // ingreso nuevo en la misma caja de origen (aunque ya esté
                // cerrada), dejando rastro visible en vez de borrar el egreso.
                $cajaPendiente  = false;
                $montoPendiente = 0.0;
                if (!empty($montosPorVenta)) {
                    $cajaService    = app(\App\Services\CajaService::class);
                    $metodosValidos = ['efectivo', 'yape', 'plin', 'transferencia', 'mixto'];

                    foreach ($montosPorVenta as $ventaId => $monto) {
                        $caja = $cajaService->cajaDeVenta((int) $ventaId)
                            ?? \App\Models\Caja::where('user_id', auth()->id())->where('estado', 'abierta')->first();

                        if (!$caja) {
                            $cajaPendiente   = true;
                            $montoPendiente += $monto;
                            continue;
                        }

                        $ventaOrigen = \App\Models\Venta::find($ventaId);
                        $metodoPago  = in_array($ventaOrigen->metodo_pago ?? '', $metodosValidos)
                            ? $ventaOrigen->metodo_pago
                            : 'efectivo';
                        $cajaService->registrarCorreccion(
                            $caja,
                            'ingreso',
                            round($monto, 2),
                            'Reversión de devolución - Venta #' . ($ventaOrigen->codigo ?? $ventaId),
                            $ventaId,
                            $metodoPago,
                            null
                        );
                    }
                }

                return ['caja_pendiente' => $cajaPendiente, 'monto_pendiente' => $montoPendiente];
            });

            $mensaje = 'Devolución anulada correctamente. El stock fue revertido.';
            if ($resultado['caja_pendiente']) {
                $mensaje .= ' Aviso: no se encontró ninguna caja asociada — registra manualmente el ingreso de S/ '
                    . number_format($resultado['monto_pendiente'], 2) . ' cuando corresponda.';
            }

            return redirect()->route('devoluciones.index')->with('success', $mensaje);

        } catch (\Exception $e) {
            return back()->with('error', 'Error al anular la devolución: ' . $e->getMessage());
        }
    }
}
