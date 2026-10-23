<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\Catalogo\Marca;
use App\Models\Catalogo\TipoLuminaria;
use App\Models\Catalogo\TipoProducto;
use App\Models\Catalogo\UnidadMedida;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Services\CodigoKyriosService;
use Database\Seeders\KyriosCatalogoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Importa el catálogo Kyrios desde "Productos.xlsx".
 *
 *  - Código principal (variante.codigo_kyrios / sku): codificación del PDF, generado al importar.
 *  - Código del Excel ("Código KYRIOS", p. ej. KLU01-00003): queda como segundo código (codigo_excel).
 *  - Código del fabricante (columna A): codigo_fabricante.
 *  - Producto = modelo (nombre fábrica + marca + tipo); cada fila del Excel = una variante (SKU).
 *  - Estado de daño: hoja "Dañados" (color de la celda: amarillo leve, celeste moderado, gris grave)
 *    y hoja "OBSERVACIONES" (texto).
 */
class ImportarProductosKyrios extends Command
{
    protected $signature = 'productos:importar-kyrios
                            {archivo : Ruta del Productos.xlsx}
                            {--dry-run : Procesa todo y revierte al final (solo muestra el reporte)}
                            {--sin-imagenes : No extrae las imágenes incrustadas en la columna IMAGEN del Excel}';

    protected $description = 'Importa productos, variantes, atributos y estado de daño desde el Excel de Kyrios';

    private const COLOR_DANO = ['FFFFFF00' => 'leve', 'FF00FFFF' => 'moderado', 'FF999999' => 'grave'];
    private const GRAVEDAD = ['ninguno' => 0, 'leve' => 1, 'moderado' => 2, 'grave' => 3];

    /** Aplicación 1 del Excel → nombre en tipos_luminaria */
    private const MAPA_LUMINARIA = [
        'plafon' => 'techo (plafón / adosado)', 'adosado' => 'techo (plafón / adosado)',
        'empotrado de techo recesado' => 'empotrado de techo', 'spot empotrado' => 'empotrado de techo',
        'banador' => 'bañador',
    ];

    private const MAPA_TIPO_PRODUCTO = [
        'accesorios' => 'accesorio', 'fuente (driver, transformador)' => 'fuente (driver, transformador)',
    ];

    private array $reporte = [];
    /** @var array<int, array{ruta: string, media: string}> archivos a escribir tras el commit */
    private array $archivosPendientes = [];
    private array $marcasProvisionales = [];
    private int $siguienteCodigoMarca = 34; // 31 queda libre (cortado en el PDF); 32 y 33 ya usados por el Excel

    public function handle(CodigoKyriosService $codigos): int
    {
        ini_set('memory_limit', '3G');
        set_time_limit(0);

        $archivo = $this->argument('archivo');
        if (!is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");
            return self::FAILURE;
        }

        // La lectura va antes de tocar la base de datos: si falta una hoja se informa y no se cambia nada
        try {
            $this->info('Leyendo hojas del Excel (puede tardar un par de minutos)...');
            $base = $this->leerHoja($archivo, 'Base de Productos', 5, 45);
            $proy = $this->leerHoja($archivo, 'Tipo de proyecto', 4, 24);
            $inst = $this->leerHoja($archivo, 'Tipo de instalación', 4, 24);
            $esti = $this->leerHoja($archivo, 'Estilo', 4, 14);
            $obs  = $this->leerHoja($archivo, 'OBSERVACIONES', 3, 9);
            $danos = $this->leerDanos($archivo);
            $pack = $this->leerHoja($archivo, 'Packing list', 5, 16);
            $imagenes = $this->option('sin-imagenes') ? [] : $this->imagenesDeHoja($archivo, 'Base de Productos');
            $this->info('Imágenes incrustadas en filas de productos: ' . array_sum(array_map('count', $imagenes)));
        } catch (\Throwable $e) {
            $this->error('No se pudo leer el Excel: ' . $e->getMessage());
            return self::FAILURE;
        }

        $filas = array_filter($base['filas'], fn ($f) => $this->vacio($f[0] ?? null) !== null || $this->vacio($f[1] ?? null) !== null);
        $this->info('Filas con datos: ' . count($filas));

        $this->reporte = [
            'productos' => 0, 'variantes' => 0, 'sin_tipo_producto' => 0, 'sin_marca' => 0,
            'caso1_sin_luminaria' => 0, 'danos_aplicados' => 0, 'danos_no_encontrados' => 0,
            'matriz_desalineada' => 0, 'fabricante_duplicado' => 0, 'imagenes_excel' => 0, 'imagenes_sobrantes' => 0, 'precios' => 0, 'packing_desalineado' => 0, 'danos_creados' => 0,
        ];

        DB::beginTransaction();
        try {
            $this->call('db:seed', ['--class' => KyriosCatalogoSeeder::class, '--force' => true]);

            $unidad = UnidadMedida::firstOrCreate(
                ['abreviatura' => 'UND'],
                ['nombre' => 'Unidad', 'categoria' => 'unidad', 'permite_decimales' => false]
            );

            $this->importar($filas, $proy, $inst, $esti, $obs, $danos, $unidad, $codigos, $imagenes, $pack);

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->warn('DRY-RUN: no se guardó nada.');
            } else {
                DB::commit();
                $this->escribirArchivos($archivo);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            return self::FAILURE;
        }

        $this->mostrarReporte();
        return self::SUCCESS;
    }

