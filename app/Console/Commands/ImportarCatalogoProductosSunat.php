<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarCatalogoProductosSunat extends Command
{
    protected $signature = 'catalogo:importar-productos-sunat';

    protected $description = 'Importa el catálogo oficial SUNAT de Código de Producto (UNSPSC) desde database/data/catalogo_productos_sunat.csv';

    public function handle(): int
    {
        $path = database_path('data/catalogo_productos_sunat.csv');

        if (!file_exists($path)) {
            $this->error("No se encontró el archivo: {$path}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        $this->info('Importando catálogo de productos SUNAT...');
        $bar = $this->output->createProgressBar();
        $bar->start();

        DB::table('catalogo_productos_sunat')->truncate();

        $batch = [];
        $total = 0;
        $now = now();

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);

            $batch[] = [
                'codigo_segmento' => $data['codigo_segmento'],
                'segmento'        => $data['segmento'],
                'codigo_familia'  => $data['codigo_familia'],
                'familia'         => $data['familia'],
                'codigo_clase'    => $data['codigo_clase'],
                'clase'           => $data['clase'],
                'codigo'          => $data['codigo'],
                'producto'        => $data['producto'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];

            if (count($batch) >= 500) {
                DB::table('catalogo_productos_sunat')->insert($batch);
                $total += count($batch);
                $bar->advance(count($batch));
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('catalogo_productos_sunat')->insert($batch);
            $total += count($batch);
            $bar->advance(count($batch));
        }

        fclose($handle);
        $bar->finish();

        $this->newLine(2);
        $this->info("Listo: {$total} registros importados.");

        return self::SUCCESS;
    }
}
