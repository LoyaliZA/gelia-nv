<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiRecurso extends Model
{
    protected $table = 'api_recursos';

    protected $fillable = [
        'slug',
        'nombre',
        'descripcion',
        'activo',
        'lectura_habilitada',
        'escritura_habilitada',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'lectura_habilitada' => 'boolean',
            'escritura_habilitada' => 'boolean',
        ];
    }

    public function campos(): HasMany
    {
        return $this->hasMany(ApiCampoRecurso::class)->orderBy('orden');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(ApiAplicacionPermiso::class);
    }
}
