<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Las confirmaciones usan el diálogo del sistema (data-confirm / kyrConfirm), no el del navegador.
 */
class DialogosPropiosTest extends TestCase
{
    public function test_las_vistas_no_usan_confirm_nativo(): void
    {
        $encontrados = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            if ($archivo->isFile() && str_ends_with($archivo->getFilename(), '.blade.php')
                && preg_match('/(?<![\w.])confirm\(/', file_get_contents($archivo->getPathname()))) {
                $encontrados[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $archivo->getPathname());
            }
        }

        $this->assertSame([], $encontrados, 'Usar data-confirm="…" o window.kyrConfirm(…) en lugar de confirm()');
    }

    public function test_el_modulo_de_dialogos_se_carga_con_la_app(): void
    {
        $this->assertStringContainsString("import './dialogos'", file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString('window.kyrConfirm', file_get_contents(resource_path('js/dialogos.js')));
    }
}
