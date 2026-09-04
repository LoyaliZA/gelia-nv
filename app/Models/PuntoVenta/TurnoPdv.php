<?php

namespace App\Models\PuntoVenta;

use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\User;
use Database\Factories\PuntoVenta\TurnoPdvFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TurnoPdv extends Model
{
    use HasFactory;

    public const ESTADO_EN_COLA = 'EN_COLA';

    public const ESTADO_ASIGNADO = 'ASIGNADO';

    public const ESTADO_EN_REATENCION = 'EN_REATENCION';

    public const ESTADO_CERRADO = 'CERRADO';

    public const SERVICIO_VENTAS = 'ventas';

    public const ORIGEN_RECEPCION = 'recepcion';

    public const PRIORIDAD_NORMAL = 'normal';

    protected $table = 'pdv_turnos';

    protected $fillable = [
        'sucursal_id',
        'cliente_id',
        'folio',
        'servicio',
        'origen',
        'estado',
        'prioridad',
        'prioridad_adulto_mayor',
        'prioridad_discapacidad',
        'prioridad_diamante',
        'prioridad_vip',
        'snapshot_nombre_llamado',
        'snapshot_cliente_nombre',
        'snapshot_json',
        'alta_at',
        'cerrado_at',
        'reatencion_expira_at',
        'alta_por_id',
        'baja_por_id',
        'baja_at',
        'baja_motivo',
        'baja_motivo_detalle',
        'atencion_actual_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'prioridad_adulto_mayor' => 'boolean',
            'prioridad_discapacidad' => 'boolean',
            'prioridad_diamante' => 'boolean',
            'prioridad_vip' => 'boolean',
            'snapshot_json' => 'array',
            'alta_at' => 'datetime',
            'cerrado_at' => 'datetime',
            'reatencion_expira_at' => 'datetime',
            'baja_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function newFactory(): TurnoPdvFactory
    {
        return TurnoPdvFactory::new();
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function altaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alta_por_id');
    }

    public function bajaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'baja_por_id');
    }

    public function atencionActual(): BelongsTo
    {
        return $this->belongsTo(TurnoPdvAtencion::class, 'atencion_actual_id');
    }

    public function atenciones(): HasMany
    {
        return $this->hasMany(TurnoPdvAtencion::class, 'turno_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(TurnoPdvEvento::class, 'turno_id');
    }
}
