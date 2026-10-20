<?php

namespace App\Console\Commands;

use Database\Seeders\CatalogoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Deja la base lista para cargar el catálogo de Kyrios desde cero: vacía TODOS los datos de negocio
 * (productos, variantes, inventario, compras, ventas, clientes, proveedores, sucursales, empresa…).
 *
 * Se conservan: usuarios y roles (se les quita el almacén), la estructura (migrations), el catálogo oficial
 * de códigos SUNAT, y las tablas técnicas (sesiones, caché, colas). Después se vuelven a sembrar los
 * catálogos base (motivos, unidades, tipos de producto/luminaria y marcas con su código).
 *
 * IRREVERSIBLE: haga un respaldo (mysqldump) antes de ejecutarlo.
 */
class PurgarDatosKyrios extends Command
{
    protected $signature = 'kyrios:purgar-datos
                            {--force : Ejecuta sin pedir confirmación interactiva}
                            {--archivos : Borra también los archivos subidos de la empresa anterior (logos, imágenes, QR, comprobantes, certificados)}';

    protected $description = 'Vacía los datos de negocio de la base (IRREVERSIBLE) para cargar el catálogo de Kyrios';

    private const CONSERVAR = [
        'migrations', 'users', 'roles', 'catalogo_productos_sunat',
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    private const CARPETAS_ARCHIVOS = [
        'app/public/categorias', 'app/public/comprobantes', 'app/public/logos', 'app/public/marcas',
        'app/public/modelos', 'app/public/productos', 'app/public/qr', 'app/public/qrs',
        'app/private/certificados', 'debugbar',
    ];

    public function handle(): int
    {
        $base = DB::getDatabaseName();
        $tablas = collect(DB::select('SHOW TABLES'))->map(fn ($t) => array_values((array) $t)[0])
            ->reject(fn ($t) => in_array($t, self::CONSERVAR, true))->values();

        $this->warn("Base de datos: {$base}  ({$this->laravel->environment()})");
        $this->table(['Tabla a vaciar', 'Filas'], $tablas->map(fn ($t) => [$t, DB::table($t)->count()])->filter(fn ($f) => $f[1] > 0)->values()->all());

        if (!$this->option('force')
            && !$this->confirm("Esto BORRA todos esos datos de '{$base}' y no se puede deshacer. ¿Hizo un respaldo y desea continuar?", false)) {
            $this->info('Cancelado. No se modificó nada.');
            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();
        foreach ($tablas as $tabla) {
            DB::table($tabla)->truncate();
        }
        Schema::enableForeignKeyConstraints();

        if (Schema::hasColumn('users', 'almacen_id')) {
            DB::table('users')->update(['almacen_id' => null]);
        }

        if ($this->option('archivos')) {
            foreach (self::CARPETAS_ARCHIVOS as $rel) {
                $ruta = storage_path($rel);
                if (is_dir($ruta)) {
                    File::cleanDirectory($ruta);
                    $this->line("  Vaciada: storage/{$rel}");
                }
            }
        }

        $this->call('db:seed', ['--class' => CatalogoSeeder::class, '--force' => true]);
        $this->info('Listo. Siguiente paso: php artisan productos:importar-kyrios <Productos.xlsx>');

        return self::SUCCESS;
    }
}
