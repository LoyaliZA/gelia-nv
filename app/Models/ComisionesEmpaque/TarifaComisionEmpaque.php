<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TarifaComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_tarifas';

    protected $fillable = ['area_id', 'importe_centavos', 'vigente_desde', 'vigente_hasta', 'creado_por_id'];

    protected $casts = [
        'importe_centavos' => 'integer',
        'vigente_desde' => 'datetime',
        'vigente_hasta' => 'datetime',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaComisionEmpaque::class, 'area_id');
    }

    public static function vigente(int $areaId, Carbon $momento): ?self
    {
        return static::query()
            ->where('area_id', $areaId)
            ->where('vigente_desde', '<=', $momento)
            ->where(function ($q) use ($momento) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $momento);
            })
            ->orderByDesc('vigente_desde')
            ->first();
    }
}
