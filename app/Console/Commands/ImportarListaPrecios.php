<?php

namespace App\Console\Commands;

use App\Models\Catalogo\Marca;
use App\Models\Catalogo\TipoLuminaria;
use App\Models\Catalogo\TipoProducto;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Services\CodigoKyriosService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Aplica la «Lista de precios KYRIOS» (ya convertida a JSON) al sistema.
 *
 *  - Cada fila se busca por código de fábrica (manda sobre el KLU, que en el sistema está repetido en algunos productos);
 *    solo si la fábrica no existe se prueba con el código KLU.
 *  - Existente: precio de venta regular (USD con IGV, que es el «sin IGV» de la lista × 1.18: el sistema entero —punto de
 *    venta, comisiones, reportes— da por hecho que el precio regular ya incluye IGV) con su costo en almacén, precio de oferta
 *    (USD con IGV) y precio Oechsle (S/ sin IGV). El costo de fábrica solo se escribe donde la variante no lo tiene.
 *    El costo promedio NO se toca: sigue saliendo de las compras.
 *  - Inexistente: se crea producto + variante con su código KY-… y los mismos precios.
 *  - Por defecto solo simula; con --aplicar guarda y deja un respaldo con lo creado y desactivado (--revertir lo deshace).
 */
class ImportarListaPrecios extends Command
{
    protected $signature = 'precios:importar-lista
                            {archivo? : JSON con las filas de la lista}
                            {--aplicar : Guarda los cambios (sin esto solo simula y revierte)}
                            {--revertir= : Archivo de respaldo de una importación anterior, para deshacerla}';

    protected $description = 'Importa la lista de precios oficial: actualiza costos y precios y crea los productos que falten';

    public const NOTA = 'Lista de precios KYRIOS (nov-2026)';
    public const IGV = 1.18;

    private const LUMINARIAS = [
        ['/\bCOLGANTE/', 'colgante'], ['/\b(APLIQUE|BRAQUETE)/', 'braquete'], ['/\bPROYECTOR/', 'proyector'], ['/\bBALIZA/', 'baliza'],
        ['/\bESTACA/', 'estaca'], ['/\bREFLECTOR/', 'reflector'], ['/\b(RIEL|CARRIL)/', 'riel'], ['/\bSUMERGIBLE/', 'sumergible'],
        ['/\bBANADOR/', 'bañador'], ['/\bLECTURA/', 'de lectura'], ['/\bESCRITORIO/', 'de escritorio'],
        ['/\b(SOBREMESA|MESA|VELADOR|ABAJUR|ABUJAR)\b/', 'de mesa'], ['/\bPIE\b/', 'de pie'], ['/\b(PLAFON|ADOSAD|TECHO)/', 'techo (plafón / adosado)'],
    ];

    private const TIPOS = [
        ['/\b(CINTA|TIRA LED)\b/', 'CL'], ['/\b(DRIVER|FUENTE|TRANSFORMADOR)\b/', 'EA'], ['/\bPERFIL/', 'PE'],
        ['/\bVENTILADOR/', 'VE'], ['/\bPOSTE\b/', 'PO'], ['/\bACCESORIO/', 'AC'],
    ];

    private array $informe = [];
    private array $respaldo = ['precios_creados' => [], 'precios_desactivados' => [], 'productos' => [], 'variantes' => [], 'marcas' => [], 'fabrica' => []];
    private array $marcas = [];
    private array $categorias = [];

