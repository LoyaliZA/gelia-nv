<?php

namespace App\Models\Escalonamiento;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalonamientoReglaVersion extends Model
{
    protected $table = 'escalonamiento_reglas_versiones';

    protected $fillable = [
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function periodos(): HasMany
    {
        return $this->hasMany(EscalonamientoPeriodo::class, 'escalonamiento_regla_version_id');
    }
}
