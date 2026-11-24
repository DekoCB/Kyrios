<?php

namespace App\Console\Commands;

use App\Services\CostoService;
use Illuminate\Console\Command;

class RevisarMargenes extends Command
{
    protected $signature = 'alertas:margenes';

    protected $description = 'Avisa de los productos cuyo margen real quedó por debajo de su margen mínimo';

    public function handle(CostoService $costos): int
    {
        $r = $costos->revisarMargenes();
        $this->info("Productos bajo el mínimo: {$r['bajo_minimo']}; avisos nuevos: {$r['avisos']}; retirados: {$r['retirados']}.");

        return self::SUCCESS;
    }
}
