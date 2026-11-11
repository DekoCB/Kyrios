<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ProductoPrecio;
use App\Models\ProductoVariante;
use App\Support\EtiquetaProducto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Plantilla de homologación: el cliente describe lo que busca y el sistema propone los productos
 * del catálogo que mejor coinciden, para armar la cotización.
 */
class HomologacionService
{
    /** Columnas de la plantilla: clave interna => encabezado. */
    public const COLUMNAS = [
        'descripcion' => 'Descripción del producto',
        'tipo'        => 'Tipo de luminaria',
        'potencia'    => 'Potencia (W)',
        'temperatura' => 'Temperatura de color (K)',
        'ip'          => 'Grado IP',
        'color'       => 'Color / acabado',
        'medidas'     => 'Medidas (mm)',
        'cantidad'    => 'Cantidad',
        'notas'       => 'Observaciones',
    ];

    private const PARASITAS = ['para', 'con', 'sin', 'del', 'las', 'los', 'una', 'uno', 'por', 'que', 'luz', 'led'];

    // ── Plantilla en Excel ────────────────────────────────────────────────

    public function plantillaExcel(): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet()->setTitle('Homologación');

        $col = 1;
        foreach (self::COLUMNAS as $titulo) {
            $hoja->setCellValue([$col, 1], $titulo);
            $hoja->getColumnDimensionByColumn($col)->setWidth($col === 1 ? 46 : 22);
            $col++;
        }
        $ultima = $hoja->getHighestColumn();
        $hoja->getStyle("A1:{$ultima}1")->getFont()->setBold(true);
        $hoja->getStyle("A1:{$ultima}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2C80F');
        $hoja->getStyle("A1:{$ultima}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $hoja->getRowDimension(1)->setRowHeight(32);
        $hoja->freezePane('A2');

        $ayuda = $libro->createSheet()->setTitle('Instrucciones');
        $lineas = [
            'Cómo llenar la plantilla',
            '1. Escriba un producto por fila en la hoja «Homologación».',
            '2. Solo la descripción y la cantidad son indispensables; el resto de columnas ayuda a encontrar mejor coincidencia.',
            '3. Ejemplo: «Downlight empotrable» · Downlight · 12 · 3000 · 20 · Blanco · 120x120 · 10',
            '4. Devuelva el archivo para cargarlo en el sistema.',
        ];
        foreach ($lineas as $i => $t) {
            $ayuda->setCellValue([1, $i + 1], $t);
        }
        $ayuda->getStyle('A1')->getFont()->setBold(true);
        $ayuda->getColumnDimension('A')->setWidth(110);

        $ruta = tempnam(sys_get_temp_dir(), 'homol') . '.xlsx';
        (new Xlsx($libro))->save($ruta);

        return $ruta;
    }

    // ── Lectura del archivo lleno ─────────────────────────────────────────

    /**
     * Lee la plantilla (xlsx o csv) y devuelve las filas con datos, con las claves de COLUMNAS.
     *
     * @return array<int, array<string, string>>
     */
    public function leer(string $ruta): array
    {
        $hoja = IOFactory::load($ruta)->getSheet(0);
        $filas = $hoja->toArray(null, true, true, false);

        // Localiza la fila de encabezados (la que contiene «descripción»)
        $mapa = [];
        $inicio = null;
        foreach ($filas as $i => $fila) {
            foreach ($fila as $c => $celda) {
                if (str_contains($this->normalizar((string) $celda), 'descripcion')) {
                    $inicio = $i;
                    break 2;
                }
            }
        }
        if ($inicio === null) {
            throw new \DomainException('No se encontró la columna «Descripción del producto». Use la plantilla descargada del sistema.');
        }

        $claves = [
            'descripcion' => ['descripcion'], 'tipo' => ['tipo'], 'potencia' => ['potencia'], 'temperatura' => ['temperatura'],
            'ip' => ['ip'], 'color' => ['color', 'acabado'], 'medidas' => ['medida', 'dimension'], 'cantidad' => ['cantidad'], 'notas' => ['observacion', 'nota'],
        ];
        foreach ($filas[$inicio] as $c => $celda) {
            $t = $this->normalizar((string) $celda);
            foreach ($claves as $clave => $palabras) {
                if (!isset($mapa[$clave]) && $t !== '' && collect($palabras)->contains(fn ($p) => str_contains($t, $p))) {
                    $mapa[$clave] = $c;
                }
            }
        }

        $resultado = [];
        foreach (array_slice($filas, $inicio + 1) as $fila) {
            $dato = [];
            foreach (array_keys(self::COLUMNAS) as $clave) {
                $dato[$clave] = isset($mapa[$clave]) ? trim((string) ($fila[$mapa[$clave]] ?? '')) : '';
            }
            if ($dato['descripcion'] === '' && $dato['tipo'] === '' && $dato['potencia'] === '') {
                continue;
            }
            $resultado[] = $dato;
            if (count($resultado) >= 200) {
                break;
            }
        }

        return $resultado;
    }

    // ── Búsqueda de la mejor coincidencia ─────────────────────────────────

    /**
     * Devuelve hasta $max candidatos del catálogo para una fila de la plantilla, de mayor a menor coincidencia.
     *
     * @return array<int, array{producto_id:int, variante_id:?int, nombre:string, codigo:string, detalle:?string, puntaje:int, precio:?float}>
     */
    public function coincidencias(array $fila, int $max = 3): array
    {
        $tokens = $this->palabras($fila['descripcion'] . ' ' . $fila['tipo']);
        $potencia = $this->numero($fila['potencia']);

        $consulta = ProductoVariante::activas()
            ->whereHas('producto', fn ($p) => $p->where('estado', 'activo'))
            ->with(['producto.marca', 'producto.tipoLuminaria', 'producto.tipoProducto', 'color']);

        if ($tokens) {
            $consulta->where(function ($w) use ($tokens) {
                foreach ($tokens as $t) {
                    $w->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$t}%")->orWhere('nombre_fabrica', 'like', "%{$t}%")
                        ->orWhere('nombre_kyrios', 'like', "%{$t}%")->orWhere('uso', 'like', "%{$t}%")->orWhere('fuente_luz', 'like', "%{$t}%")
                        ->orWhereHas('tipoLuminaria', fn ($l) => $l->where('nombre', 'like', "%{$t}%"))
                        ->orWhereHas('tipoProducto', fn ($l) => $l->where('nombre', 'like', "%{$t}%")))
                        ->orWhere('codigo_kyrios', 'like', "%{$t}%")->orWhere('codigo_fabricante', 'like', "%{$t}%");
                }
            });
        } elseif ($potencia) {
            $consulta->whereBetween('potencia_w', [$potencia * 0.85, $potencia * 1.15]);
        } else {
            return [];
        }

