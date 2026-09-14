<?php

namespace App\Models\Tiendanube;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioReglaVersion extends Model
{
    public $timestamps = false;

    protected $table = 'tiendanube_precio_regla_versiones';

    protected $fillable = [
        'regla_id',
        'numero',
        'contract_version',
        'definicion',
        'utilizable',
        'motivo_no_utilizable',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'regla_id' => 'integer',
            'numero' => 'integer',
            'definicion' => 'array',
            'utilizable' => 'boolean',
            'created_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioRegla::class, 'regla_id');
    }
}
