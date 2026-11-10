<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso interno del sistema (solo dentro de la aplicación, sin correo).
 * `clave` identifica el asunto para no repetir el mismo aviso mientras siga sin leer.
 */
class AvisoSistema extends Notification
{
    public function __construct(
        public string $clave,
        public string $titulo,
        public string $mensaje,
        public ?string $url = null,
        public string $nivel = 'info',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'clave'   => $this->clave,
            'titulo'  => $this->titulo,
            'mensaje' => $this->mensaje,
            'url'     => $this->url,
            'nivel'   => $this->nivel,
        ];
    }
}
