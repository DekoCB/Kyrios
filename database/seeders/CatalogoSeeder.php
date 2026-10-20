<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Catalogo\MotivoMovimiento;
use App\Models\Catalogo\Color;
use App\Models\Catalogo\UnidadMedida;

class CatalogoSeeder extends Seeder
{
    /**
     * Datos base del catálogo. Idempotente: se puede ejecutar múltiples veces sin duplicar.
     */
    public function run(): void
    {
        $this->seedMotivos();
        $this->seedColores();
        $this->seedUnidadesMedida();
        $this->seedMarcas();

        $this->command->info('✅ Catálogo verificado/creado correctamente.');
    }

    private function seedMotivos(): void
    {
        $motivos = [
            ['nombre' => 'Compra a proveedor',      'tipo' => 'ingreso',       'codigo' => 'COMP01'],
            ['nombre' => 'Venta a cliente',          'tipo' => 'salida',        'codigo' => 'VENT01'],
            ['nombre' => 'Devolución de cliente',    'tipo' => 'ingreso',       'codigo' => 'DEV01'],
            ['nombre' => 'Devolución a proveedor',   'tipo' => 'salida',        'codigo' => 'DEVP01'],
            ['nombre' => 'Ajuste de inventario',     'tipo' => 'ajuste',        'codigo' => 'AJUS01'],
            ['nombre' => 'Traslado entre almacenes', 'tipo' => 'transferencia', 'codigo' => 'TRAS01'],
            ['nombre' => 'Muestra gratis',           'tipo' => 'salida',        'codigo' => 'MUES01'],
            ['nombre' => 'Producto dañado',          'tipo' => 'salida',        'codigo' => 'DANI01'],
        ];

        foreach ($motivos as $motivo) {
            MotivoMovimiento::firstOrCreate(
                ['codigo' => $motivo['codigo']],
                ['nombre' => $motivo['nombre'], 'tipo' => $motivo['tipo']]
            );
        }
    }

    private function seedColores(): void
    {
        $colores = [
            ['nombre' => 'Negro',    'codigo_hex' => '#000000'],
            ['nombre' => 'Blanco',   'codigo_hex' => '#FFFFFF'],
            ['nombre' => 'Rojo',     'codigo_hex' => '#FF0000'],
            ['nombre' => 'Azul',     'codigo_hex' => '#0000FF'],
            ['nombre' => 'Verde',    'codigo_hex' => '#00FF00'],
            ['nombre' => 'Amarillo', 'codigo_hex' => '#FFFF00'],
            ['nombre' => 'Gris',     'codigo_hex' => '#808080'],
            ['nombre' => 'Plateado', 'codigo_hex' => '#C0C0C0'],
            ['nombre' => 'Dorado',   'codigo_hex' => '#FFD700'],
            ['nombre' => 'Rosado',   'codigo_hex' => '#FFC0CB'],
        ];

        foreach ($colores as $color) {
            Color::firstOrCreate(
                ['nombre' => $color['nombre']],
                ['codigo_hex' => $color['codigo_hex']]
            );
        }
    }

    private function seedUnidadesMedida(): void
    {
        $unidades = [
            ['nombre' => 'Unidad',     'abreviatura' => 'UND', 'categoria' => 'unidad',   'permite_decimales' => false],
            ['nombre' => 'Kilogramo',  'abreviatura' => 'KG',  'categoria' => 'peso',     'permite_decimales' => true],
            ['nombre' => 'Gramo',      'abreviatura' => 'GR',  'categoria' => 'peso',     'permite_decimales' => true],
            ['nombre' => 'Litro',      'abreviatura' => 'LT',  'categoria' => 'volumen',  'permite_decimales' => true],
            ['nombre' => 'Mililitro',  'abreviatura' => 'ML',  'categoria' => 'volumen',  'permite_decimales' => true],
            ['nombre' => 'Metro',      'abreviatura' => 'MT',  'categoria' => 'longitud', 'permite_decimales' => true],
            ['nombre' => 'Centímetro', 'abreviatura' => 'CM',  'categoria' => 'longitud', 'permite_decimales' => true],
            ['nombre' => 'Pulgada',    'abreviatura' => 'IN',  'categoria' => 'longitud', 'permite_decimales' => true],
            ['nombre' => 'Caja',       'abreviatura' => 'CJA', 'categoria' => 'unidad',   'permite_decimales' => false],
            ['nombre' => 'Pack',       'abreviatura' => 'PCK', 'categoria' => 'unidad',   'permite_decimales' => false],
        ];

        foreach ($unidades as $unidad) {
            UnidadMedida::firstOrCreate(
                ['abreviatura' => $unidad['abreviatura']],
                [
                    'nombre'            => $unidad['nombre'],
                    'categoria'         => $unidad['categoria'],
                    'permite_decimales' => $unidad['permite_decimales'],
                ]
            );
        }
    }

    private function seedMarcas(): void
    {
        // Marcas y códigos de la codificación KYRIOS (tipos de producto y de luminaria incluidos)
        $this->call(KyriosCatalogoSeeder::class);
    }
}
