<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoBancoDepartamento extends Model
{
    protected $table = 'catalogo_banco_departamento';

    protected $fillable = [
        'catalogo_banco_id',
        'departamento_id',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function banco(): BelongsTo
    {
        return $this->belongsTo(CatalogoBanco::class, 'catalogo_banco_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }
}
