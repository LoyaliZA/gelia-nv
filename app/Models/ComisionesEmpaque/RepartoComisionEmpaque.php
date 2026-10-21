<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RepartoComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_repartos';

    protected $fillable = ['area_id', 'permanente', 'nombre', 'motivo', 'vigente_desde', 'vigente_hasta', 'creado_por_id'];

    protected $casts = [
        'permanente' => 'boolean',
        'vigente_desde' => 'datetime',
        'vigente_hasta' => 'datetime',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaComisionEmpaque::class, 'area_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(RepartoParticipanteComisionEmpaque::class, 'reparto_id');
    }

    public static function permanente(int $areaId): ?self
    {
        return static::query()->with('lineas.participante')->where('area_id', $areaId)
            ->where('permanente', true)->whereNull('vigente_hasta')->latest('id')->first();
    }

    public static function vigente(int $areaId, Carbon $momento): ?self
    {
        return static::query()
            ->with('lineas.participante')
            ->where('area_id', $areaId)
            ->where('permanente', false)
            ->where('vigente_desde', '<=', $momento)
            ->where(function ($q) use ($momento) {
                $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $momento);
            })
            ->orderByDesc('vigente_desde')
            ->first();
    }
}
