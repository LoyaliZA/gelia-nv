<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperacionGestionAuditoriaPdv extends Model
{
    protected $table = 'pdv_operacion_gestion_auditoria';

    protected $fillable = [
        'actor_id',
        'user_id',
        'sucursal_id',
        'accion',
        'estado_anterior',
        'estado_nuevo',
        'contexto',
        'registrado_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'registrado_at' => 'datetime',
            'contexto' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function usuarioAfectado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
