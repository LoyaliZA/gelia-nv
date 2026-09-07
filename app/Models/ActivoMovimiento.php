<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivoMovimiento extends Model
{
    use BelongsToUsuario;
    protected $table = 'activo_movimientos';

    protected $fillable = [
        'activo_id',
        'usuario_id',
        'tipo',
        'departamento_origen_id',
        'departamento_destino_id',
        'user_destino_id',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
        'notas',
        'datos_snapshot',
    ];

    protected $casts = [
        'datos_snapshot' => 'array',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsToUsuario('usuario_id');
    }

    public function userDestino(): BelongsTo
    {
        return $this->belongsToUsuario('user_destino_id');
    }

    public function departamentoOrigen(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_origen_id');
    }

    public function departamentoDestino(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_destino_id');
    }
}
