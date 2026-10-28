<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\Almacen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $esPendienteCobro  = $this->input('condicion_pago') === 'pendiente_cobro';

        return [
            // Cliente: opcional en ventas pendientes de cobro (las cotizaciones tienen su propio módulo)
            'cliente_id'               => ($esPendienteCobro)
                                            ? ['nullable', Rule::exists('clientes', 'id')->where('estado', 'activo')]
                                            : ['required', Rule::exists('clientes', 'id')->where('estado', 'activo')],

            'almacen_id'               => 'required|exists:almacenes,id',
            'observaciones'            => 'nullable|string',
            'tipo_comprobante'         => 'required|in:boleta,factura,nota_venta',

            // Guía de remisión (nueva estructura SUNAT)
            'guia_data'                              => 'nullable|array',
            'guia_data.motivo_traslado'              => 'required_with:guia_data|string|in:VENTA,COMPRA,TRASLADO_ENTRE_ALMACENES,IMPORTACION,EXPORTACION,OTROS',
            'guia_data.modalidad'                    => 'required_with:guia_data|in:privado,publico',
            'guia_data.fecha_traslado'               => 'required_with:guia_data|date',
            'guia_data.peso_total'                   => 'required_with:guia_data|numeric|min:0.01',
            'guia_data.bultos'                       => 'nullable|integer|min:1',
            'guia_data.direccion_partida'            => 'required_with:guia_data|string|max:300',
            'guia_data.ubigeo_partida'               => 'nullable|string|max:6',
            'guia_data.direccion_llegada'            => 'required_with:guia_data|string|max:300',
            'guia_data.ubigeo_llegada'               => 'nullable|string|max:6',
            'guia_data.transportista_tipo_doc'       => 'nullable|string|in:RUC,DNI',
            'guia_data.transportista_doc'            => 'nullable|string|max:15',
            'guia_data.transportista_nombre'         => 'nullable|string|max:200',
            'guia_data.conductor_dni'                => 'nullable|string|digits:8',
            'guia_data.conductor_nombre'             => 'nullable|string|max:200',
            'guia_data.conductor_licencia'           => 'nullable|string|max:20',
            'guia_data.placa_vehiculo'               => 'nullable|string|max:20',

            // Campos legado (se mantienen nullable por compatibilidad)
            'guia_remision'            => 'nullable|string|max:100',
            'transportista'            => 'nullable|string|max:150',
            'placa_vehiculo'           => 'nullable|string|max:20',

            // Pago y condición
            'condicion_pago'           => 'nullable|in:contado,credito,pendiente_cobro',
            'metodo_pago'              => 'nullable|in:efectivo,transferencia,yape,plin,mixto',
            'pagos_detalle'            => 'nullable|array',
            'pagos_detalle.*.metodo'    => 'required_with:pagos_detalle|in:efectivo,transferencia,yape,plin',
            'pagos_detalle.*.monto'     => 'required_with:pagos_detalle|numeric|min:0.01',
            'pagos_detalle.*.referencia'=> 'nullable|string|max:100',

            // Crédito
            'credito'                        => 'nullable|array',
            'credito.numero_cuotas'          => 'required_if:condicion_pago,credito|integer|min:1|max:24',
            'credito.dias_entre_cuotas'      => 'required_if:condicion_pago,credito|in:7,15,30',
            'credito.fecha_inicio'           => 'required_if:condicion_pago,credito|date|after_or_equal:today',

            // Detalle de productos
            'detalles'                       => 'required|array|min:1',
            'detalles.*.producto_id'         => 'required|exists:productos,id',
            'detalles.*.variante_id'         => 'nullable|exists:producto_variantes,id',
            'detalles.*.cantidad'            => 'required|integer|min:1|max:100000',
            'detalles.*.precio_unitario'     => 'required|numeric|min:0.01|max:9999999',
            'detalles.*.incluye_igv'         => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Debe seleccionar un cliente para registrar la venta.',
            'cliente_id.exists'   => 'El cliente seleccionado no es válido o está inactivo.',
            'detalles.required'   => 'Debe agregar al menos un producto.',
            'detalles.min'        => 'Debe agregar al menos un producto.',
        ];
    }

    /**
     * Regla extra: si el comprobante es FACTURA, el cliente debe tener RUC de 11 dígitos.
     */
    public function withValidator(Validator $validator): void
    {
        // Solo se vende desde una tienda activa y, si el usuario tiene una asignada, desde la suya (el POS
        // ya filtra así la lista; aquí se exige también en el servidor).
        $validator->after(function (Validator $v) {
            if ($v->errors()->has('almacen_id') || !$this->input('almacen_id')) {
                return;
            }
            $almacen = Almacen::find($this->input('almacen_id'));
            if (!$almacen) {
                return;
            }
            if ($almacen->tipo !== 'tienda' || $almacen->estado !== 'activo') {
                $v->errors()->add('almacen_id', 'Solo se puede vender desde una tienda activa.');

                return;
            }
            $usuario = $this->user();
            if ($usuario && $usuario->role?->nombre !== 'Administrador' && $usuario->almacen_id && (int) $usuario->almacen_id !== (int) $almacen->id) {
                $v->errors()->add('almacen_id', 'Solo puede vender desde la tienda que tiene asignada.');
            }
        });

        $validator->after(function (Validator $v) {
            $tipo      = $this->input('tipo_comprobante');
            $clienteId = $this->input('cliente_id');

            if ($tipo !== 'factura') {
                return;
            }

            if (!$clienteId) {
                $v->errors()->add(
                    'cliente_id',
                    'Para emitir factura debe seleccionar un cliente con RUC.'
                );
                return;
            }

            $cliente = Cliente::find($clienteId);

            if (!$cliente) {
                return; // ya lo captura la regla 'exists'
            }

            if ($cliente->tipo_documento !== 'RUC') {
                $v->errors()->add(
                    'cliente_id',
                    'Para emitir factura, el cliente debe tener RUC (actualmente ' . ($cliente->tipo_documento ? 'tiene ' . $cliente->tipo_documento : 'no tiene documento') . ').'
                );
                return;
            }

            if (strlen($cliente->numero_documento) !== 11) {
                $v->errors()->add(
                    'cliente_id',
                    'Para emitir factura, el RUC debe tener exactamente 11 dígitos.'
                );
            }
        });

        // Crédito: requiere cliente y no puede tener metodo_pago
        $validator->after(function (Validator $v) {
            if ($this->input('condicion_pago') === 'credito') {
                if (!$this->input('cliente_id')) {
                    $v->errors()->add('cliente_id', 'Para ventas a crédito debe seleccionar un cliente.');
                }
                if ($this->input('metodo_pago')) {
                    $v->errors()->add('metodo_pago', 'Las ventas a crédito no tienen método de pago inmediato.');
                }
            }
        });

        // Guía: transporte público requiere datos del transportista
        $validator->after(function (Validator $v) {
            $guia = $this->input('guia_data');
            if (!$guia) return;

            if (($guia['modalidad'] ?? '') === 'publico' && empty($guia['transportista_nombre'])) {
                $v->errors()->add('guia_data.transportista_nombre', 'Para transporte público debe ingresar la razón social del transportista.');
            }
        });
    }
}
