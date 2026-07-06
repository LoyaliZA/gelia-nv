<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectorioTelefono extends Model
{
    use SoftDeletes;

    protected $table = 'directorio_telefonos';
    
    protected $fillable = [
        'rh_colaborador_id',
        'user_id',
        'telefono',
    ];

    public function colaborador()
    {
        return $this->belongsTo(RhColaborador::class, 'rh_colaborador_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
