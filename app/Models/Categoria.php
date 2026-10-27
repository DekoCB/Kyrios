<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Catalogo\Marca;

class Categoria extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'codigo',
        'imagen',
        'estado',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => 'string',
    ];

    /**
     * Relación: Una categoría tiene muchas marcas (many-to-many)
     */
    public function marcas()
    {
        return $this->belongsToMany(Marca::class, 'categoria_marca');
    }

    /**
     * Relación: Una categoría tiene muchos productos
     */
    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    /**
     * Scope para categorías activas
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para categorías inactivas
     */
    public function scopeInactivas($query)
    {
        return $query->where('estado', 'inactivo');
    }

    /**
     * Accessor: Obtener URL completa de la imagen
     */
    public function getImagenUrlAttribute()
    {
        return $this->imagen 
            ? asset('storage/' . $this->imagen) 
            : asset('images/no-image.png');
    }

    /**
     * Obtener cantidad total de productos en esta categoría
     */
    public function getTotalProductosAttribute()
    {
        return $this->productos()->count();
    }

    /**
     * Obtener cantidad de productos activos en esta categoría
     */
    public function getTotalProductosActivosAttribute()
    {
        return $this->productos()->where('estado', 'activo')->count();
    }

    /**
     * Generar código automático para nueva categoría
     */
    public static function generarCodigo()
    {
        $ultimaCategoria = self::latest('id')->first();
        $numero = $ultimaCategoria ? $ultimaCategoria->id + 1 : 1;
        return 'CAT-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Antes de eliminar, verificar que no tenga productos
        static::deleting(function ($categoria) {
            if ($categoria->productos()->count() > 0) {
                throw new \Exception('No se puede eliminar una categoría que tiene productos asociados.');
            }
        });
    }
}