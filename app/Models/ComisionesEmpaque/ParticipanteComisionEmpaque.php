<?php

namespace App\Models\ComisionesEmpaque;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParticipanteComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_participantes';

    protected $fillable = ['nombre', 'referencia_interna', 'user_id', 'activo', 'observaciones'];

    protected $casts = ['activo' => 'boolean'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionComisionEmpaque::class, 'participante_id');
    }
}
