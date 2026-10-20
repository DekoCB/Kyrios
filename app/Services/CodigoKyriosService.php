<?php

namespace App\Services;

use App\Models\Catalogo\Marca;
use App\Models\Catalogo\TipoLuminaria;
use App\Models\Catalogo\TipoProducto;
use Illuminate\Support\Facades\DB;

/**
 * Codificación KYRIOS:  KY-{tipo producto}{tipo luminaria}{marca}-{correlativo}
 *   Caso 1 (luminaria, emergencia, solares, recargables): KY-SO1404-0015
 *   Caso 2 (lámpara, cinta LED, accesorio, fuente, ...):  KY-LA0029-0004  (luminaria = "00")
 * El correlativo se reinicia por cada combinación tipo + luminaria + marca.
 */
class CodigoKyriosService
{
    public const PREFIJO = 'KY';
    public const SIN_VALOR = '00';

    public function prefijoPara(TipoProducto $tipo, ?TipoLuminaria $luminaria, ?Marca $marca): string
    {
        $lum = $tipo->usaTipoLuminaria() ? ($luminaria?->codigo ?: self::SIN_VALOR) : self::SIN_VALOR;

        return $tipo->codigo . $lum . ($marca?->codigo ?: self::SIN_VALOR);
    }

    /** Reserva el siguiente correlativo y devuelve [código completo, correlativo]. */
    public function generar(TipoProducto $tipo, ?TipoLuminaria $luminaria, ?Marca $marca): array
    {
        $prefijo = $this->prefijoPara($tipo, $luminaria, $marca);

        $correlativo = DB::transaction(function () use ($prefijo) {
            $fila = DB::table('correlativos_codigo')->where('prefijo', $prefijo)->lockForUpdate()->first();

            if (!$fila) {
                DB::table('correlativos_codigo')->insert([
                    'prefijo' => $prefijo, 'ultimo' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                return 1;
            }

            DB::table('correlativos_codigo')->where('id', $fila->id)
                ->update(['ultimo' => $fila->ultimo + 1, 'updated_at' => now()]);

            return $fila->ultimo + 1;
        });

        return [$this->formatear($prefijo, $correlativo), $correlativo];
    }

    public function formatear(string $prefijo, int $correlativo): string
    {
        return self::PREFIJO . '-' . $prefijo . '-' . str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT);
    }
}
