<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaItem extends Model
{
    protected $table = 'tarea_items';

    protected $fillable = ['tarea_id', 'texto', 'hecho', 'orden'];

    protected $casts = ['hecho' => 'boolean'];

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }
}
