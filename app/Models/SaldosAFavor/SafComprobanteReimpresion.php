<?php

namespace App\Models\SaldosAFavor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafComprobanteReimpresion extends Model
{
    protected $table = 'saf_comprobante_reimpresiones';

    protected $fillable = [
        'saf_comprobante_caja_id',
        'usuario_id',
        'perfil_impresion',
    ];

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(SafComprobanteCaja::class, 'saf_comprobante_caja_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
