<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudFacturaRemision extends Model
{
    protected $table = 'solicitud_factura_remisiones';

    protected $fillable = [
        'solicitud_id',
        'path',
        'nombre_original',
        'orden',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTag::class, 'solicitud_id');
    }
}
