<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContadorFolioTurnoPdv extends Model
{
    protected $table = 'pdv_contadores_folio';

    protected $fillable = [
        'sucursal_id',
        'fecha_operativa',
        'servicio',
        'ultimo_numero',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'fecha_operativa' => 'date',
            'ultimo_numero' => 'integer',
            'version' => 'integer',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