    // ─── Importación ─────────────────────────────────────────────────────────

    private function importar(array $filas, array $proy, array $inst, array $esti, array $obs, array $danos, UnidadMedida $unidad, CodigoKyriosService $codigos, array $imagenes, array $pack): void
    {
        $tipos = TipoProducto::all()->keyBy(fn ($t) => $this->clave($t->nombre));
        $luminarias = TipoLuminaria::all()->keyBy(fn ($t) => $this->clave($t->nombre));
        $categorias = [];
        $marcas = [];
        $productos = [];
        $fabricantesVistos = [];

        $obsPorCodigo = [];
        foreach ($obs['filas'] as $o) {
            $cod = $this->vacio($o[2] ?? null);
            if ($cod) {
                $obsPorCodigo[$this->clave($cod)] = ['estado' => strtolower((string) $this->vacio($o[4] ?? null)), 'texto' => $this->vacio($o[5] ?? null)];
            }
        }

        foreach ($filas as $n => $f) {
            $codFab = $this->texto($f[0] ?? null);
            $codExcel = $this->texto($f[1] ?? null);

            // Tipo de producto (sin dato → Luminaria, aparece en el reporte)
            $nombreTipo = $this->texto($f[5] ?? null);
            $tipo = $nombreTipo ? ($tipos[$this->clave(self::MAPA_TIPO_PRODUCTO[$this->clave($nombreTipo)] ?? $nombreTipo)] ?? null) : null;
            if (!$tipo) {
                $this->reporte['sin_tipo_producto']++;
                $tipo = $tipos[$this->clave('Luminaria')];
            }

            // Tipo de luminaria (Aplicación 1)
            $aplicacion = $this->texto($f[8] ?? null);
            $luminaria = null;
            if ($aplicacion) {
                $k = $this->clave($aplicacion);
                $luminaria = $luminarias[$this->clave(self::MAPA_LUMINARIA[$k] ?? $aplicacion)] ?? null;
            }
            if ($tipo->usaTipoLuminaria() && !$luminaria) {
                $this->reporte['caso1_sin_luminaria']++;
            }

            // Marca
            $marca = $this->resolverMarca($this->texto($f[41] ?? null), $marcas);
            if ($marca->nombre === 'SIN MARCA') {
                $this->reporte['sin_marca']++;
            }

            $categoria = $categorias[$tipo->id] ??= Categoria::firstOrCreate(
                ['codigo' => 'CAT-' . $tipo->codigo],
                ['nombre' => $tipo->nombre, 'estado' => 'activo']
            );

            // Producto = modelo
            $nombreFab = $this->texto($f[10] ?? null);
            $nombreK = $this->texto($f[11] ?? null);
            $claveProducto = $nombreFab
                ? implode('|', [$this->clave($nombreFab), $marca->id, $tipo->id, $luminaria?->id])
                : 'fila-' . $n;

            $variantes = $productos[$claveProducto] ?? null;
            $producto = $variantes['modelo'] ?? null;

            // Código de la variante (PDF) — siempre antes de crear el producto, que toma ese código
            [$codigoKyrios, $correlativo] = $codigos->generar($tipo, $luminaria, $marca);

            if (!$producto) {
                $producto = Producto::create([
                    'codigo'           => $codigoKyrios,
                    'nombre'           => $nombreK ?: ($nombreFab ?: ($codFab ?: $codExcel)),
                    'nombre_fabrica'   => $nombreFab,
                    'nombre_kyrios'    => $nombreK,
                    'categoria_id'     => $categoria->id,
                    'tipo_producto_id' => $tipo->id,
                    'tipo_luminaria_id' => $luminaria?->id,
                    'marca_id'         => $marca->id,
                    'unidad_medida_id' => $unidad->id,
                    'tipo_inventario'  => 'cantidad',
                    'stock_minimo'     => 0,
                    'stock_maximo'     => 1000,
                    'estado'           => 'activo',
                    'linea'            => $this->sinParentesis($this->texto($f[42] ?? null)),
                    'procedencia'      => $this->texto($f[43] ?? null),
                    'fuente_luz'       => $this->texto($f[6] ?? null),
                    'uso'              => $this->minusculas($this->texto($f[7] ?? null)),
                    'aplicacion_2'     => $this->minusculas($this->texto($f[9] ?? null)),
                    'material_1'       => $this->minusculas($this->texto($f[25] ?? null)),
                    'material_2'       => $this->minusculas($this->texto($f[26] ?? null)),
                    'nivel_potencia'   => $this->texto($f[27] ?? null),
                    'voltaje'          => $this->texto($f[33] ?? null),
                    'ip'               => $this->entero($f[34] ?? null),
                    'ik'               => $this->entero($f[35] ?? null),
                    'cri'              => $this->entero($f[36] ?? null),
                    'angulo_apertura'  => $this->texto($f[37] ?? null),
                    'driver'           => $this->texto($f[38] ?? null),
                    'regulable'        => $this->booleano($f[39] ?? null),
                    'protocolo_regulacion' => $this->texto($f[40] ?? null),
                    'ficha_tecnica'    => $this->texto($f[44] ?? null),
                    'estilos'          => $this->marcados($esti, $n, 2, 13, 'estilos'),
                    'tipos_instalacion' => $this->marcados($inst, $n, 2, 23, 'tipos_instalacion'),
                    'tipos_proyecto'   => $this->marcados($proy, $n, 2, 14, 'tipos_proyecto'),
                ]);
                $categoria->marcas()->syncWithoutDetaching([$marca->id]); // el formulario filtra marcas por categoría
                $productos[$claveProducto] = ['modelo' => $producto];
                $this->reporte['productos']++;

                // Alineación de las hojas de matriz con la base (por fila)
                foreach ([$proy, $inst, $esti] as $m) {
                    $cm = $this->texto($m['filas'][$n][0] ?? null);
                    if ($cm !== $codFab) {
                        $this->reporte['matriz_desalineada']++;
                        break;
                    }
                }
            }

            if ($codFab) {
                $fabricantesVistos[$codFab] = ($fabricantesVistos[$codFab] ?? 0) + 1;
            }

            // Packing list (misma fila que la base; se verifica por código de fabricante)
            $pk = $pack['filas'][$n] ?? [];
            if ($pk && $this->texto($pk[0] ?? null) !== $codFab) {
                $this->reporte['packing_desalineado']++;
                $pk = [];
            }

            // Estado de daño
            [$estadoDano, $obsDano] = $this->resolverDano($codFab, $danos, $obsPorCodigo);

            $variante = ProductoVariante::create([
                'producto_id'       => $producto->id,
                'sku'               => $codigoKyrios,
                'codigo_kyrios'     => $codigoKyrios,
                'correlativo'       => $correlativo,
                'codigo_fabricante' => $codFab,
                'codigo_excel'      => $codExcel,
                'acabado_1'         => $this->minusculas($this->texto($f[12] ?? null)),
                'acabado_2'         => $this->minusculas($this->texto($f[13] ?? null)),
                'tamano'            => $this->minusculas($this->texto($f[14] ?? null)),
                'diametro'          => $this->decimal($f[15] ?? null),
                'alto'              => $this->decimal($f[17] ?? null),
                'ancho'             => $this->decimal($f[18] ?? null),
                'alto_suspendido'   => $this->decimal($f[19] ?? null),
                'diametro_agujero'  => $this->decimal($f[20] ?? null),
                'profundidad'       => $this->decimal($f[22] ?? null),
                'socket'            => $this->texto($f[23] ?? null),
                'num_lamparas'      => $this->minusculas($this->texto($f[24] ?? null)),
                'potencia_w'        => $this->decimal($f[28] ?? null),
                'lumenes'           => $this->decimal($f[30] ?? null) ?? $this->decimal($f[29] ?? null),
                'temperatura_color' => ($t = $this->decimal($f[31] ?? null)) !== null ? (int) $t : null,
                'tonalidad_luz'     => $this->texto($f[32] ?? null),
                'estado_dano'       => $estadoDano,
                'observacion_dano'  => $obsDano,
                'precio_fabrica'    => $this->decimal($pk[8] ?? null),
                'peso'              => $this->decimal($pk[2] ?? null),
                'volumen'           => $this->decimal($pk[3] ?? null),
                'embalado'          => $this->texto($pk[4] ?? null),
                'medida_embalaje'   => $this->texto($pk[5] ?? null),
                'cantidad_por_caja' => ($c = $this->decimal($pk[6] ?? null)) !== null ? (int) $c : null,
                'codigos_relacionados' => $this->texto($pk[7] ?? null),
                'stock_actual'      => 0,
                'stock_minimo'      => 0,
                'estado'            => 'activo',
            ]);
            $this->reporte['variantes']++;

            // PRECIO VENTA + IGV (col. P) y costo importado "C. IMP" (col. K), en USD (EUR × 1.3 según el Excel)
            $precioVenta = $this->decimal($pk[15] ?? null);
            if ($precioVenta !== null && $precioVenta > 0) {
                ProductoPrecio::create([
                    'producto_id'   => $producto->id,
                    'variante_id'   => $variante->id,
                    'tipo_precio'   => 'venta_regular',
                    'precio'        => $precioVenta,
                    'precio_compra' => $this->decimal($pk[10] ?? null),
                    'incluye_igv'   => true,
                    'prioridad'     => 1,
                    'activo'        => true,
                    'observaciones' => 'Importado del Excel (Packing list). Montos en USD.',
                ]);
                $this->reporte['precios']++;
            }

            foreach (array_values($imagenes[$n] ?? []) as $i => $media) {
                if ($i >= ProductoImagen::MAX_POR_VARIANTE) {
                    $this->reporte['imagenes_sobrantes']++;
                    continue;
                }
                $ruta = "productos/{$variante->id}/" . ($i + 1) . '.' . strtolower(pathinfo($media, PATHINFO_EXTENSION));
                ProductoImagen::create([
                    'producto_id' => $producto->id, 'variante_id' => $variante->id,
                    'ruta' => $ruta, 'orden' => $i + 1, 'es_principal' => $i === 0,
                ]);
                $this->archivosPendientes[] = ['ruta' => $ruta, 'media' => $media];
                $this->reporte['imagenes_excel']++;
            }

            if ($estadoDano !== 'ninguno') {
                $this->reporte['danos_aplicados']++;
            }
        }

        $this->reporte['fabricante_duplicado'] = count(array_filter($fabricantesVistos, fn ($c) => $c > 1));
        $hallados = array_map(fn ($x) => $this->clave((string) $x), array_keys($fabricantesVistos));
        $faltantes = array_filter($danos, fn ($d, $k) => !in_array((string) $k, $hallados, true), ARRAY_FILTER_USE_BOTH);
        $this->reporte['danos_no_encontrados'] = count($faltantes);

        // Códigos de la hoja Dañados que no están en la base: se crean con los pocos datos que traen
        // (código del fabricante, línea, marca y daño). Tipo = Luminaria; nombre = código del fabricante.
        $tipoLuminaria = $tipos[$this->clave('Luminaria')];
        $categoriaLum = $categorias[$tipoLuminaria->id] ??= Categoria::firstOrCreate(
            ['codigo' => 'CAT-' . $tipoLuminaria->codigo],
            ['nombre' => $tipoLuminaria->nombre, 'estado' => 'activo']
        );
        foreach ($faltantes as $d) {
            $marca = $this->resolverMarca($this->texto($d['marca'] ?? null), $marcas);
            [$codigoKyrios, $correlativo] = $codigos->generar($tipoLuminaria, null, $marca);
            $categoriaLum->marcas()->syncWithoutDetaching([$marca->id]);

            $producto = Producto::create([
                'codigo' => $codigoKyrios, 'nombre' => $d['codigo'], 'nombre_fabrica' => null,
                'categoria_id' => $categoriaLum->id, 'tipo_producto_id' => $tipoLuminaria->id,
                'marca_id' => $marca->id, 'unidad_medida_id' => $unidad->id, 'tipo_inventario' => 'cantidad',
                'stock_minimo' => 0, 'stock_maximo' => 1000, 'estado' => 'activo',
                'linea' => $d['linea'] ?? null,
                'observaciones' => 'Creado desde la hoja "Dañados" del Excel: faltan los datos técnicos del producto.',
            ]);
            ProductoVariante::create([
                'producto_id' => $producto->id, 'sku' => $codigoKyrios, 'codigo_kyrios' => $codigoKyrios,
                'correlativo' => $correlativo, 'codigo_fabricante' => $d['codigo'],
                'estado_dano' => $d['estado'], 'observacion_dano' => $d['texto'],
                'stock_actual' => 0, 'stock_minimo' => 0, 'estado' => 'activo',
            ]);
            $this->reporte['productos']++;
            $this->reporte['variantes']++;
            $this->reporte['danos_creados']++;
            $this->reporte['danos_aplicados']++;
        }
        $this->reporte['danos_no_encontrados'] = 0;
    }

