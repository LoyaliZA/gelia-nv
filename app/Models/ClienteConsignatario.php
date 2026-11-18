<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteConsignatario extends Model
{
    use SoftDeletes;

    protected $table = 'cliente_consignatarios';

    protected $fillable = ['cliente_id', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(ClienteConsignatarioVersion::class, 'consignatario_id');
    }

    public function versionVigente(): HasOne
    {
        return $this->hasOne(ClienteConsignatarioVersion::class, 'consignatario_id')->where('vigente', true);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(ClienteDocumento::class, 'consignatario_id');
    }

    public function documentoVigente(): HasOne
    {
        return $this->hasOne(ClienteDocumento::class, 'consignatario_id')
            ->where('vigente', true)
            ->where('tipo', ClienteDocumento::TIPO_IDENTIFICACION);
    }
}
