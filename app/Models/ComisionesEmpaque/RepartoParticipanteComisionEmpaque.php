<?php

namespace App\Models\ComisionesEmpaque;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepartoParticipanteComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_reparto_participantes';

    protected $fillable = ['reparto_id', 'participante_id', 'puntos_base'];

    protected $casts = ['puntos_base' => 'integer'];

    public function participante(): BelongsTo
    {
        return $this->belongsTo(ParticipanteComisionEmpaque::class, 'participante_id');
    }

    public function reparto(): BelongsTo
    {
        return $this->belongsTo(RepartoComisionEmpaque::class, 'reparto_id');
    }
}
