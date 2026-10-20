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

    'alias' => [
        'estilos' => ['contenporaneo' => 'contemporaneo'],
        'tipos_instalacion' => ['empotrados sumergibles' => 'empotrado-sumergible'],
    ],
];
