<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsignacionComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_asignaciones';

    protected $fillable = [
        'corte_id', 'participante_id', 'reparto_id', 'tramo_desde', 'tramo_hasta', 'puntos_base',
        'importe_centavos', 'pagado_centavos',
    ];

    protected $casts = [
        'tramo_desde' => 'datetime',
        'tramo_hasta' => 'datetime',
        'puntos_base' => 'integer',
        'importe_centavos' => 'integer',
        'pagado_centavos' => 'integer',
    ];

    public function participante(): BelongsTo
    {
        return $this->belongsTo(ParticipanteComisionEmpaque::class, 'participante_id');
    }

    public function corte(): BelongsTo
    {
        return $this->belongsTo(CorteComisionEmpaque::class, 'corte_id');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(AjusteComisionEmpaque::class, 'asignacion_id');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PagoAplicacionComisionEmpaque::class, 'asignacion_id');
    }

    public function ajusteAplicadoCentavos(): int
    {
        $ajustes = $this->relationLoaded('ajustes') ? $this->ajustes : $this->ajustes()->get();

        return (int) $ajustes->where('aplicar_en_saldo', true)->sum('importe_centavos');
    }

    public function reconocidoCentavos(): int
    {
        return (int) $this->importe_centavos + $this->ajusteAplicadoCentavos();
    }

    public function pendienteCentavos(): int
    {
        return $this->reconocidoCentavos() - (int) $this->pagado_centavos;
    }
}
