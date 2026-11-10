<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cierre automático de cajas: se ejecuta cada día a las 00:01 hora Lima (Perú, UTC-5)
Schedule::command('caja:cerrar-automatico')
    ->dailyAt('00:01')
    ->timezone('America/Lima')
    ->withoutOverlapping()
    ->runInBackground();

// Marcar cuentas por cobrar vencidas: se ejecuta diariamente a las 00:05
Schedule::call(function () {
    \App\Models\CuentaPorCobrar::where('estado', 'vigente')
        ->where('fecha_vencimiento_final', '<', now())
        ->update(['estado' => 'vencido']);
    // También marcar cuotas individuales vencidas
    \App\Models\CuotaCobro::where('estado', 'pendiente')
        ->where('fecha_vencimiento', '<', now())
        ->update(['estado' => 'vencido']);
})
    ->dailyAt('00:05')
    ->timezone('America/Lima')
    ->name('cuentas:marcar-vencidas')
    ->withoutOverlapping();

// Avisos de stock mínimo: cada mañana a las 07:00 hora Lima
Schedule::command('alertas:stock-minimo')
    ->dailyAt('07:00')
    ->timezone('America/Lima')
    ->withoutOverlapping();
