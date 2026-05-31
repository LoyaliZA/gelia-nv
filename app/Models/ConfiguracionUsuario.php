<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionUsuario extends Model
{
    protected $table = 'configuraciones_usuarios';

    protected $fillable = [
        'user_id',
        'tema_visual',
        'dashboard_prefs',
        'presencia',
    ];

    protected $casts = [
        'tema_visual' => 'array',
        'dashboard_prefs' => 'array',
        'presencia' => 'array',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}