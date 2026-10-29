<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombre',
        'contacto',
        'direccion',
        'distrito',
        'provincia',
        'departamento',
        'ubigeo',
        'telefono',
        'email',
        'notas',
        'estado',
        'segmento',
        'listas_difusion',
        'especialidad',
        'cargo',
        'empresa',
        'fecha_cumpleanos',
        'telefono_fijo',
        'anexo',
        'email_empresa',
        'redes_sociales',
        'comision',
        'preferencias',
        'asesor',
        'origen',
        'verificacion',
        'verificado_at',
    ];

    protected $casts = [
        'fecha_cumpleanos' => 'date',
        'listas_difusion'  => 'array',
        'verificado_at'    => 'datetime',
        'comision'         => 'decimal:2',
    ];

    /** Segmentos de la base comercial. */
    public const SEGMENTOS = [
        'ARQ' => 'Arquitecto', 'ING' => 'Ingeniero', 'DIS' => 'Diseñador', 'DEC' => 'Decorador',
        'PN' => 'Persona natural', 'PJ' => 'Persona jurídica', 'CF' => 'Cliente final', 'NEG' => 'Negocio',
    ];

    /** Listas de difusión de WhatsApp (un cliente puede estar en varias). */
    public const LISTAS_DIFUSION = [
        'arquitecto'    => 'Arquitecto/a',
        'ingeniero'     => 'Ingeniero/a',
        'disenador'     => 'Diseñador/a',
        'decorador'     => 'Decorador/a',
        'cliente_final' => 'Clientes finales',
        'paisajista'    => 'Paisajista',
        'mama'          => 'Mamá',
        'papa'          => 'Papá',
        'mujer'         => 'Mujer',
        'hombre'        => 'Hombre',
    ];

    /** Notas de crédito/débito: ajustan una venta, no cuentan como compra. */
    public const TIPOS_NOTA = ['nc_factura', 'nc_boleta', 'nota_credito', 'nota_debito'];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function cotizaciones()
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function cuentasPorCobrar()
    {
        return $this->hasMany(CuentaPorCobrar::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /** Busca por nombre, documento, teléfono, correo o contacto. */
    public function scopeBuscar($query, ?string $termino)
    {
        $termino = trim((string) $termino);
        if ($termino === '') {
            return $query;
        }

        // Los comodines de LIKE (% _ \) que escriba el usuario se buscan tal cual, no como patrón
        $like = '%' . addcslashes($termino, '\%_') . '%';

        return $query->where(function ($q) use ($like) {
            $q->where('nombre', 'like', $like)
                ->orWhere('numero_documento', 'like', $like)
                ->orWhere('telefono', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('contacto', 'like', $like)
                ->orWhere('empresa', 'like', $like);
        });
    }

    /** Clientes que están en alguna de las listas indicadas (claves de LISTAS_DIFUSION). */
    public function scopeEnListas($query, array $claves)
    {
        $claves = array_values(array_intersect($claves, array_keys(self::LISTAS_DIFUSION)));
        if (!$claves) {
            return $query;
        }

        return $query->where(function ($q) use ($claves) {
            foreach ($claves as $clave) {
                // JSON_CONTAINS existe en MariaDB 10.2+ y MySQL 5.7+; el valor se compara como texto JSON
                $q->orWhereRaw('JSON_CONTAINS(listas_difusion, ?)', [json_encode($clave)]);
            }
        });
    }

    /** Etiquetas legibles de las listas del cliente, en el orden de LISTAS_DIFUSION. */
    public function getEtiquetasDifusionAttribute(): array
    {
        $propias = (array) $this->listas_difusion;

        return array_values(array_filter(
            self::LISTAS_DIFUSION,
            fn ($etiqueta, $clave) => in_array($clave, $propias, true),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /** Celular peruano de 9 dígitos que empieza con 9, o null si no hay uno válido (acepta +51 y separadores). */
    public function celularWhatsapp(): ?string
    {
        foreach (preg_split('/[\/,;|]+/', (string) $this->telefono) as $trozo) {
            $digitos = preg_replace('/\D+/', '', $trozo);
            if (strlen($digitos) === 11 && str_starts_with($digitos, '519')) {
                $digitos = substr($digitos, 2);
            }
            if (preg_match('/^9\d{8}$/', $digitos)) {
                return $digitos;
            }
        }

        return null;
    }

    /** Contacto todavía sin documento: no se le puede emitir comprobante hasta completarlo. */
    public function getEsProspectoAttribute(): bool
    {
        return $this->numero_documento === null || $this->numero_documento === '';
    }

    public function scopeProspectos($query)
    {
        return $query->whereNull('numero_documento');
    }

    /** Cliente con RUC (empresa o persona con negocio): puede recibir factura. */
    public function getEsEmpresaAttribute(): bool
    {
        return $this->tipo_documento === 'RUC';
    }

    /** Ventas que cuentan como compra (sin anuladas, canceladas ni cotizaciones antiguas). */
    public function scopeConTotalComprado($query)
    {
        return $query->withSum(
            ['ventas as total_comprado' => fn ($v) => $v->whereNotIn('estado_pago', ['anulado', 'cancelado', 'cotizacion'])
                ->whereNotIn('tipo_comprobante', self::TIPOS_NOTA)],
            'total'
        );
    }
}
