<?php

namespace App\Models\PuntoVenta;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResguardoPdvEvento extends Model
{
    public const TIPO_RECEPCION_ESPERADA_CREADA = 'resguardo.recepcion_esperada_creada';

    public const TIPO_RECEPCION_COMPLETA = 'resguardo.recepcion_completa';

    public const TIPO_RECEPCION_PARCIAL = 'resguardo.recepcion_parcial';

    public const TIPO_INCIDENCIA_FOLIO_NO_ENCONTRADO = 'resguardo.incidencia_folio_no_encontrado';

    public const TIPO_INCIDENCIA_DANO = 'resguardo.incidencia_dano';

    public const TIPO_INCIDENCIA_FALTANTE = 'resguardo.incidencia_faltante';

    public const TIPO_INCIDENCIA_ENTREGA_AUTORIZADA = 'resguardo.incidencia_entrega_autorizada';

    public const TIPO_ENTREGA_TITULAR = 'resguardo.entrega_titular';

    public const TIPO_ENTREGA_TERCERO = 'resguardo.entrega_tercero';

    public const TIPO_ENTREGA_MULTIPLE = 'resguardo.entrega_multiple';

    public const TIPO_ENTREGA_PARCIAL = 'resguardo.entrega_parcial';

    public const TIPO_MARCADO_VENCIDO = 'resguardo.marcado_vencido';

    public const TIPO_VENCIDO_REPUESTO = 'resguardo.vencido_repuesto';

    public const TIPO_MARCADO_REZAGADO = 'resguardo.marcado_rezagado';

    public const TIPO_CANCELACION_RECIBIDA = 'resguardo.cancelacion_recibida';

    public const TIPO_DEVOLUCION_CONFIRMADA = 'resguardo.devolucion_confirmada';

    protected $table = 'pdv_resguardo_eventos';

    protected $fillable = [
        'resguardo_id',
        'bulto_id',
        'tipo_evento',
        'estado_anterior',
        'estado_nuevo',
        'actor_id',
        'ocurrido_at',
        'snapshot_json',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'ocurrido_at' => 'datetime',
            'snapshot_json' => 'array',
        ];
    }

    public function resguardo(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdv::class, 'resguardo_id');
    }

    public function bulto(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdvBulto::class, 'bulto_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(ResguardoPdvEvidencia::class, 'evento_id');
    }
}
