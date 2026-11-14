<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpleadoDocumento extends Model
{
    protected $table = 'empleado_documentos';

    public const TIPOS = ['contrato' => 'Contrato', 'dni' => 'Copia de DNI', 'cv' => 'Currículum', 'certificado' => 'Certificado / título', 'medico' => 'Examen médico', 'otro' => 'Otro'];

    /** Disco privado: los documentos de personal no son públicos. */
    public const DISCO = 'local';

    protected $fillable = ['empleado_id', 'tipo', 'nombre', 'ruta', 'mime', 'vence', 'subido_por'];

    protected $casts = ['vence' => 'date'];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function getTipoNombreAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
