<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudFacturaPdf extends Model
{
    protected $table = 'solicitud_factura_pdfs';

    protected $fillable = [
        'solicitud_factura_id',
        'path',
        'nombre_original',
        'mime',
        'orden',
    ];

    public function solicitudFactura(): BelongsTo
    {
        return $this->belongsTo(SolicitudFactura::class, 'solicitud_factura_id');
    }
}
