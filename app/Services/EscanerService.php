<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoCodigoBarras;
use App\Models\ProductoVariante;
use App\Models\StockAlmacen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de códigos (el lector del PDA escribe como un teclado) para ubicar variantes y recibir mercadería.
 *
 * Un código puede ser: Kyrios (KY-…), del fabricante, del Excel anterior, SKU, o un código de barras
 * (de la variante, o del producto: en ese caso se devuelven todas sus variantes para elegir).
 */
class EscanerService
{
    /** Quita espacios, saltos de línea y caracteres de control que algunos lectores agregan. */
    public function normalizar(string $codigo): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $codigo));
    }

    /** @return Collection variantes activas que coinciden con el código */
    public function buscar(string $codigo): Collection
    {
        $codigo = $this->normalizar($codigo);
        if ($codigo === '') {
            return collect();
        }

        $variantes = ProductoVariante::activas()
            ->where(fn ($q) => $q->where('codigo_kyrios', $codigo)->orWhere('sku', $codigo)
                ->orWhere('codigo_fabricante', $codigo)->orWhere('codigo_excel', $codigo))
            ->get();

        if ($variantes->isEmpty()) {
            $variantes = ProductoVariante::activas()
                ->whereIn('id', ProductoCodigoBarras::where('codigo_barras', $codigo)->whereNotNull('variante_id')->pluck('variante_id'))
                ->get();
        }

        if ($variantes->isEmpty()) {
            // Código de barras del producto (sin variante): se ofrecen todas sus variantes
            $productoIds = ProductoCodigoBarras::where('codigo_barras', $codigo)->whereNull('variante_id')->pluck('producto_id')
                ->merge(Producto::where('codigo_barras', $codigo)->pluck('id'))->unique();
            $variantes = ProductoVariante::activas()->whereIn('producto_id', $productoIds)->get();
        }

        return $variantes->load(['producto:id,nombre,nombre_fabrica,marca_id', 'producto.marca:id,nombre', 'imagenPrincipal']);
    }

    public function formatear(ProductoVariante $v): array
    {
        return [
            'id'                => $v->id,
            'producto_id'       => $v->producto_id,
            'nombre'            => $v->producto?->nombre,
            'marca'             => $v->producto?->marca?->nombre,
            'codigo_kyrios'     => $v->codigo_kyrios ?? $v->sku,
            'codigo_fabricante' => $v->codigo_fabricante,
            'detalle'           => $v->nombre_completo,
            'stock_actual'      => (int) $v->stock_actual,
            'estado_dano'       => $v->estado_dano,
            'imagen'            => $v->imagenPrincipal?->url,
        ];
    }

    /** ¿El código ya está usado como código de barras? (producto, variante o tabla de códigos) */
    public function codigoEnUso(string $codigo): bool
    {
        return ProductoCodigoBarras::where('codigo_barras', $codigo)->exists()
            || Producto::where('codigo_barras', $codigo)->exists();
    }

    /** Asocia un código de barras escaneado a una variante (se hace una sola vez por caja). */
    public function asociar(ProductoVariante $variante, string $codigo): ProductoCodigoBarras
    {
        $codigo = $this->normalizar($codigo);
        if ($codigo === '' || mb_strlen($codigo) > 50) {
            throw new \InvalidArgumentException('El código está vacío o supera los 50 caracteres.');
        }
        if ($this->codigoEnUso($codigo)) {
            throw new \RuntimeException('Ese código ya está asociado a otro producto o variante.');
        }

        return ProductoCodigoBarras::create([
            'producto_id'   => $variante->producto_id,
            'variante_id'   => $variante->id,
            'codigo_barras' => $codigo,
            'descripcion'   => 'Asociado con el escáner',
            'es_principal'  => !ProductoCodigoBarras::where('variante_id', $variante->id)->exists(),
        ]);
    }

    /**
     * Ingreso de mercadería por cantidad (mismo criterio que una compra: stock de la variante en el
     * almacén, stock total de la variante y movimiento de inventario con la variante).
     *
     * @param array<int, array{variante_id:int, cantidad:int}> $lineas
     * @return int cantidad de movimientos creados
     */
    public function recibir(int $almacenId, array $lineas, int $userId, ?string $referencia = null): int
    {
        return DB::transaction(function () use ($almacenId, $lineas, $userId, $referencia) {
            // Varias lecturas de la misma variante se suman
            $porVariante = [];
            foreach ($lineas as $l) {
                $porVariante[$l['variante_id']] = ($porVariante[$l['variante_id']] ?? 0) + (int) $l['cantidad'];
            }

            $n = 0;
            foreach ($porVariante as $varianteId => $cantidad) {
                if ($cantidad < 1) {
                    continue;
                }
                $variante = ProductoVariante::with('producto')->lockForUpdate()->findOrFail($varianteId);
                $producto = $variante->producto;

                if ($producto->tipo_inventario === 'serie') {
                    throw new \RuntimeException("{$producto->nombre} se controla por serie/IMEI y no puede recibirse por cantidad.");
                }

                // Stock por variante y almacén
                $stock = StockAlmacen::fila($producto->id, $almacenId, $variante->id);
                $anterior = $stock->cantidad;
                $stock->increment('cantidad', $cantidad);
                $variante->increment('stock_actual', $cantidad);
                $producto->update(['stock_actual' => StockAlmacen::where('producto_id', $producto->id)->sum('cantidad')]);

                MovimientoInventario::create([
                    'producto_id'          => $producto->id,
                    'variante_id'          => $variante->id,
                    'almacen_id'           => $almacenId,
                    'user_id'              => $userId,
                    'tipo_movimiento'      => 'ingreso',
                    'cantidad'             => $cantidad,
                    'stock_anterior'       => $anterior,
                    'stock_nuevo'          => $anterior + $cantidad,
                    'motivo'               => 'Recepción con escáner',
                    'documento_referencia' => $referencia,
                ]);
                $n++;
            }

            return $n;
        });
    }
}
