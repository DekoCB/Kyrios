<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CajaService
{
    /**
     * Retorna la caja abierta de un día anterior (si existe).
     * Útil para mostrar advertencias en dashboards.
     */
    public function cajaAtrasada(?int $userId = null): ?Caja
    {
        $userId = $userId ?? auth()->id();
        return Caja::where('user_id', $userId)
            ->where('estado', 'abierta')
            ->whereDate('fecha', '<', now()->toDateString())
            ->with(['almacen', 'sucursal'])
            ->first();
    }

    /**
     * Retorna todas las cajas abiertas de días anteriores (para admin).
     */
    public function cajasAtrasadasTodas(): \Illuminate\Database\Eloquent\Collection
    {
        return Caja::where('estado', 'abierta')
            ->whereDate('fecha', '<', now()->toDateString())
            ->with(['usuario', 'almacen', 'sucursal'])
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Abrir caja para un usuario.
     * Si no se pasa almacen_id, se toma del usuario.
     */
    public function abrirCaja(
        int $userId,
        ?int $almacenId,
        float $montoInicial,
        ?string $observaciones = null
    ): Caja {
        $cajaAbierta = Caja::where('user_id', $userId)
            ->where('estado', 'abierta')
            ->first();

        if ($cajaAbierta) {
            // Si la caja abierta es de un día anterior, dar mensaje específico
            if ($cajaAbierta->fecha < now()->toDateString()) {
                $fechaFormateada = \Carbon\Carbon::parse($cajaAbierta->fecha)
                    ->locale('es')
                    ->isoFormat('D [de] MMMM [de] YYYY');
                throw new \Exception(
                    "Tienes una caja del {$fechaFormateada} que no fue cerrada. " .
                    "Debes cerrarla antes de abrir una nueva caja para hoy."
                );
            }
            throw new \Exception('Ya tienes una caja abierta hoy. Ciérrala antes de abrir una nueva.');
        }

        if (!$almacenId) {
            $user = User::find($userId);
            $almacenId = $user?->almacen_id;
            if (!$almacenId) {
                throw new \Exception('El usuario no tiene un almacén asignado. Contacta al administrador.');
            }
        }

        // Obtener sucursal_id directamente del almacén (Almacen belongsTo Sucursal)
        $almacen    = Almacen::find($almacenId);
        $sucursalId = $almacen?->sucursal_id;

        return Caja::create([
            'user_id'                => $userId,
            'almacen_id'             => $almacenId,
            'sucursal_id'            => $sucursalId,
            'fecha'                  => now()->toDateString(),
            'fecha_apertura'         => now(),
            'monto_inicial'          => $montoInicial,
            'monto_final'            => $montoInicial,
            'estado'                 => 'abierta',
            'observaciones_apertura' => $observaciones,
        ]);
    }

    /**
     * Registrar movimiento de caja (ingreso o egreso).
     */
    public function registrarMovimiento(
        int $cajaId,
        string $tipo,
        float $monto,
        string $concepto,
        ?int $ventaId = null,
        ?int $compraId = null,
        ?string $observaciones = null,
        ?string $metodoPago = null,
        ?string $referencia = null
    ): MovimientoCaja {
        return DB::transaction(function () use (
            $cajaId, $tipo, $monto, $concepto,
            $ventaId, $compraId, $observaciones, $metodoPago, $referencia
        ) {
            $caja = Caja::findOrFail($cajaId);

            if ($caja->estado !== 'abierta') {
                throw new \Exception('La caja está cerrada. No se pueden registrar movimientos.');
            }

            $movimiento = MovimientoCaja::create([
                'caja_id'       => $cajaId,
                'user_id'       => auth()->id(),
                'venta_id'      => $ventaId,
                'compra_id'     => $compraId,
                'tipo'          => $tipo,
                'metodo_pago'   => $metodoPago ?? 'efectivo',
                'monto'         => $monto,
                'concepto'      => $concepto,
                'referencia'    => $referencia,
                'observaciones' => $observaciones,
            ]);

            if ($tipo === 'ingreso') {
                $caja->increment('monto_final', $monto);
            } else {
                $caja->decrement('monto_final', $monto);
            }

            return $movimiento;
        });
    }

    /**
     * Cerrar caja con arqueo.
     */
    public function cerrarCaja(
        int $cajaId,
        float $montoRealEfectivo,
        ?string $observaciones = null
    ): Caja {
        return DB::transaction(function () use ($cajaId, $montoRealEfectivo, $observaciones) {
            $caja = Caja::with('movimientos')->findOrFail($cajaId);

            if ($caja->estado !== 'abierta') {
                throw new \Exception('Esta caja ya está cerrada.');
            }

            $arqueo     = $this->getArqueo($caja);
            $diferencia = $montoRealEfectivo - $arqueo['saldo_esperado'];

            $caja->update([
                'estado'               => 'cerrada',
                'fecha_cierre'         => now(),
                'monto_real_cierre'    => $montoRealEfectivo,
                'diferencia_cierre'    => $diferencia,
                'observaciones_cierre' => $observaciones,
            ]);

            if (abs($diferencia) >= 0.01) {
                MovimientoCaja::create([
                    'caja_id'     => $cajaId,
                    'user_id'     => auth()->id(),
                    'tipo'        => $diferencia > 0 ? 'ingreso' : 'egreso',
                    'monto'       => abs($diferencia),
                    'concepto'    => $diferencia > 0 ? 'Sobrante en cierre de caja' : 'Faltante en cierre de caja',
                    'metodo_pago' => 'efectivo',
                ]);
            }

            return $caja->fresh('movimientos');
        });
    }

    /**
     * Caja donde se registró el ingreso original de una venta pagada (todos
     * los movimientos de una misma venta —incluidos los de un pago mixto—
     * quedan en la misma caja porque se crean en un solo lote). Null si esa
     * venta nunca llegó a generar un ingreso de caja.
     */
    public function cajaDeVenta(int $ventaId): ?Caja
    {
        $cajaId = MovimientoCaja::where('venta_id', $ventaId)
            ->where('tipo', 'ingreso')
            ->value('caja_id');

        return $cajaId ? Caja::find($cajaId) : null;
    }

    /**
     * Escribe un movimiento de corrección (devolución, anulación,
     * eliminación) y actualiza monto_final, funcionando tanto si la caja
     * sigue abierta (igual que registrarMovimiento) como si ya está
     * cerrada. registrarMovimiento() bloquea escribir en una caja cerrada
     * a propósito para operaciones normales del día a día; esto es
     * explícitamente una corrección retroactiva, así que además recalcula
     * diferencia_cierre reutilizando getArqueo(), que ya sabe qué métodos
     * de pago afectan el conteo físico de efectivo y cuáles no.
     */
    public function registrarCorreccion(
        Caja $caja,
        string $tipo,
        float $monto,
        string $concepto,
        ?int $ventaId,
        string $metodoPago,
        ?string $referencia = null
    ): MovimientoCaja {
        if ($caja->estado === 'abierta') {
            return $this->registrarMovimiento(
                $caja->id, $tipo, $monto, $concepto, $ventaId, null, null, $metodoPago, $referencia
            );
        }

        $movimiento = MovimientoCaja::create([
            'caja_id'     => $caja->id,
            'user_id'     => auth()->id(),
            'venta_id'    => $ventaId,
            'tipo'        => $tipo,
            'metodo_pago' => $metodoPago,
            'monto'       => $monto,
            'concepto'    => $concepto . ' [caja ya cerrada — corrección retroactiva]',
            'referencia'  => $referencia,
        ]);

        $tipo === 'ingreso' ? $caja->increment('monto_final', $monto) : $caja->decrement('monto_final', $monto);

        $this->recalcularDiferenciaCierre($caja);

        return $movimiento;
    }

    /**
     * Vuelve a calcular diferencia_cierre de una caja cerrada a partir de su
     * arqueo actual (monto_real_cierre es el conteo físico ya hecho, eso no
     * cambia; lo que se corrige es cuánto se ESPERABA según los movimientos
     * vigentes ahora mismo).
     */
    public function recalcularDiferenciaCierre(Caja $caja): void
    {
        $arqueo = $this->getArqueo($caja->fresh('movimientos'));
        $caja->update(['diferencia_cierre' => (float) $caja->monto_real_cierre - $arqueo['saldo_esperado']]);
    }

    /**
     * Obtener la caja activa del usuario.
     */
    public function cajaActiva(?int $userId = null): ?Caja
    {
        $userId = $userId ?? auth()->id();
        return Caja::where('user_id', $userId)
            ->where('estado', 'abierta')
            ->with(['almacen', 'sucursal', 'movimientos.venta'])
            ->first();
    }

    /**
     * Resumen de arqueo (breakdown por método de pago).
     */
    public function getArqueo(Caja $caja): array
    {
        $movimientos = $caja->movimientos;

        $ingresos = $movimientos->where('tipo', 'ingreso');
        $egresos  = $movimientos->where('tipo', 'egreso');

        $ventasEfectivo      = $ingresos->whereNotNull('venta_id')->where('metodo_pago', 'efectivo')->sum('monto');
        $ventasYape          = $ingresos->whereNotNull('venta_id')->where('metodo_pago', 'yape')->sum('monto');
        $ventasPlin          = $ingresos->whereNotNull('venta_id')->where('metodo_pago', 'plin')->sum('monto');
        $ventasTransferencia = $ingresos->whereNotNull('venta_id')->where('metodo_pago', 'transferencia')->sum('monto');
        $ventasMixto         = $ingresos->whereNotNull('venta_id')->where('metodo_pago', 'mixto')->sum('monto');

        // El "saldo esperado (efectivo)" reconcilia el CONTEO FÍSICO del cajón, así
        // que solo puede moverse por movimientos en efectivo. Una devolución o la
        // reversión de una venta anulada pagada por Yape/Plin/Transferencia/Mixto
        // no saca billetes del cajón, así que no debe restar de aquí (antes se
        // restaba igual, y eso inflaba el "sobrante" al cerrar caja). Lo mismo
        // aplica a ingresos manuales registrados en un método distinto a efectivo.
        $ingresosManual      = $ingresos->whereNull('venta_id')
            ->whereNotIn('concepto', ['Sobrante en cierre de caja'])
            ->where('metodo_pago', 'efectivo')
            ->sum('monto');
        $egresosTotal        = $egresos->whereNotIn('concepto', ['Faltante en cierre de caja'])
            ->where('metodo_pago', 'efectivo')
            ->sum('monto');

        // total_ventas incluye todos los métodos de pago (efectivo, yape, plin, transferencia, mixto)
        $totalVentas = $ingresos->whereNotNull('venta_id')->sum('monto');
        $saldoEsperado = $caja->monto_inicial + $ventasEfectivo + $ingresosManual - $egresosTotal;

        return [
            'monto_inicial'        => (float) $caja->monto_inicial,
            'ventas_efectivo'      => (float) $ventasEfectivo,
            'ventas_yape'          => (float) $ventasYape,
            'ventas_plin'          => (float) $ventasPlin,
            'ventas_transferencia' => (float) $ventasTransferencia,
            'ventas_mixto'         => (float) $ventasMixto,
            'total_ventas'         => (float) $totalVentas,
            'ingresos_manual'      => (float) $ingresosManual,
            'total_ingresos'       => (float) ($totalVentas + $ingresosManual),
            'total_egresos'        => (float) $egresosTotal,
            'saldo_esperado'       => (float) $saldoEsperado,
            // Un pago mixto genera varios movimientos de caja para la MISMA venta
            // (uno por método); contar movimientos en vez de ventas distintas
            // duplicaba el conteo mostrado en "Mi Caja".
            'num_ventas'           => $ingresos->whereNotNull('venta_id')->pluck('venta_id')->unique()->count(),
        ];
    }
}
