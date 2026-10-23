<?php

namespace App\Http\Requests;

use App\Models\Venta;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Venta $venta */
        $venta = $this->route('venta');

        if (!$venta) return false;

        // Solo Administrador y Tienda pueden editar
        if (!in_array(auth()->user()->role->nombre, ['Administrador', 'Tienda'])) {
            return false;
        }

        // No editar si está anulado
        if ($venta->estado_pago === 'anulado') {
            return false;
        }

        // Ventana de tiempo configurable
        $ventanaMaxima = config('ventas.edit_window_hours', 24);
        if ($venta->created_at->diffInHours(now()) > $ventanaMaxima) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            // Campos base
            'observaciones'     => 'nullable|string|max:500',
            'metodo_pago'       => 'nullable|in:efectivo,transferencia,yape,plin,mixto',
            'fecha'             => 'nullable|date|before_or_equal:today',
            'tipo_comprobante'  => 'nullable|in:boleta,factura,nota_venta,ticket',

            // Datos de envío (campos en ventas)
            'guia_remision'     => 'nullable|string|max:20',
            'transportista'     => 'nullable|string|max:200',
            'placa_vehiculo'    => 'nullable|string|max:10',

            // Guía de remisión (modelo GuiaRemision)
            'guia.motivo_traslado'       => 'nullable|string|max:50',
            'guia.modalidad'             => 'nullable|in:privado,publico',
            'guia.fecha_traslado'        => 'nullable|date',
            'guia.peso_total'            => 'nullable|numeric|min:0',
            'guia.bultos'               => 'nullable|integer|min:0',
            'guia.direccion_partida'     => 'nullable|string|max:300',
            'guia.ubigeo_partida'        => 'nullable|string|max:6',
            'guia.direccion_llegada'     => 'nullable|string|max:300',
            'guia.ubigeo_llegada'        => 'nullable|string|max:6',
            'guia.transportista_tipo_doc'=> 'nullable|string|max:10',
            'guia.transportista_doc'     => 'nullable|string|max:15',
            'guia.transportista_nombre'  => 'nullable|string|max:200',
            'guia.conductor_dni'         => 'nullable|string|digits:8',
            'guia.conductor_nombre'      => 'nullable|string|max:200',
            'guia.conductor_licencia'    => 'nullable|string|max:20',
            'guia.placa_vehiculo'        => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.before_or_equal'     => 'La fecha no puede ser futura.',
            'tipo_comprobante.in'       => 'Tipo de comprobante no válido.',
            'guia.modalidad.in'         => 'La modalidad debe ser privado o público.',
            'guia.fecha_traslado.date'  => 'La fecha de traslado no es válida.',
        ];
    }
}
