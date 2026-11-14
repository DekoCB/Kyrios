<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ficha de personal de Kyrios (recursos humanos). */
class Empleado extends Model
{
    public const TIPOS_CONTRATO = [
        'indefinido' => 'Plazo indeterminado', 'plazo_fijo' => 'Plazo fijo', 'part_time' => 'Part-time',
        'locacion' => 'Locación de servicios', 'practicas' => 'Prácticas pre-profesionales',
    ];

    public const ESTADOS = ['activo' => 'Activo', 'licencia' => 'En licencia', 'cesado' => 'Cesado'];

    public const AREAS = ['Administración', 'Ventas', 'Proyectos', 'Logística', 'Operaciones', 'Contabilidad', 'Diseño', 'Otra'];

    public const ESTADOS_CIVILES = ['soltero' => 'Soltero(a)', 'casado' => 'Casado(a)', 'conviviente' => 'Conviviente', 'divorciado' => 'Divorciado(a)', 'viudo' => 'Viudo(a)'];

    protected $fillable = [
        'codigo', 'user_id', 'nombres', 'apellidos', 'dni', 'fecha_nacimiento', 'sexo', 'estado_civil', 'direccion', 'telefono',
        'email_personal', 'contacto_emergencia', 'telefono_emergencia', 'parentesco_emergencia',
        'cargo', 'area', 'tipo_contrato', 'fecha_ingreso', 'fecha_fin_contrato', 'fecha_cese', 'motivo_cese', 'sueldo_basico',
        'hora_entrada', 'hora_salida', 'estado', 'sistema_pension', 'afp_nombre', 'cuspp', 'banco', 'numero_cuenta', 'notas',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date', 'fecha_ingreso' => 'date', 'fecha_fin_contrato' => 'date', 'fecha_cese' => 'date',
        'sueldo_basico' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $e) {
            if (!$e->codigo) {
                $e->codigo = self::siguienteCodigo();
            }
        });
    }

    public static function siguienteCodigo(): string
    {
        $n = (int) self::max('id') + 1;

        return 'EMP-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudRrhh::class)->latest('desde');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(EmpleadoDocumento::class)->latest();
    }

    public function scopeActivos($q)
    {
        return $q->where('estado', '!=', 'cesado');
    }

    public function scopeBuscar($q, ?string $texto)
    {
        $texto = trim((string) $texto);

        return $texto === '' ? $q : $q->where(function ($w) use ($texto) {
            $w->where('nombres', 'like', "%{$texto}%")->orWhere('apellidos', 'like', "%{$texto}%")
                ->orWhere('dni', 'like', "%{$texto}%")->orWhere('codigo', 'like', "%{$texto}%")->orWhere('cargo', 'like', "%{$texto}%");
        });
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public function getInicialesAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->nombres, 0, 1) . mb_substr($this->apellidos, 0, 1));
    }

    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }

    /** Tiempo en la empresa, p. ej. «2 años, 3 meses». */
    public function getAntiguedadAttribute(): string
    {
        $fin = $this->fecha_cese ?? today();
        $d = $this->fecha_ingreso->diff($fin);
        $partes = array_filter([$d->y ? $d->y . ($d->y === 1 ? ' año' : ' años') : null, $d->m ? $d->m . ($d->m === 1 ? ' mes' : ' meses') : null]);

        return $partes ? implode(', ', $partes) : ($d->days . ' días');
    }

    public function getTipoContratoNombreAttribute(): string
    {
        return self::TIPOS_CONTRATO[$this->tipo_contrato] ?? $this->tipo_contrato;
    }

    /** Contrato a plazo fijo que vence en los próximos 30 días. */
    public function getContratoPorVencerAttribute(): bool
    {
        return $this->estado !== 'cesado' && $this->fecha_fin_contrato && $this->fecha_fin_contrato->between(today(), today()->addDays(30));
    }

    /** Cumpleaños en el mes actual. */
    public function getCumpleEsteMesAttribute(): bool
    {
        return $this->fecha_nacimiento && $this->fecha_nacimiento->month === today()->month;
    }
}
