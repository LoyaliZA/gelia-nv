<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionUsuario extends Model
{
    protected $table = 'configuraciones_usuarios';

    protected $fillable = [
        'user_id',
        'tema_visual'
    ];

    // Magia de Laravel: Convierte el JSON a Array automáticamente al consultar
    protected $casts = [
        'tema_visual' => 'array', 
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}