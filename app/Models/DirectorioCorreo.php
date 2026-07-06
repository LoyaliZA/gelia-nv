<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectorioCorreo extends Model
{
    use SoftDeletes;

    protected $table = 'directorio_correos';
    
    protected $fillable = [
        'rh_colaborador_id',
        'user_id',
        'email',
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
