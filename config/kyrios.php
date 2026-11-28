<?php

/**
 * Listas de filtros del catálogo Kyrios (hojas "Estilo", "Tipo de instalación" y "Tipo de proyecto" del Excel).
 * En la base se guarda el identificador (slug, sin tildes); aquí está el nombre que se muestra.
 * 'alias' = cabeceras del Excel (minúsculas, sin tildes) que corresponden a otro identificador.
 */
return [
    'estilos' => [
        'clasico' => 'Clásico', 'clasico-moderno' => 'Clásico-moderno', 'moderno' => 'Moderno',
        'contemporaneo' => 'Contemporáneo', 'minimalista' => 'Minimalista', 'rustico' => 'Rústico',
        'nautico' => 'Náutico', 'vintage' => 'Vintage', 'industrial' => 'Industrial', 'tech' => 'Tech',
        'nordico' => 'Nórdico', 'ingles' => 'Inglés',
    ],

    'tipos_instalacion' => [
        'colgante' => 'Colgante', 'colgante-doble-altura' => 'Colgante doble altura', 'plafon' => 'Plafón',
        'aplique' => 'Aplique', 'sobre-mesa' => 'Sobre mesa', 'pie' => 'Pie', 'escritorio' => 'Escritorio',
        'lectura' => 'Lectura', 'empotrado-de-techo' => 'Empotrado de techo', 'empotrado-de-piso' => 'Empotrado de piso',
        'empotrado-sobre-muro' => 'Empotrado sobre muro', 'ventilador' => 'Ventilador', 'estacas' => 'Estacas',
        'balizas' => 'Balizas', 'empotrado-sumergible' => 'Empotrado sumergible',
        'luminarias-portatiles' => 'Luminarias portátiles', 'proyectores' => 'Proyectores',
        'sistema-de-riel' => 'Sistema de riel', 'tiras-led' => 'Tiras LED', 'postes' => 'Postes', 'luz-guia' => 'Luz guía',
    ],

    'tipos_proyecto' => [
        'residencial' => 'Residencial', 'comercial' => 'Comercial', 'oficina' => 'Oficina', 'hotelero' => 'Hotelero',
        'restaurante' => 'Restaurante', 'laboratorio' => 'Laboratorio', 'centro-medico' => 'Centro médico',
        'estacion-de-servicios' => 'Estación de servicios', 'paisajismo' => 'Paisajismo', 'clubes' => 'Clubes',
        'condominios' => 'Condominios', 'urbano' => 'Urbano', 'galerias-y-museos' => 'Galerías y museos',
    ],

    /*
     * Cotizaciones: valores por defecto del formulario (se pueden cambiar en cada cotización).
     */
    // Proformas: el número oficial es IL-{correlativo de 5 dígitos}/{año}; «inicio» es el primer correlativo a usar
    'proforma' => [
        'inicio' => (int) env('PROFORMA_INICIO', 1),
    ],

    // Recursos humanos: control de asistencia
    'rrhh' => [
        'tolerancia_min'   => (int) env('RRHH_TOLERANCIA_MIN', 10),
        // Marcar asistencia desde el sistema exige selfie y ubicación (apagar solo en pruebas o si un puesto no tiene cámara)
        'exigir_foto_ubicacion' => (bool) env('RRHH_EXIGIR_FOTO_UBICACION', true),
        'dias_laborables'  => [1, 2, 3, 4, 5], // 1 = lunes … 7 = domingo
        // Huellero (reloj biométrico). Con token se activa la recepción de marcaciones desde un puente en la red del cliente.
        'huellero_token'          => env('HUELLERO_TOKEN'),
        'huellero_minutos_salida' => (int) env('HUELLERO_MINUTOS_SALIDA', 60),
    ],

    'cotizacion' => [
        'validez_dias' => 7,
        'condiciones'  => "Precios en soles (PEN) e incluyen IGV.
La disponibilidad está sujeta a stock al momento de confirmar la compra.
Forma de pago: contado o transferencia.",
    ],

    // Usuario de Administración inicial (php artisan db:seed). Se lee aquí para que funcione con config:cache.
    // Fuera de local/testing el seeder exige ADMIN_EMAIL y ADMIN_PASSWORD.
    'admin' => [
        'nombre'   => env('ADMIN_NAME', 'Administración'),
        'email'    => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'alias' => [
        'estilos' => ['contenporaneo' => 'contemporaneo'],
        'tipos_instalacion' => ['empotrados sumergibles' => 'empotrado-sumergible'],
    ],
];
