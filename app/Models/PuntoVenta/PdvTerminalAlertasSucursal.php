<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvTerminalAlertasSucursal extends Model
{
    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_LIBERADA = 'liberada';

    public const ESTADO_VENCIDA = 'vencida';

    protected $table = 'pdv_terminal_alertas_sucursal';

    protected $fillable = [
        'terminal_id',
        'sucursal_id',
        'user_id',
        'estado',
        'activada_at',
        'ultima_senal_at',
        'liberada_at',
    ];

    protected function casts(): array
    {
        return [
            'activada_at' => 'datetime',
            'ultima_senal_at' => 'datetime',
            'liberada_at' => 'datetime',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
