<?php

namespace App\Console\Commands;

use App\Services\AlertaService;
use Illuminate\Console\Command;

class RevisarFechas extends Command
{
    protected $signature = 'alertas:fechas';

    protected $description = 'Avisa de etapas de órdenes de compra, instalaciones, entregas y pedidos internos que se acercan o se atrasaron';

    public function handle(AlertaService $alertas): int
    {
        $r = $alertas->revisarFechas();
        $this->info("Avisos nuevos: {$r['avisos']}; avisos retirados por quedar sin vigencia: {$r['retirados']}.");

        return self::SUCCESS;
    }
}
