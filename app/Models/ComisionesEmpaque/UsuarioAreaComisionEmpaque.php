<?php

namespace App\Models\ComisionesEmpaque;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioAreaComisionEmpaque extends Model
{
    protected $table = 'comisiones_empaque_usuario_areas';

    protected $fillable = ['user_id', 'area_id'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaComisionEmpaque::class, 'area_id');
    }
}
