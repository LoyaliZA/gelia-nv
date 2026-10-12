<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoBanco extends Model
{
    protected $table = 'catalogo_bancos';

    protected $fillable = [
        'nombre',
        'activo',
        'compartido',
        'facturacion_habilitada',
        'motivo_bloqueo_facturacion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'compartido' => 'boolean',
        'facturacion_habilitada' => 'boolean',
    ];

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudTag::class, 'catalogo_banco_id');
    }

    public function departamentos(): HasMany
    {
        return $this->hasMany(CatalogoBancoDepartamento::class, 'catalogo_banco_id');
    }
}