    public function handle(CodigoKyriosService $codigos): int
    {
        if ($this->option('revertir')) {
            return $this->revertir((string) $this->option('revertir'));
        }

        $archivo = (string) $this->argument('archivo');
        if (!is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");

            return self::FAILURE;
        }
        $filas = json_decode(File::get($archivo), true);
        if (!is_array($filas) || !$filas) {
            $this->error('El archivo no tiene filas válidas.');

            return self::FAILURE;
        }

        $this->informe = array_fill_keys(['filas', 'sin_precio', 'repetidas', 'ambiguas', 'existentes', 'variantes', 'creados', 'regular_nuevo', 'regular_igual',
            'oferta', 'oechsle', 'fabrica_asignada', 'marcas_creadas'], 0);
        $this->informe['filas'] = count($filas);

        DB::beginTransaction();
        try {
            $this->importar($filas, $codigos);
            if ($this->option('aplicar')) {
                DB::commit();
                $ruta = storage_path('app/importacion_lista_precios_' . now()->format('Ymd_His') . '.json');
                File::put($ruta, json_encode($this->respaldo));
                $this->info("Guardado. Respaldo para deshacer: {$ruta}");
            } else {
                DB::rollBack();
                $this->warn('SIMULACIÓN: no se guardó nada. Use --aplicar para guardar.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());

            return self::FAILURE;
        }

        foreach ($this->informe as $clave => $valor) {
            $this->line(str_pad($clave, 20) . $valor);
        }

        return self::SUCCESS;
    }

    private function importar(array $filas, CodigoKyriosService $codigos): void
    {
        TipoProducto::firstOrCreate(['codigo' => 'ST'], ['nombre' => 'Sin tipo', 'caso_codigo' => 'sin_luminaria']);
        $unidad = UnidadMedida::firstOrCreate(['abreviatura' => 'UND'], ['nombre' => 'Unidad', 'categoria' => 'unidad', 'permite_decimales' => false]);

        $variantes = ProductoVariante::get(['id', 'producto_id', 'codigo_fabricante', 'codigo_excel', 'precio_fabrica']);
        $porFabrica = $variantes->filter(fn ($v) => $this->norm($v->codigo_fabricante) !== '')->groupBy(fn ($v) => $this->norm($v->codigo_fabricante));
        $porKlu = $variantes->filter(fn ($v) => $this->norm($v->codigo_excel) !== '')->groupBy(fn ($v) => $this->norm($v->codigo_excel));
        $repetidas = collect($filas)->countBy(fn ($f) => $this->norm($f['cod_fab'] ?? null));

        foreach ($filas as $f) {
            $clave = $this->norm($f['cod_fab'] ?? null);
            if (empty($f['pv_usd']) || $f['pv_usd'] <= 0) {
                $this->informe['sin_precio']++;
                continue;
            }
            if ($clave !== '' && $repetidas[$clave] > 1) {
                $this->informe['repetidas']++;
                continue;
            }

            $candidatas = $clave !== '' ? ($porFabrica[$clave] ?? collect()) : collect();
            if ($candidatas->isEmpty()) {
                $klu = (string) ($f['klu'] ?? '');
                $kn = $this->norm($klu);
                $candidatas = ($kn !== '' && !str_contains(strtoupper($klu), 'SIN')) ? ($porKlu[$kn] ?? collect()) : collect();
            }

            if ($candidatas->pluck('producto_id')->unique()->count() > 1) {
                $this->informe['ambiguas']++;
                continue;
            }

            if ($candidatas->isEmpty()) {
                $this->crear($f, $unidad, $codigos);
                continue;
            }

            $this->informe['existentes']++;
            foreach ($candidatas as $v) {
                $this->informe['variantes']++;
                $this->aplicarPrecios($v->producto_id, $v->id, $f);
                if ($v->precio_fabrica === null && !empty($f['c_fab'])) {
                    ProductoVariante::whereKey($v->id)->update(['precio_fabrica' => round($f['c_fab'], 2)]);
                    $this->respaldo['fabrica'][] = $v->id;
                    $this->informe['fabrica_asignada']++;
                }
            }
        }
    }

    private function aplicarPrecios(int $productoId, int $varianteId, array $f): void
    {
        $compra = !empty($f['c_alm']) ? round($f['c_alm'], 2) : null;
        $venta = round($f['pv_usd'] * self::IGV, 2);
        // Margen sobre costo medido con el precio con IGV, igual que el formulario de Precios
        $margen = $compra ? min(999.99, round(($venta - $compra) / $compra * 100, 2)) : null;

        $this->informe[$this->fijar($productoId, $varianteId, 'venta_regular', $venta, 'USD', true, $compra, $margen) ? 'regular_nuevo' : 'regular_igual']++;
        if (!empty($f['of_usd']) && $f['of_usd'] > 0 && $this->fijar($productoId, $varianteId, 'venta_oferta', round($f['of_usd'], 2), 'USD', true, null, null)) {
            $this->informe['oferta']++;
        }
        if (!empty($f['oes']) && $f['oes'] > 0 && $this->fijar($productoId, $varianteId, 'venta_oechsle', round($f['oes'], 2), 'PEN', false, null, null)) {
            $this->informe['oechsle']++;
        }
    }

    /** Deja un único precio vigente del tipo dado; devuelve false si ya estaba igual. */
    private function fijar(int $productoId, int $varianteId, string $tipo, float $monto, string $moneda, bool $igv, ?float $compra, ?float $margen): bool
    {
        $vigentes = ProductoPrecio::where('producto_id', $productoId)->where('variante_id', $varianteId)->whereNull('almacen_id')
            ->where('tipo_precio', $tipo)->where('activo', true)->orderByDesc('id')->get();
        $actual = $vigentes->first();
        if ($vigentes->count() === 1 && abs((float) $actual->precio - $monto) < 0.005 && $actual->moneda === $moneda && (bool) $actual->incluye_igv === $igv
            && ($compra === null || abs((float) $actual->precio_compra - $compra) < 0.005)) {
            return false;
        }

        foreach ($vigentes as $p) {
            $p->update(['activo' => false]);
            $this->respaldo['precios_desactivados'][] = $p->id;
        }
        $nuevo = ProductoPrecio::create([
            'producto_id' => $productoId, 'variante_id' => $varianteId, 'almacen_id' => null, 'tipo_precio' => $tipo,
            'precio' => $monto, 'precio_compra' => $compra ?? $actual?->precio_compra, 'moneda' => $moneda, 'margen' => $margen,
            'incluye_igv' => $igv, 'prioridad' => 1, 'activo' => true, 'observaciones' => self::NOTA,
        ]);
        $this->respaldo['precios_creados'][] = $nuevo->id;

        return true;
    }

    private function crear(array $f, UnidadMedida $unidad, CodigoKyriosService $codigos): void
    {
        $descripcion = trim((string) ($f['desc'] ?? ''));
        $marca = $this->marca((string) $f['marca']);
        [$codigoTipo, $nombreLuminaria] = $this->clasificar($descripcion);
        $tipo = TipoProducto::where('codigo', $codigoTipo)->firstOrFail();
        $luminaria = $nombreLuminaria ? TipoLuminaria::where('nombre', $nombreLuminaria)->first() : null;
        $categoria = $this->categorias[$tipo->id] ??= Categoria::firstOrCreate(['codigo' => 'CAT-' . $tipo->codigo], ['nombre' => $tipo->nombre, 'estado' => 'activo']);
        $categoria->marcas()->syncWithoutDetaching([$marca->id]);

        [$codigoKyrios, $correlativo] = $codigos->generar($tipo, $luminaria, $marca);
        $codFab = trim((string) ($f['cod_fab'] ?? ''));
        $nombreFab = $descripcion !== '' ? $descripcion : null;
        $minusculas = fn ($v) => ($v = trim((string) $v)) !== '' ? mb_strtolower($v) : null;

        $producto = Producto::create([
            'codigo' => $codigoKyrios, 'nombre' => $nombreFab ?: ($codFab ?: $codigoKyrios), 'nombre_fabrica' => $nombreFab,
            'categoria_id' => $categoria->id, 'tipo_producto_id' => $tipo->id, 'tipo_luminaria_id' => $luminaria?->id, 'marca_id' => $marca->id,
            'unidad_medida_id' => $unidad->id, 'tipo_inventario' => 'cantidad', 'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo',
            'material_1' => $minusculas($f['material'] ?? null),
        ]);
        $variante = ProductoVariante::create([
            'producto_id' => $producto->id, 'sku' => $codigoKyrios, 'codigo_kyrios' => $codigoKyrios, 'correlativo' => $correlativo,
            'nombre_fabrica' => $nombreFab, 'codigo_fabricante' => $codFab !== '' ? $codFab : null,
            'codigo_excel' => !str_contains(strtoupper((string) ($f['klu'] ?? '')), 'SIN') ? ($f['klu'] ?? null) : null,
            'acabado_1' => $minusculas($f['color'] ?? null), 'alto' => $f['alto'] ?? null, 'diametro' => $f['diametro'] ?? null,
            'precio_fabrica' => !empty($f['c_fab']) ? round($f['c_fab'], 2) : null, 'stock_actual' => 0, 'stock_minimo' => 0, 'estado' => 'activo',
        ]);
        $this->respaldo['productos'][] = $producto->id;
        $this->respaldo['variantes'][] = $variante->id;
        $this->informe['creados']++;
        $this->aplicarPrecios($producto->id, $variante->id, $f);
    }

    private function marca(string $nombre): Marca
    {
        if (isset($this->marcas[$nombre])) {
            return $this->marcas[$nombre];
        }
        $marca = Marca::where('nombre', $nombre)->first();
        if (!$marca) {
            $siguiente = (int) Marca::query()->get('codigo')->map(fn ($m) => (int) $m->codigo)->max() + 1;
            $marca = Marca::create(['nombre' => $nombre, 'codigo' => str_pad((string) $siguiente, 2, '0', STR_PAD_LEFT)]);
            $this->respaldo['marcas'][] = $marca->id;
            $this->informe['marcas_creadas']++;
        }

        return $this->marcas[$nombre] = $marca;
    }

    /** @return array{0: string, 1: ?string} código de tipo de producto y nombre del tipo de luminaria, a partir de la descripción */
    private function clasificar(string $descripcion): array
    {
        if ($descripcion === '') {
            return ['ST', null];
        }
        $t = strtoupper(Str::ascii($descripcion));
        foreach (self::TIPOS as [$patron, $codigo]) {
            if (preg_match($patron, $t)) {
                return [$codigo, null];
            }
        }
        if (preg_match('/\bEMPOTR/', $t)) {
            return ['LU', preg_match('/\bPISO\b/', $t) ? 'empotrado de piso' : (preg_match('/\b(PARED|MURO)\b/', $t) ? 'empotrado de pared' : 'empotrado de techo')];
        }
        foreach (self::LUMINARIAS as [$patron, $nombre]) {
            if (preg_match($patron, $t)) {
                return ['LU', $nombre];
            }
        }

        return ['LU', null];
    }

    private function revertir(string $ruta): int
    {
        if (!is_file($ruta)) {
            $this->error("No existe el respaldo: {$ruta}");

            return self::FAILURE;
        }
        $r = json_decode(File::get($ruta), true);

        DB::transaction(function () use ($r) {
            ProductoPrecio::whereIn('id', $r['precios_creados'])->delete();
            ProductoPrecio::whereIn('id', $r['precios_desactivados'])->update(['activo' => true]);
            ProductoVariante::whereIn('id', $r['fabrica'])->update(['precio_fabrica' => null]);
            ProductoVariante::whereIn('id', $r['variantes'])->delete();
            Producto::whereIn('id', $r['productos'])->delete();
            Marca::whereIn('id', $r['marcas'])->delete();
        });
        $this->info('Importación deshecha: precios, productos, variantes y marcas creados fueron eliminados y los precios anteriores se reactivaron.');

        return self::SUCCESS;
    }

    private function norm(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (is_float($v) && $v == (int) $v) {
            $v = (int) $v;
        }

        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $v));
    }
}