        $puntuados = $consulta->limit(400)->get()
            ->map(fn (ProductoVariante $v) => ['v' => $v, 'puntaje' => $this->puntuar($fila, $tokens, $v)])
            ->filter(fn ($x) => $x['puntaje'] >= 20)
            ->sortByDesc('puntaje')->take($max)->values();

        $precios = $this->precios($puntuados->pluck('v.producto_id')->unique()->all());

        return $puntuados->map(function ($x) use ($precios) {
            /** @var ProductoVariante $v */
            $v = $x['v'];

            return [
                'producto_id' => $v->producto_id,
                'variante_id' => $v->id,
                'nombre'      => EtiquetaProducto::nombre($v->producto),
                'codigo'      => EtiquetaProducto::codigos($v->producto, $v),
                'detalle'     => $v->nombre_completo !== 'Variante base' ? $v->nombre_completo : null,
                'puntaje'     => min(100, (int) round($x['puntaje'])),
                'precio'      => $this->precioDe($precios, $v->producto_id, $v->id),
            ];
        })->all();
    }

    private function puntuar(array $fila, array $tokens, ProductoVariante $v): float
    {
        $p = $v->producto;
        $texto = $this->normalizar(implode(' ', array_filter([
            EtiquetaProducto::textoBusqueda($p, $v), $p->tipoLuminaria?->nombre, $p->tipoProducto?->nombre, $p->uso, $p->fuente_luz, $p->aplicacion_2,
        ])));

        $puntaje = 0.0;
        if ($tokens) {
            $encontrados = collect($tokens)->filter(fn ($t) => str_contains($texto, $t))->count();
            $puntaje += 50 * $encontrados / count($tokens);
        }

        if (($w = $this->numero($fila['potencia'])) && $v->potencia_w) {
            $dif = abs((float) $v->potencia_w - $w) / $w;
            $puntaje += $dif < 0.01 ? 20 : ($dif <= 0.10 ? 12 : 0);
        }
        if (($k = $this->numero($fila['temperatura'])) && $v->temperatura_color) {
            $dif = abs($v->temperatura_color - $k);
            $puntaje += $dif <= 100 ? 10 : ($dif <= 500 ? 5 : 0);
        }
        if (($ip = $this->numero($fila['ip'])) && ($v->ip ?: $p->ip) && $this->numero($v->ip ?: $p->ip) >= $ip) {
            $puntaje += 10;
        }
        if ($fila['color'] !== '') {
            $deseado = $this->normalizar($fila['color']);
            $propio = $this->normalizar(implode(' ', array_filter([$v->color?->nombre, $v->acabado_1, $v->acabado_2])));
            if ($deseado !== '' && $propio !== '' && (str_contains($propio, $deseado) || str_contains($deseado, $propio))) {
                $puntaje += 10;
            }
        }

        return $puntaje;
    }

    // ── Precios ───────────────────────────────────────────────────────────

    private function precios(array $productoIds)
    {
        return ProductoPrecio::where('tipo_precio', 'venta_regular')->where('activo', true)->whereNull('almacen_id')
            ->whereIn('producto_id', $productoIds)->orderByDesc('prioridad')->get();
    }

    /** Precio de venta con IGV de la variante (o del producto si no tiene uno propio). */
    private function precioDe($precios, int $productoId, ?int $varianteId): ?float
    {
        $p = $precios->first(fn ($x) => $x->producto_id === $productoId && $x->variante_id === $varianteId)
            ?? $precios->first(fn ($x) => $x->producto_id === $productoId && $x->variante_id === null);

        return $p ? round((float) $p->precio * ($p->incluye_igv ? 1 : CotizacionService::IGV), 2) : null;
    }

    // ── Texto ─────────────────────────────────────────────────────────────

    public function normalizar(string $t): string
    {
        $t = mb_strtolower(trim($t));

        return preg_replace('/\s+/', ' ', strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']));
    }

    /** Palabras útiles de una descripción (sin acentos, sin conectores). */
    private function palabras(string $t): array
    {
        $partes = preg_split('/[^a-z0-9]+/', $this->normalizar($t), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($partes, fn ($p) => strlen($p) >= 3 && !in_array($p, self::PARASITAS, true) && !ctype_digit($p))));
    }

    private function numero(string $t): ?float
    {
        return preg_match('/\d+(?:[.,]\d+)?/', $t, $m) ? (float) str_replace(',', '.', $m[0]) : null;
    }
}
