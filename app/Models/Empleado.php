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

    public const GRADOS_INSTRUCCION = ['Secundaria completa', 'Técnico incompleto', 'Técnico completo', 'Superior incompleto', 'Superior completo', 'Postgrado'];

    public const NIVELES = ['basico' => 'Básico', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'];

    public const TIPOS_REMUNERACION = ['empleado' => 'Empleado', 'operario' => 'Operario'];

    public const MODALIDADES_PAGO = ['tiempo_parcial' => 'Tiempo parcial', 'tiempo_completo' => 'Tiempo completo', 'ejecutivo' => 'Ejecutivo', 'practicante' => 'Practicante', 'otro' => 'Otro'];

    public const LOCALES = ['san_isidro' => 'San Isidro', 'la_molina' => 'La Molina', 'la_victoria' => 'La Victoria', 'otros' => 'Otros'];

    public const CALIFICACIONES = [
        'direccion'                  => 'Dirección',
        'confianza_no_fiscalizado'   => 'Confianza – no sujeto a fiscalización inmediata',
        'confianza_fiscalizado'      => 'Confianza – sujeto a fiscalización inmediata',
        'no_fiscalizado'             => 'No sujeto a fiscalización inmediata (intermitente, vigilancia o custodia)',
        'control_asistencia'         => 'Sujeto a control de asistencia',
    ];

    public const CONDICIONES_PROFESION = ['egresado' => 'Egresado', 'bachiller' => 'Bachiller', 'titulado' => 'Titulado'];

    public const SITUACIONES_PENSION = [
        'jubilado'      => 'Jubilado (indicar el régimen pensionario actual)',
        'afiliado_afp'  => 'Afiliado a una AFP',
        'afiliado_onp'  => 'Afiliado al Sistema Nacional de Pensiones (ONP)',
        'desea_onp'     => 'Desea aportar a la ONP',
        'desea_afp'     => 'Desea afiliarse a una AFP (por ley, la de menor comisión: Habitat)',
    ];

    public const REGIMENES_JUBILADO = ['D.L. 19990', 'D.L. 20530', 'Caja de Pensiones Militar', 'Caja de Pensiones Policial', 'AFP', 'Otros'];

    public const AFPS = ['Habitat', 'Integra', 'Prima', 'Profuturo', 'Horizonte'];

    /** Documentos y declaraciones del paquete de ingreso (páginas 9 a 13 de la ficha); se guarda la fecha en que se firmó cada una. */
    public const DECLARACIONES = [
        'ficha'        => 'Ficha del colaborador firmada',
        'antecedentes' => 'Declaración jurada de antecedentes penales',
        'delitos'      => 'Declaración jurada de delitos (Ley 29988)',
        'sst'          => 'Declaración jurada de riesgos de seguridad y salud en el trabajo',
        'parentesco'   => 'Declaración jurada de parentesco y relación sentimental',
        'perfil'       => 'Declaración jurada de conocimiento del perfil de puesto',
        'vida_ley'     => 'Declaración jurada de póliza de Seguro Vida Ley (extrabajador)',
        'reglamento'   => 'Confirmación del Reglamento Interno de Trabajo y de Seguridad y Salud',
        'salud'        => 'Declaración de salud',
    ];

    protected $fillable = [
        'codigo', 'user_id', 'nombres', 'apellidos', 'dni', 'fecha_nacimiento', 'sexo', 'estado_civil', 'direccion', 'telefono',
        'email_personal', 'contacto_emergencia', 'telefono_emergencia', 'parentesco_emergencia',
        'cargo', 'area', 'tipo_contrato', 'fecha_ingreso', 'fecha_fin_contrato', 'fecha_cese', 'motivo_cese', 'sueldo_basico',
        'hora_entrada', 'hora_salida', 'estado', 'sistema_pension', 'afp_nombre', 'cuspp', 'banco', 'numero_cuenta', 'notas',
        'nacionalidad', 'grado_instruccion', 'lugar_nacimiento', 'tipo_sangre', 'domicilio_alterno', 'referencia_domiciliaria', 'jefe_contrata', 'foto', 'codigo_huellero',
        'centro_costos', 'periodo_prueba', 'tipo_remuneracion', 'modalidad_pago', 'modalidad_pago_otro', 'local_trabajo', 'local_otro', 'calificacion',
        'puesto_solicitado', 'fecha_disponibilidad', 'sueldo_minimo', 'viajaria_provincias', 'trabajo_antes_kyrios', 'conocidos_kyrios', 'familiares_kyrios',
        'educacion', 'profesion', 'profesion_condicion', 'idiomas', 'software', 'otros_estudios', 'experiencia', 'dependientes', 'familiares', 'referencias',
        'posee_cuenta_sueldo', 'moneda_pago', 'pension_situacion', 'pension_regimen', 'declaraciones', 'vida_ley_extrabajador', 'vida_ley', 'enfermedades', 'sin_enfermedades',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date', 'fecha_ingreso' => 'date', 'fecha_fin_contrato' => 'date', 'fecha_cese' => 'date',
        'sueldo_basico' => 'decimal:2', 'fecha_disponibilidad' => 'date', 'sueldo_minimo' => 'decimal:2',
        'viajaria_provincias' => 'boolean', 'trabajo_antes_kyrios' => 'boolean', 'posee_cuenta_sueldo' => 'boolean',
        'vida_ley_extrabajador' => 'boolean', 'sin_enfermedades' => 'boolean',
        'educacion' => 'array', 'idiomas' => 'array', 'software' => 'array', 'otros_estudios' => 'array', 'experiencia' => 'array',
        'dependientes' => 'array', 'familiares' => 'array', 'referencias' => 'array', 'declaraciones' => 'array', 'vida_ley' => 'array', 'enfermedades' => 'array',
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

    /** Qué tan completa está la ficha (0 a 100): cuenta los datos clave del formato oficial. */
    public function getFichaCompletaAttribute(): int
    {
        $campos = ['nacionalidad', 'grado_instruccion', 'lugar_nacimiento', 'fecha_nacimiento', 'direccion', 'telefono', 'email_personal', 'estado_civil', 'sexo',
            'tipo_sangre', 'contacto_emergencia', 'telefono_emergencia', 'jefe_contrata', 'centro_costos', 'tipo_remuneracion', 'calificacion', 'pension_situacion', 'banco', 'foto'];
        $listas = ['educacion', 'experiencia', 'referencias', 'dependientes'];
        $total = count($campos) + count($listas) + 1;
        $llenos = collect($campos)->filter(fn ($c) => filled($this->{$c}))->count()
            + collect($listas)->filter(fn ($c) => !empty($this->{$c}))->count()
            + (count(array_filter((array) $this->declaraciones)) >= count(self::DECLARACIONES) ? 1 : 0);

        return (int) round($llenos / $total * 100);
    }

    /** Declaraciones del paquete de ingreso que todavía no tienen fecha de firma. */
    public function getDeclaracionesPendientesAttribute(): array
    {
        $firmadas = array_filter((array) $this->declaraciones);

        return array_values(array_diff_key(self::DECLARACIONES, $firmadas));
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? route('rrhh.empleados.foto', $this) : null;
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
