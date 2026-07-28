<?php

namespace App\Models\ControlPedidos;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperacionEmpaque extends Model
{
    protected $table = 'operaciones_empaque';

    public const ESTATUS_ABIERTA = 'abierta';
    public const ESTATUS_EMPACADA = 'empacada';
    public const ESTATUS_ENVIADA = 'enviada';

    protected $fillable = [
        'folio_operacion',
        'cliente_id',
        'numero_cajas',
        'peso_real_kg',
        'empacado_at',
        'empacado_por_id',
        'estatus',
    ];

    protected $casts = [
        'numero_cajas' => 'integer',
        'peso_real_kg' => 'decimal:4',
        'empacado_at' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empacadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empacado_por_id');
    }

    public function miembros(): HasMany
    {
        return $this->hasMany(OperacionEmpaqueMiembro::class, 'operacion_empaque_id')->orderBy('orden');
    }

    public function estaAbierta(): bool
    {
        return $this->estatus === self::ESTATUS_ABIERTA;
    }

    public function sumaPiezas(): int
    {
        return (int) $this->miembros->sum(fn (OperacionEmpaqueMiembro $m) => (int) ($m->cantidad_piezas ?? 0));
    }

    public function sumaMercancia(): float
    {
        return round((float) $this->miembros->sum(
            fn (OperacionEmpaqueMiembro $m) => (float) ($m->pedido?->total_mercancia ?? 0)
        ), 2);
    }
}
