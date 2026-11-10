<?php

namespace App\Console\Commands;

use App\Services\AlertaService;
use Illuminate\Console\Command;

class RevisarStockMinimo extends Command
{
    protected $signature = 'alertas:stock-minimo';

    protected $description = 'Avisa a Administración y Logística de las variantes que llegaron a su stock mínimo';

    public function handle(AlertaService $alertas): int
    {
        $r = $alertas->revisarStockMinimo();
        $this->info("Bajo el mínimo: {$r['bajo_minimo']}; avisos nuevos: {$r['avisos']}; avisos retirados por reposición: {$r['retirados']}.");

        return self::SUCCESS;
    }
}