    private function resolverMarca(?string $crudo, array &$cache): Marca
    {
        if (!$crudo) {
            return $cache['SIN MARCA'] ??= Marca::firstOrCreate(['nombre' => 'SIN MARCA'], ['codigo' => '00', 'descripcion' => 'Filas del Excel sin marca']);
        }

        $nombre = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $crudo));
        $numero = preg_match('/\((\d+)\)\s*$/', $crudo, $m) ? str_pad($m[1], 2, '0', STR_PAD_LEFT) : null;

        if (isset($cache[$nombre])) {
            return $cache[$nombre];
        }

        $marca = Marca::where('nombre', $nombre)->first();   // PDF manda sobre el número del Excel
        if (!$marca) {
            $codigo = $numero && !Marca::where('codigo', $numero)->exists() ? $numero : null;
            if (!$codigo) {
                do {
                    $codigo = str_pad((string) $this->siguienteCodigoMarca++, 2, '0', STR_PAD_LEFT);
                } while (Marca::where('codigo', $codigo)->exists());
            }
            $marca = Marca::create(['nombre' => $nombre, 'codigo' => $codigo]);
            $this->marcasProvisionales[$nombre] = $codigo;
        }

        return $cache[$nombre] = $marca;
    }

    private function resolverDano(?string $codFab, array $danos, array $obsPorCodigo): array
    {
        if (!$codFab) {
            return ['ninguno', null];
        }
        $k = $this->clave($codFab);
        $estado = $danos[$k]['estado'] ?? 'ninguno';
        $texto = $danos[$k]['texto'] ?? null;

        if (isset($obsPorCodigo[$k])) {
            $o = $obsPorCodigo[$k];
            $e = ['leve' => 'leve', 'moderado' => 'moderado', 'grave' => 'grave'][$o['estado']] ?? null;
            if ($e && self::GRAVEDAD[$e] > self::GRAVEDAD[$estado]) {
                $estado = $e;
            }
            $texto = trim(($texto ? $texto . ' | ' : '') . ($o['texto'] ?? ''));
        }

        return [$estado, $texto ?: null];
    }

    // ─── Lectura del Excel ───────────────────────────────────────────────────

    /** @return array{filas: array<int, array<int, mixed>>, cabeceras: array<int, mixed>} filas indexadas por número de fila Excel, columnas 0-based */
    private function leerHoja(string $archivo, string $hoja, int $filaInicial, int $maxCol): array
    {
        $filtro = new class($filaInicial, $maxCol) implements IReadFilter {
            public function __construct(private int $desde, private int $maxCol) {}
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= $this->desde && Coordinate::columnIndexFromString($columnAddress) <= $this->maxCol;
            }
        };

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setLoadSheetsOnly($this->nombreHoja($archivo, $hoja));
        $reader->setReadFilter($filtro);
        $ws = $reader->load($archivo)->getActiveSheet();

        $filas = [];
        foreach ($ws->getRowIterator($filaInicial) as $row) {
            $r = [];
            foreach ($row->getCellIterator('A', Coordinate::stringFromColumnIndex($maxCol)) as $cell) {
                $valor = $cell->getValue();
                if (is_string($valor) && str_starts_with($valor, '=')) {
                    $valor = $cell->getOldCalculatedValue(); // valor guardado por Excel de las fórmulas
                }
                $r[Coordinate::columnIndexFromString($cell->getColumn()) - 1] = $valor;
            }
            if (array_filter($r, fn ($v) => $v !== null && $v !== '')) {
                $filas[$row->getRowIndex()] = $r;
            }
        }

        $ws->getParent()->disconnectWorksheets();
        return ['filas' => $filas, 'cabeceras' => $filas[4] ?? []];
    }

    /**
     * Imágenes ancladas (dibujos) de una hoja → [fila Excel => [ruta dentro del zip, ...]].
     * El Excel de Kyrios tiene las fotos incrustadas en la columna IMAGEN de "Base de Productos".
     */
    private function imagenesDeHoja(string $archivo, string $hoja): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($archivo) !== true) {
            return [];
        }

        $wb = $zip->getFromName('xl/workbook.xml') ?: '';
        $rid = null;
        foreach ((preg_match_all('/<sheet\b[^>]*>/', $wb, $m) ? $m[0] : []) as $tag) {
            if (preg_match('/name="([^"]*)"/', $tag, $n) && $this->clave(html_entity_decode($n[1])) === $this->clave($hoja)
                && preg_match('/r:id="([^"]+)"/', $tag, $r)) {
                $rid = $r[1];
            }
        }
        $buscarDestino = function (string $rels, string $id): ?string {
            foreach ((preg_match_all('/<Relationship\b[^>]*>/', $rels, $m) ? $m[0] : []) as $tag) {
                if (str_contains($tag, 'Id="' . $id . '"') && preg_match('/Target="([^"]+)"/', $tag, $t)) {
                    return $t[1];
                }
            }
            return null;
        };

        $hojaRuta = $rid ? $buscarDestino($zip->getFromName('xl/_rels/workbook.xml.rels') ?: '', $rid) : null;
        if (!$hojaRuta) {
            return [];
        }
        $hojaRuta = 'xl/' . ltrim($hojaRuta, '/');
        $relsHoja = $zip->getFromName(dirname($hojaRuta) . '/_rels/' . basename($hojaRuta) . '.rels') ?: '';
        if (!preg_match('#Target="\.\./drawings/(drawing\d+\.xml)"#', $relsHoja, $d)) {
            return [];
        }

        $dibujo = $zip->getFromName('xl/drawings/' . $d[1]) ?: '';
        $relsDibujo = $zip->getFromName('xl/drawings/_rels/' . $d[1] . '.rels') ?: '';
        $media = [];
        foreach ((preg_match_all('/<Relationship\b[^>]*>/', $relsDibujo, $m) ? $m[0] : []) as $tag) {
            if (preg_match('/Id="([^"]+)"/', $tag, $i) && preg_match('#Target="\.\./media/([^"]+)"#', $tag, $t)) {
                $media[$i[1]] = 'xl/media/' . $t[1];
            }
        }

        $res = [];
        preg_match_all('#<xdr:(?:twoCellAnchor|oneCellAnchor)\b.*?</xdr:(?:twoCellAnchor|oneCellAnchor)>#s', $dibujo, $anclas);
        foreach ($anclas[0] as $ancla) {
            if (preg_match('#<xdr:from><xdr:col>(\d+)</xdr:col>.*?<xdr:row>(\d+)</xdr:row>#s', $ancla, $pos)
                && preg_match('/r:embed="([^"]+)"/', $ancla, $e) && isset($media[$e[1]])) {
                $res[(int) $pos[2] + 1][] = $media[$e[1]];
            }
        }
        $zip->close();

        return $res;
    }

    /** Escribe en storage/public las imágenes del Excel (solo después de confirmar la importación). */
    private function escribirArchivos(string $archivo): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($archivo) !== true) {
            return;
        }
        foreach ($this->archivosPendientes as $f) {
            if (($contenido = $zip->getFromName($f['media'])) !== false) {
                Storage::disk('public')->put($f['ruta'], $contenido);
            }
        }
        $zip->close();
    }

    /** Dañados: el nivel está en el color de relleno de la celda de la columna A. */
    private function leerDanos(string $archivo): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly($this->nombreHoja($archivo, 'Dañados'));
        $ws = $reader->load($archivo)->getActiveSheet();

        $res = [];
        for ($row = 2, $max = $ws->getHighestDataRow('A'); $row <= $max; $row++) {
            $cod = $this->texto($ws->getCell("A{$row}")->getValue());
            if (!$cod) {
                continue;
            }
            $rgb = $ws->getStyle("A{$row}")->getFill()->getStartColor()->getARGB();
            $estado = self::COLOR_DANO[strtoupper((string) $rgb)] ?? null;
            if (!$estado) {
                continue;
            }
            $k = $this->clave($cod);
            $texto = trim(($this->texto($ws->getCell("D{$row}")->getValue()) ? 'Cantidad: ' . $this->texto($ws->getCell("D{$row}")->getValue()) . '. ' : '')
                . ($this->texto($ws->getCell("E{$row}")->getValue()) ?? ''));
            if (!isset($res[$k]) || self::GRAVEDAD[$estado] > self::GRAVEDAD[$res[$k]['estado']]) {
                $res[$k] = [
                    'codigo' => $cod, 'estado' => $estado, 'texto' => $texto ?: null,
                    'linea' => $this->sinParentesis($this->texto($ws->getCell("B{$row}")->getValue())),
                    'marca' => $this->texto($ws->getCell("C{$row}")->getValue()),
                ];
            }
        }
        $ws->getParent()->disconnectWorksheets();

        return $res;
    }

    private function nombreHoja(string $archivo, string $hoja): string
    {
        static $nombres = null;
        $nombres ??= IOFactory::createReader('Xlsx')->listWorksheetNames($archivo);
        foreach ($nombres as $n) {
            if ($this->clave($n) === $this->clave($hoja)) {
                return $n;
            }
        }
        throw new \RuntimeException("No se encontró la hoja '{$hoja}' en el Excel");
    }

    // ─── Normalización ───────────────────────────────────────────────────────

    private function vacio($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === null || $v === '' || $v === '-' || $v === '--') ? null : (string) $v;
    }

    private function texto($v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return null;
        }
        if (is_float($v) && floor($v) == $v) {
            $v = (int) $v;
        }
        return $this->vacio($v);
    }

    private function minusculas(?string $v): ?string
    {
        return $v === null ? null : mb_strtolower($v);
    }

    private function decimal($v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return round((float) $v, 2);
        }
        $v = $this->vacio($v);
        return ($v !== null && is_numeric(str_replace(',', '.', $v))) ? round((float) str_replace(',', '.', $v), 2) : null;
    }

    private function entero($v): ?string
    {
        $d = $this->decimal($v);
        return $d === null ? null : (string) (int) $d;
    }

    private function booleano($v): ?bool
    {
        $v = $this->minusculas($this->texto($v));
        return $v === 'si' || $v === 'sí' ? true : ($v === 'no' ? false : null);
    }

    private function sinParentesis(?string $v): ?string
    {
        return $v === null ? null : trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $v));
    }

    /** Clave de comparación: sin tildes, minúsculas, espacios simples. */
    private function clave(string $s): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtolower(Str::ascii($s))));
    }

    /** Columnas marcadas con "x" en una hoja de matriz → nombres de la cabecera (fila 4). */
    private function marcados(array $hoja, int $fila, int $desde, int $hasta, string $lista): array
    {
        $r = $hoja['filas'][$fila] ?? [];
        $out = [];
        for ($c = $desde; $c <= $hasta; $c++) {
            if (isset($r[$c]) && trim((string) $r[$c]) !== '' && ($nombre = $this->nombreColumna($hoja, $c))) {
                // Identificador (slug) de config/kyrios.php; corrige typos y tildes de las cabeceras del Excel
                $k = $this->clave($nombre);
                $slug = config("kyrios.alias.{$lista}.{$k}") ?? Str::slug($k);
                $out[] = $slug;
            }
        }
        return array_values(array_unique($out));
    }

    private function nombreColumna(array $hoja, int $col): ?string
    {
        $v = $hoja['cabeceras'][$col] ?? null;
        return $v ? trim(preg_replace('/\s+/', ' ', (string) $v)) : null;
    }

    private function mostrarReporte(): void
    {
        $this->newLine();
        $this->table(['Concepto', 'Cantidad'], collect($this->reporte)->map(fn ($v, $k) => [$k, $v])->values()->all());
        if ($this->marcasProvisionales) {
            $this->warn('Marcas fuera del PDF con código PROVISIONAL (confirmar): ' . json_encode($this->marcasProvisionales, JSON_UNESCAPED_UNICODE));
        }
    }
}
