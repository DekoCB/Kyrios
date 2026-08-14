<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\SerieComprobante;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;

class SucursalService
{
    /**
     * Crear una nueva sucursal con:
     *  - Código autogenerado
     *  - Almacén automático vinculado
     *  - Series de comprobantes estándar
     */
    public function crear(array $datos): Sucursal
    {
        return DB::transaction(function () use ($datos) {
            // 1. Auto-código
            $datos['codigo'] = Sucursal::generarCodigo();

            // Si es la primera sucursal, siempre es principal
            if (Sucursal::count() === 0) {
                $datos['es_principal'] = true;
            }

            // 2. Crear sucursal (sin almacen_id aún)
            $sucursal = Sucursal::create($datos);

            // 3. Crear almacén automático vinculado a la sucursal
            $esTienda    = ($datos['tipo'] ?? 'tienda') === 'tienda';
            $tipoAlmacen = $esTienda ? 'tienda' : 'deposito';
            $prefixNombre = $esTienda ? 'Tienda ' : 'Almacén ';

            $almacen = Almacen::create([
                'nombre'      => $prefixNombre . $sucursal->nombre,
                'codigo'      => 'ALM-' . $sucursal->codigo,
                'direccion'   => $sucursal->direccion,
                'tipo'        => $tipoAlmacen,
                'sucursal_id' => $sucursal->id,
                'estado'      => 'activo',
            ]);

            // 4. Vincular almacén a sucursal
            $sucursal->update(['almacen_id' => $almacen->id]);

            // 5. Generar series estándar solo para tiendas (las tiendas emiten comprobantes)
            if ($esTienda) {
                $numSucursal = (int) substr($sucursal->codigo, 1); // S001 → 1
                $this->generarSeriesEstandar($sucursal, $numSucursal);
            }

            return $sucursal->fresh(['almacen', 'series']);
        });
    }

    /**
     * Generar las series estándar de SUNAT para una sucursal.
     * Número de sucursal se usa como sufijo: FA01, BA01 → FA02, BA02.
     */
    public function generarSeriesEstandar(Sucursal $sucursal, int $numero = 1): void
    {
        $sufijo = str_pad($numero, 2, '0', STR_PAD_LEFT); // 01, 02 …

        foreach (SerieComprobante::TIPOS_ESTANDAR as $tipo) {
            // Reemplazar {N} con el número de sucursal
            $serie = str_replace('{N}', $sufijo, $tipo['serie_template']);

            // Evitar duplicados (por serie exacta: puede haber más de una fila con el
            // mismo tipo_comprobante, ej. "07" para Factura y "07" para Boleta)
            if (!SerieComprobante::where('sucursal_id', $sucursal->id)
                ->where('serie', $serie)->exists()) {
                SerieComprobante::create([
                    'sucursal_id'       => $sucursal->id,
                    'tipo_comprobante'  => $tipo['tipo_comprobante'],
                    'tipo_nombre'       => $tipo['tipo_nombre'],
                    'serie'             => $serie,
                    'correlativo_actual'=> 1,
                    'formato_impresion' => $tipo['formato_impresion'],
                    'activo'            => true,
                ]);
            }
        }
    }

    /**
     * Obtener la serie activa para un tipo de comprobante y sucursal.
     */
    public function obtenerSerie(int $sucursalId, string $tipoComprobante): ?SerieComprobante
    {
        return SerieComprobante::where('sucursal_id', $sucursalId)
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('activo', true)
            ->first();
    }
}
