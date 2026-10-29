<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ventana de edición de comprobantes (horas)
    |--------------------------------------------------------------------------
    | Número máximo de horas desde la emisión durante las cuales un
    | Administración puede editar campos no contables de un comprobante.
    */
    'edit_window_hours' => env('VENTA_EDIT_WINDOW_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Máximo de cuotas permitidas en ventas a crédito
    |--------------------------------------------------------------------------
    */
    'credito_max_cuotas' => env('VENTA_CREDITO_MAX_CUOTAS', 24),

    /*
    |--------------------------------------------------------------------------
    | Opciones de días entre cuotas
    |--------------------------------------------------------------------------
    */
    'credito_dias_opciones' => [7, 15, 30],
];
