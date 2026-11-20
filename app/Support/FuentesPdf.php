<?php

namespace App\Support;

use Dompdf\Dompdf;

/**
 * Tipografías propias para los PDF. La ficha técnica usa Carlito (clon libre de Calibri, la letra del formato original).
 * Se registran una sola vez en el caché de dompdf; si no se pudiera escribir, el PDF sale con la letra por defecto.
 */
class FuentesPdf
{
    public static function carlito(Dompdf $dompdf): void
    {
        try {
            $metricas = $dompdf->getFontMetrics();
            $registradas = $metricas->getFontFamilies()['carlito'] ?? [];
            foreach (['normal' => 'Carlito-Regular.ttf', 'bold' => 'Carlito-Bold.ttf'] as $peso => $archivo) {
                if (isset($registradas[$peso])) {
                    continue;
                }
                $metricas->registerFont(['family' => 'Carlito', 'weight' => $peso, 'style' => 'normal'], public_path('fonts/carlito/' . $archivo));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
