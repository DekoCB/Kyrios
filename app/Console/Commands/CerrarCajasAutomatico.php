<?php

namespace App\Console\Commands;

use App\Models\Caja;
use App\Services\CajaService;
use Illuminate\Console\Command;

class CerrarCajasAutomatico extends Command
{
    protected $signature   = 'caja:cerrar-automatico {--dry-run : Solo muestra qué cajas se cerrarían sin cerrarlas}';
    protected $description = 'Cierra automáticamente todas las cajas abiertas del día anterior (se ejecuta a medianoche hora Lima)';

    public function handle(): int
    {
        $hoy = now()->timezone('America/Lima')->toDateString();

        $cajas = Caja::where('estado', 'abierta')
            ->whereDate('fecha', '<', $hoy)
            ->with(['usuario', 'almacen', 'movimientos'])
            ->get();

        if ($cajas->isEmpty()) {
            $this->info('No hay cajas abiertas de días anteriores. Nada que cerrar.');
            return 0;
        }

        $this->info("Encontradas {$cajas->count()} caja(s) para cerrar automáticamente.");

        if ($this->option('dry-run')) {
            foreach ($cajas as $caja) {
                $this->line(" - Caja #{$caja->id} | {$caja->fecha} | {$caja->usuario?->name} | {$caja->almacen?->nombre}");
            }
            $this->warn('Modo dry-run: no se realizaron cambios.');
            return 0;
        }

        $servicio = app(CajaService::class);
        $cerradas = 0;

        foreach ($cajas as $caja) {
            try {
                // Mismo arqueo y mismo cierre que el manual: solo el efectivo cuenta para el cajón.
                $esperado = $servicio->getArqueo($caja)['saldo_esperado'];
                $servicio->cerrarCaja($caja->id, $esperado, 'Cierre automático al fin del día (sistema)');

                $this->line("  ✓ Cerrada caja #{$caja->id} | {$caja->fecha} | {$caja->usuario?->name}");
                $cerradas++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Error cerrando caja #{$caja->id}: {$e->getMessage()}");
            }
        }

        $this->info("Cierre automático completado: {$cerradas}/{$cajas->count()} caja(s) cerrada(s).");
        return 0;
    }
}
