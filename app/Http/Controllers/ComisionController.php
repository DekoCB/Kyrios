<?php

namespace App\Http\Controllers;

use App\Models\BonusLiquidacion;
use App\Models\BonusRegla;
use App\Models\Categoria;
use App\Models\ComisionDetalleVenta;
use App\Models\ComisionRegla;
use App\Models\Producto;
use App\Models\User;
use App\Services\BonusService;
use Illuminate\Http\Request;

class ComisionController extends Controller
{
    // ══════════════════════════════════════════════════════════════════
    //  COMISIONES — Reglas
    // ══════════════════════════════════════════════════════════════════

    public function index()
    {
        $reglas     = ComisionRegla::with([
            'usuario', 'categoria',
            'producto:id,nombre,costo_promedio',
            'producto.precios' => fn($q) => $q->where('tipo_precio', 'venta_regular')->where('activo', true)->orderByDesc('prioridad'),
        ])->latest()->get();
        $bonusReglas = BonusRegla::with(['producto', 'categoria', 'usuario'])->latest()->get();

        $vendedores = User::whereHas('role', fn($q) => $q->whereIn('nombre', ['Vendedor', 'Tienda', 'Cajero']))->orderBy('name')->get();
        $categorias = Categoria::orderBy('nombre')->get();

        $productosRaw = Producto::where('estado', 'activo')
            ->with(['precios' => fn($q) => $q->where('tipo_precio', 'venta_regular')->where('activo', true)->orderByDesc('prioridad')])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'costo_promedio']);

        $productos = $productosRaw->map(fn($p) => (object)[
            'id'             => $p->id,
            'nombre'         => $p->nombre,
            'codigo'         => $p->codigo,
            'costo_promedio' => (float) ($p->costo_promedio ?? 0),
            'precio_venta'   => (float) ($p->precios->first()?->precio ?? 0),
        ]);

        $totalComisionPendiente = ComisionDetalleVenta::where('estado', 'pendiente')->sum('monto_comision');
        $totalBonusPendiente    = BonusLiquidacion::where('estado', 'pendiente')->sum('monto_bonus');

        return view('comisiones.index', compact(
            'reglas', 'bonusReglas', 'vendedores', 'categorias', 'productos',
            'totalComisionPendiente', 'totalBonusPendiente'
        ));
    }

    public function store(Request $request)
    {
        // El modal envía el vendedor de "producto + vendedor" y "categoría + vendedor"
        // en campos aparte (user_id_producto / user_id_categoria) para no colisionar
        // con el <select name="user_id"> del caso "vendedor específico".
        if ($request->tipo_aplicacion === 'producto_usuario') {
            $request->merge(['user_id' => $request->user_id_producto]);
        } elseif ($request->tipo_aplicacion === 'categoria_usuario') {
            $request->merge(['user_id' => $request->user_id_categoria]);
        }

        $request->validate([
            'nombre'           => 'required|string|max:100',
            'tipo_aplicacion'  => 'required|in:usuario,categoria,producto,producto_usuario,categoria_usuario',
            'tipo_calculo'     => 'required|in:porcentaje,monto_fijo,porcentaje_margen',
            'valor'            => 'required|numeric|min:0.0001',
            'user_id'          => 'nullable|required_if:tipo_aplicacion,usuario,producto_usuario,categoria_usuario|exists:users,id',
            'categoria_id'     => 'nullable|required_if:tipo_aplicacion,categoria,categoria_usuario|exists:categorias,id',
            'producto_id'      => 'nullable|required_if:tipo_aplicacion,producto,producto_usuario|exists:productos,id',
        ]);

        $aplicaUsuario   = in_array($request->tipo_aplicacion, ['usuario', 'producto_usuario', 'categoria_usuario'], true);
        $aplicaProducto  = in_array($request->tipo_aplicacion, ['producto', 'producto_usuario'], true);
        $aplicaCategoria = in_array($request->tipo_aplicacion, ['categoria', 'categoria_usuario'], true);

        ComisionRegla::create([
            'nombre'          => $request->nombre,
            'tipo_aplicacion' => $request->tipo_aplicacion,
            'tipo_calculo'    => $request->tipo_calculo,
            'valor'           => $request->valor,
            'user_id'         => $aplicaUsuario   ? $request->user_id     : null,
            'categoria_id'    => $aplicaCategoria ? $request->categoria_id : null,
            'producto_id'     => $aplicaProducto  ? $request->producto_id : null,
            'activo'          => true,
        ]);

        return back()->with('success', 'Regla de comisión creada correctamente.');
    }

    public function update(Request $request, ComisionRegla $regla)
    {
        $request->validate([
            'nombre'       => 'required|string|max:100',
            'tipo_calculo' => 'required|in:porcentaje,monto_fijo,porcentaje_margen',
            'valor'        => 'required|numeric|min:0.0001',
        ]);

        $regla->update($request->only('nombre', 'tipo_calculo', 'valor'));

        return back()->with('success', 'Regla actualizada correctamente.');
    }

    public function destroy(ComisionRegla $regla)
    {
        if (ComisionDetalleVenta::where('regla_id', $regla->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: hay comisiones generadas con esta regla. Desactívala en su lugar.');
        }
        $regla->delete();
        return back()->with('success', 'Regla eliminada.');
    }

    public function toggle(ComisionRegla $regla)
    {
        $regla->update(['activo' => !$regla->activo]);
        return back()->with('success', $regla->activo ? 'Regla activada.' : 'Regla desactivada.');
    }

    /**
     * Aplica las reglas activas a ventas ya pagadas cuyos detalles todavía no
     * tienen comisión generada (típicamente porque la regla se creó después
     * de la venta). No duplica ni modifica comisiones ya existentes.
     */
    public function recalcular(\App\Services\ComisionService $comisionService)
    {
        $creadas = $comisionService->recalcularHistorico();

        return back()->with(
            'success',
            $creadas > 0
                ? "Se generaron {$creadas} comisión(es) nueva(s) sobre ventas anteriores."
                : 'No había ventas pendientes de comisión: todo estaba al día.'
        );
    }

    // ══════════════════════════════════════════════════════════════════
    //  BONUS — Reglas
    // ══════════════════════════════════════════════════════════════════

    public function bonusStore(Request $request)
    {
        // Igual que en store(): el vendedor de "producto + vendedor" llega en un
        // campo aparte para no colisionar con otros selects del mismo formulario.
        if ($request->tipo_aplicacion === 'producto_usuario') {
            $request->merge(['user_id' => $request->user_id_producto]);
        }

        $request->validate([
            'nombre'          => 'required|string|max:100',
            'tipo_aplicacion' => 'required|in:producto,categoria,producto_usuario',
            'tipo_bonus'      => 'required|in:fijo,meta',
            'tipo_calculo'    => 'required|in:monto_fijo,porcentaje_venta',
            'valor'           => 'required|numeric|min:0.0001',
            'producto_id'     => 'nullable|required_if:tipo_aplicacion,producto,producto_usuario|exists:productos,id',
            'categoria_id'    => 'nullable|required_if:tipo_aplicacion,categoria|exists:categorias,id',
            'user_id'         => 'nullable|required_if:tipo_aplicacion,producto_usuario|exists:users,id',
            'meta_unidades'   => 'nullable|required_if:tipo_bonus,meta|integer|min:1',
            'meta_periodo'    => 'nullable|in:mensual,quincenal,semanal',
        ], [
            'meta_unidades.required_if' => 'Las unidades mínimas son requeridas para bonos por meta.',
            'producto_id.required_if'   => 'Selecciona un producto.',
            'categoria_id.required_if'  => 'Selecciona una categoría.',
            'user_id.required_if'       => 'Selecciona un vendedor.',
        ]);

        $aplicaProducto = in_array($request->tipo_aplicacion, ['producto', 'producto_usuario'], true);

        BonusRegla::create([
            'nombre'          => $request->nombre,
            'tipo_aplicacion' => $request->tipo_aplicacion,
            'tipo_bonus'      => $request->tipo_bonus,
            'tipo_calculo'    => $request->tipo_calculo,
            'valor'           => $request->valor,
            'producto_id'     => $aplicaProducto ? $request->producto_id : null,
            'categoria_id'    => $request->tipo_aplicacion === 'categoria' ? $request->categoria_id : null,
            'user_id'         => $request->tipo_aplicacion === 'producto_usuario' ? $request->user_id : null,
            'meta_unidades'   => $request->tipo_bonus === 'meta' ? $request->meta_unidades : null,
            'meta_periodo'    => $request->tipo_bonus === 'meta' ? ($request->meta_periodo ?? 'mensual') : null,
            'activo'          => true,
        ]);

        return back()->with('success', 'Regla de bono creada correctamente.');
    }

    public function bonusUpdate(Request $request, BonusRegla $bonus)
    {
        $request->validate([
            'nombre'        => 'required|string|max:100',
            'tipo_calculo'  => 'required|in:monto_fijo,porcentaje_venta',
            'valor'         => 'required|numeric|min:0.0001',
            'meta_unidades' => 'nullable|integer|min:1',
            'meta_periodo'  => 'nullable|in:mensual,quincenal,semanal',
        ]);

        $bonus->update($request->only('nombre', 'tipo_calculo', 'valor', 'meta_unidades', 'meta_periodo'));

        return back()->with('success', 'Bono actualizado correctamente.');
    }

    public function bonusDestroy(BonusRegla $bonus)
    {
        if (BonusLiquidacion::where('bonus_regla_id', $bonus->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: hay bonos generados con esta regla. Desactívala en su lugar.');
        }
        $bonus->delete();
        return back()->with('success', 'Regla de bono eliminada.');
    }

    public function bonusToggle(BonusRegla $bonus)
    {
        $bonus->update(['activo' => !$bonus->activo]);
        return back()->with('success', $bonus->activo ? 'Bono activado.' : 'Bono desactivado.');
    }

    // ══════════════════════════════════════════════════════════════════
    //  REPORTE UNIFICADO
    // ══════════════════════════════════════════════════════════════════

    public function reporte(Request $request)
    {
        $vendedores = User::whereHas('role', fn($q) => $q->whereIn('nombre', ['Vendedor', 'Tienda', 'Cajero']))->orderBy('name')->get();

        // ── Comisiones ──
        $queryComision = ComisionDetalleVenta::with(['vendedor', 'detalleVenta.venta', 'detalleVenta.producto', 'regla']);
        if ($request->filled('user_id'))    $queryComision->where('user_id', $request->user_id);
        if ($request->filled('estado'))     $queryComision->where('estado', $request->estado);
        if ($request->filled('fecha_desde')) $queryComision->whereHas('detalleVenta.venta', fn($q) => $q->whereDate('fecha', '>=', $request->fecha_desde));
        if ($request->filled('fecha_hasta')) $queryComision->whereHas('detalleVenta.venta', fn($q) => $q->whereDate('fecha', '<=', $request->fecha_hasta));

        $comisiones = $queryComision->latest()->paginate(25, ['*'], 'pc')->withQueryString();

        // ── Bonos ──
        $queryBonus = BonusLiquidacion::with(['vendedor', 'regla', 'detalleVenta.venta', 'detalleVenta.producto']);
        if ($request->filled('user_id')) $queryBonus->where('user_id', $request->user_id);
        if ($request->filled('estado'))  $queryBonus->where('estado', $request->estado);
        if ($request->filled('fecha_desde')) $queryBonus->whereDate('created_at', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $queryBonus->whereDate('created_at', '<=', $request->fecha_hasta);

        $bonos = $queryBonus->latest()->paginate(25, ['*'], 'pb')->withQueryString();

        // ── Resumen por vendedor (comisiones + bonos) ──
        $resumenComision = ComisionDetalleVenta::with('vendedor')
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->selectRaw('user_id, SUM(monto_comision) as total, SUM(CASE WHEN estado="pagado" THEN monto_comision ELSE 0 END) as pagado, SUM(CASE WHEN estado="pendiente" THEN monto_comision ELSE 0 END) as pendiente')
            ->groupBy('user_id')
            ->get()->keyBy('user_id');

        $resumenBonus = BonusLiquidacion::with('vendedor')
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->selectRaw('user_id, SUM(monto_bonus) as total, SUM(CASE WHEN estado="pagado" THEN monto_bonus ELSE 0 END) as pagado, SUM(CASE WHEN estado="pendiente" THEN monto_bonus ELSE 0 END) as pendiente')
            ->groupBy('user_id')
            ->get()->keyBy('user_id');

        // ── Resumen mensual por vendedor (comisiones + bonos), para pago mensual ──
        // No se filtra por fecha_desde/fecha_hasta (igual que el resumen de arriba):
        // el objetivo es ver el histórico completo mes a mes y elegir cuál pagar.
        $mensualComision = ComisionDetalleVenta::query()
            ->join('detalle_ventas', 'comision_detalle_venta.detalle_venta_id', '=', 'detalle_ventas.id')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('users', 'users.id', '=', 'comision_detalle_venta.user_id')
            ->when($request->filled('user_id'), fn($q) => $q->where('comision_detalle_venta.user_id', $request->user_id))
            ->selectRaw("DATE_FORMAT(ventas.fecha, '%Y-%m') as mes, comision_detalle_venta.user_id as user_id, users.name as vendedor_nombre,
                SUM(monto_comision) as pagado_y_pendiente,
                SUM(CASE WHEN comision_detalle_venta.estado = 'pagado' THEN monto_comision ELSE 0 END) as pagado,
                SUM(CASE WHEN comision_detalle_venta.estado = 'pendiente' THEN monto_comision ELSE 0 END) as pendiente")
            ->groupBy('mes', 'comision_detalle_venta.user_id', 'users.name')
            ->get();

        $mensualBonus = BonusLiquidacion::query()
            ->join('users', 'users.id', '=', 'bonus_liquidaciones.user_id')
            ->when($request->filled('user_id'), fn($q) => $q->where('bonus_liquidaciones.user_id', $request->user_id))
            ->selectRaw("DATE_FORMAT(bonus_liquidaciones.created_at, '%Y-%m') as mes, bonus_liquidaciones.user_id as user_id, users.name as vendedor_nombre,
                SUM(monto_bonus) as pagado_y_pendiente,
                SUM(CASE WHEN bonus_liquidaciones.estado = 'pagado' THEN monto_bonus ELSE 0 END) as pagado,
                SUM(CASE WHEN bonus_liquidaciones.estado = 'pendiente' THEN monto_bonus ELSE 0 END) as pendiente")
            ->groupBy('mes', 'bonus_liquidaciones.user_id', 'users.name')
            ->get();

        $resumenMensual = collect();
        foreach ($mensualComision as $row) {
            $resumenMensual[$row->mes . '|' . $row->user_id] = [
                'mes' => $row->mes, 'vendedor_nombre' => $row->vendedor_nombre,
                'comision_pendiente' => (float) $row->pendiente, 'comision_pagado' => (float) $row->pagado,
                'bonus_pendiente' => 0.0, 'bonus_pagado' => 0.0,
            ];
        }
        foreach ($mensualBonus as $row) {
            $key = $row->mes . '|' . $row->user_id;
            if (!isset($resumenMensual[$key])) {
                $resumenMensual[$key] = [
                    'mes' => $row->mes, 'vendedor_nombre' => $row->vendedor_nombre,
                    'comision_pendiente' => 0.0, 'comision_pagado' => 0.0,
                    'bonus_pendiente' => 0.0, 'bonus_pagado' => 0.0,
                ];
            }
            $resumenMensual[$key]['bonus_pendiente'] = (float) $row->pendiente;
            $resumenMensual[$key]['bonus_pagado']    = (float) $row->pagado;
        }

        $resumenMensualPorMes = $resumenMensual->values()
            ->sortBy('vendedor_nombre')
            ->groupBy('mes')
            ->sortKeysDesc();

        $todosUserIds = $resumenComision->keys()->merge($resumenBonus->keys())->unique();
        $resumen = $todosUserIds->map(function ($uid) use ($resumenComision, $resumenBonus) {
            $c = $resumenComision[$uid] ?? null;
            $b = $resumenBonus[$uid]    ?? null;
            $vendedor = $c?->vendedor ?? $b?->vendedor;
            return [
                'vendedor'          => $vendedor,
                'comision_pendiente'=> (float) ($c->pendiente ?? 0),
                'comision_pagado'   => (float) ($c->pagado   ?? 0),
                'bonus_pendiente'   => (float) ($b->pendiente ?? 0),
                'bonus_pagado'      => (float) ($b->pagado   ?? 0),
            ];
        })->values();

        return view('comisiones.reporte', compact('comisiones', 'bonos', 'resumen', 'resumenMensualPorMes', 'vendedores'));
    }

    // ══════════════════════════════════════════════════════════════════
    //  PROGRESO DE BONOS POR META (logrado / en progreso)
    // ══════════════════════════════════════════════════════════════════

    public function progresoBonos()
    {
        $progreso = app(BonusService::class)->progresoMetas();

        return view('comisiones.progreso-bonos', compact('progreso'));
    }

    // ══════════════════════════════════════════════════════════════════
    //  MIS COMISIONES (Vendedor / Cajero / Tienda)
    // ══════════════════════════════════════════════════════════════════

    public function misComisiones(Request $request)
    {
        $userId = auth()->id();

        $queryComision = ComisionDetalleVenta::with(['detalleVenta.venta', 'detalleVenta.producto', 'regla'])
            ->where('user_id', $userId);

        if ($request->filled('estado'))      $queryComision->where('estado', $request->estado);
        if ($request->filled('fecha_desde')) $queryComision->whereHas('detalleVenta.venta', fn($q) => $q->whereDate('fecha', '>=', $request->fecha_desde));
        if ($request->filled('fecha_hasta')) $queryComision->whereHas('detalleVenta.venta', fn($q) => $q->whereDate('fecha', '<=', $request->fecha_hasta));

        $comisiones = $queryComision->latest()->paginate(20, ['*'], 'pc')->withQueryString();

        $queryBonus = BonusLiquidacion::with(['regla', 'detalleVenta.venta', 'detalleVenta.producto'])
            ->where('user_id', $userId);

        if ($request->filled('estado'))      $queryBonus->where('estado', $request->estado);
        if ($request->filled('fecha_desde')) $queryBonus->whereDate('created_at', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $queryBonus->whereDate('created_at', '<=', $request->fecha_hasta);

        $bonos = $queryBonus->latest()->paginate(20, ['*'], 'pb')->withQueryString();

        $totales = [
            'comision_pendiente' => ComisionDetalleVenta::where('user_id', $userId)->where('estado', 'pendiente')->sum('monto_comision'),
            'comision_pagado'    => ComisionDetalleVenta::where('user_id', $userId)->where('estado', 'pagado')->sum('monto_comision'),
            'bonus_pendiente'    => BonusLiquidacion::where('user_id', $userId)->where('estado', 'pendiente')->sum('monto_bonus'),
            'bonus_pagado'       => BonusLiquidacion::where('user_id', $userId)->where('estado', 'pagado')->sum('monto_bonus'),
        ];

        return view('comisiones.mis-comisiones', compact('comisiones', 'bonos', 'totales'));
    }

    public function marcarPagado(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:comision_detalle_venta,id']);

        ComisionDetalleVenta::whereIn('id', $request->ids)
            ->where('estado', 'pendiente')
            ->update([
                'estado'             => 'pagado',
                'fecha_pago'         => now()->toDateString(),
                'pagado_por_user_id' => auth()->id(),
            ]);

        return back()->with('success', count($request->ids) . ' comisiones marcadas como pagadas.');
    }

    public function marcarBonusPagado(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:bonus_liquidaciones,id']);

        BonusLiquidacion::whereIn('id', $request->ids)
            ->where('estado', 'pendiente')
            ->update([
                'estado'             => 'pagado',
                'fecha_pago'         => now()->toDateString(),
                'pagado_por_user_id' => auth()->id(),
            ]);

        return back()->with('success', count($request->ids) . ' bonos marcados como pagados.');
    }
}
