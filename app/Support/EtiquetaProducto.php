<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Cómo se muestra un producto en pantallas, PDFs y buscadores: nombre K y nombre de fábrica
 * juntos, y el código Kyrios junto al código de fábrica. Acepta modelos o arreglos.
 */
class EtiquetaProducto
{
    /** Nombre Kyrios (o, si no hay, el nombre guardado / el de fábrica). */
    public static function nombrePrincipal($producto): string
    {
        $k = self::texto($producto, 'nombre_kyrios');
        $f = self::texto($producto, 'nombre_fabrica');
        $n = self::texto($producto, 'nombre');

        return $k ?: ($n && !self::esCodigo($producto, $n) ? $n : ($f ?: ($n ?: self::texto($producto, 'codigo'))));
    }

    /**
     * «Ring (fábrica: Oxygen)». Si solo existe uno de los dos nombres, o son iguales, se muestra uno solo.
     */
    public static function nombre($producto): string
    {
        $principal = self::nombrePrincipal($producto);
        $f = self::texto($producto, 'nombre_fabrica');

        if ($f !== '' && self::normalizar($f) !== self::normalizar($principal)) {
            return "{$principal} (fábrica: {$f})";
        }

        return $principal;
    }

    /**
     * «KY-LU2501-0001 · fab: 25-0573-N3-B8». Con variante usa sus códigos; sin ella, el código del producto
     * y el de fábrica de la primera variante cargada.
     */
    public static function codigos($producto, $variante = null): string
    {
        $kyrios = $fabrica = '';

        if ($variante !== null && $variante !== []) {
            $kyrios = self::texto($variante, 'codigo_kyrios') ?: self::texto($variante, 'sku');
            $fabrica = self::texto($variante, 'codigo_fabricante');
        } else {
            $kyrios = self::texto($producto, 'codigo');
            $variantes = self::variantesCargadas($producto);
            if (is_iterable($variantes)) {
                foreach ($variantes as $v) {
                    $fabrica = self::texto($v, 'codigo_fabricante');
                    if ($fabrica !== '') {
                        break;
                    }
                }
            }
        }

        $partes = array_values(array_filter([$kyrios, $fabrica !== '' && $fabrica !== $kyrios ? "fab: {$fabrica}" : null]));

        return implode(' · ', $partes);
    }

    /**
     * Completa la lista de productos de un selector (arreglos con 'id', 'nombre' y 'variantes' con 'id'/'etiqueta'):
     * el nombre pasa a «K (fábrica: F)», se agrega 'codigos' y cada variante muestra ambos códigos.
     * Usa dos consultas en total, sin importar cuántos productos haya.
     */
    public static function enriquecerSelector(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        $ids = $items->pluck('id')->filter()->all();
        if (!$ids) {
            return $items;
        }
        $productos = \App\Models\Producto::whereIn('id', $ids)->get(['id', 'codigo', 'nombre', 'nombre_fabrica', 'nombre_kyrios'])->keyBy('id');
        $variantes = \App\Models\ProductoVariante::whereIn('producto_id', $ids)->get(['id', 'producto_id', 'sku', 'codigo_kyrios', 'codigo_fabricante'])->keyBy('id');

        return $items->map(function ($item) use ($productos, $variantes) {
            $producto = $productos[$item['id']] ?? null;
            if (!$producto) {
                return $item;
            }
            $item['nombre'] = self::nombre($producto);
            $item['codigos'] = self::codigos($producto, $variantes->firstWhere('producto_id', $producto->id));
            $item['texto_busqueda'] = mb_strtolower(implode(' ', array_filter(array_merge(
                [$producto->nombre, $producto->nombre_kyrios, $producto->nombre_fabrica, $producto->codigo],
                $variantes->where('producto_id', $producto->id)->flatMap(fn ($v) => [$v->codigo_kyrios, $v->codigo_fabricante, $v->sku])->all()
            ))));
            $item['variantes'] = collect($item['variantes'] ?? [])->map(function ($v) use ($variantes, $producto) {
                $fila = $variantes[$v['id']] ?? null;
                if ($fila) {
                    $codigos = self::codigos($producto, $fila);
                    $etiqueta = (string) ($v['etiqueta'] ?? '');
                    $posicion = strpos($etiqueta, ' · ');
                    $detalle = $posicion === false ? '' : trim(substr($etiqueta, $posicion + strlen(' · ')));
                    $v['codigos'] = $codigos;
                    $v['etiqueta'] = $codigos . ($detalle !== '' ? ' · ' . $detalle : '');
                }

                return $v;
            });

            return $item;
        });
    }

    /** Texto en minúsculas con nombres y códigos de un producto (y su variante) para filtros del lado del navegador. */
    public static function textoBusqueda($producto, $variante = null): string
    {
        $partes = [
            self::texto($producto, 'nombre'), self::texto($producto, 'nombre_kyrios'), self::texto($producto, 'nombre_fabrica'), self::texto($producto, 'codigo'),
            self::texto($variante, 'codigo_kyrios'), self::texto($variante, 'codigo_fabricante'), self::texto($variante, 'codigo_excel'), self::texto($variante, 'sku'),
        ];

        return mb_strtolower(implode(' ', array_filter($partes)));
    }

    /** Búsqueda libre por nombre, nombre K, nombre de fábrica y todos los códigos (producto y variantes). */
    public static function buscar(Builder $consulta, string $termino): Builder
    {
        $t = trim($termino);
        if ($t === '') {
            return $consulta;
        }
        $like = '%' . addcslashes($t, '%_\\') . '%';

        return $consulta->where(function ($q) use ($like) {
            $q->where('nombre', 'like', $like)
                ->orWhere('nombre_fabrica', 'like', $like)
                ->orWhere('nombre_kyrios', 'like', $like)
                ->orWhere('codigo', 'like', $like)
                ->orWhereHas('variantes', function ($v) use ($like) {
                    $v->where('codigo_kyrios', 'like', $like)
                        ->orWhere('codigo_fabricante', 'like', $like)
                        ->orWhere('codigo_excel', 'like', $like)
                        ->orWhere('sku', 'like', $like);
                });
        });
    }

    /**
     * Variantes ya disponibles en memoria. Con un modelo solo se usan las relaciones cargadas: pedirlas aquí
     * haría una consulta por cada producto de una lista.
     */
    private static function variantesCargadas($producto)
    {
        if ($producto instanceof \Illuminate\Database\Eloquent\Model) {
            foreach (['variantes', 'variantesActivas'] as $relacion) {
                if ($producto->relationLoaded($relacion)) {
                    return $producto->getRelation($relacion);
                }
            }

            return [];
        }

        return data_get($producto, 'variantes');
    }

    private static function texto($origen, string $campo): string
    {
        $v = data_get($origen, $campo);

        return is_scalar($v) ? trim((string) $v) : '';
    }

    private static function normalizar(string $t): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($t)));
    }

    /** ¿El «nombre» guardado es en realidad un código (el de fábrica o el Kyrios)? */
    private static function esCodigo($producto, string $nombre): bool
    {
        $n = self::normalizar($nombre);
        if ($n === self::normalizar(self::texto($producto, 'codigo'))) {
            return true;
        }
        $variantes = self::variantesCargadas($producto);
        if (is_iterable($variantes)) {
            foreach ($variantes as $v) {
                if ($n === self::normalizar(self::texto($v, 'codigo_fabricante')) || $n === self::normalizar(self::texto($v, 'codigo_kyrios'))) {
                    return true;
                }
            }
        }

        return false;
    }
}
