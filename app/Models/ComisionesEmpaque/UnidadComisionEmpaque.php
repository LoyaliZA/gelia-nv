<?php

namespace App\Models\ComisionesEmpaque;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadComisionEmpaque extends Model
{
    public const PENDIENTE_REVISION = 'pendiente_revision';

    public const RECHAZADA = 'rechazada';

    public const PENDIENTE_CLASIFICACION = 'pendiente_clasificacion';

    public const PENDIENTE_TARIFA = 'pendiente_tarifa';

    public const ELEGIBLE = 'elegible';

    public const EXCLUIDA = 'excluida';

    public const PENDIENTE_DECISION = 'pendiente_decision';

    public const EN_CORTE = 'en_corte';

    public const REVERTIDA_REVISION = 'revertida_revision';

    public const REEMPAQUE_REVISION = 'reempaque_revision';

    public const SOLAPE_REVISION = 'solape_revision';

    public const POSIBLE_DUPLICADO = 'posible_duplicado';

    public const ETIQUETAS = [
        self::PENDIENTE_REVISION => 'Pendiente de revisión',
        self::RECHAZADA => 'Rechazada',
        self::PENDIENTE_CLASIFICACION => 'Pendiente de clasificación',
        self::PENDIENTE_TARIFA => 'Sin tarifa para la fecha',
        self::ELEGIBLE => 'Elegible',
        self::EXCLUIDA => 'Excluida',
        self::PENDIENTE_DECISION => 'Cancelación por decidir',
        self::EN_CORTE => 'En corte',
        self::REVERTIDA_REVISION => 'Reversión por revisar',
        self::REEMPAQUE_REVISION => 'Reempaque por revisar',
        self::SOLAPE_REVISION => 'Posible solape por revisar',
        self::POSIBLE_DUPLICADO => 'Posible duplicado',
    ];

    public const POR_REVISAR = [
        self::PENDIENTE_REVISION,
        self::PENDIENTE_CLASIFICACION,
        self::PENDIENTE_TARIFA,
        self::PENDIENTE_DECISION,
        self::REVERTIDA_REVISION,
        self::REEMPAQUE_REVISION,
        self::SOLAPE_REVISION,
        self::POSIBLE_DUPLICADO,
    ];

    protected $table = 'comisiones_empaque_unidades';

    protected $fillable = [
        'clave_origen', 'area_id', 'pedido_bma_id', 'procedencia', 'origen_registro', 'origen_tipo', 'origen_id',
        'empacado_at', 'incorporado_at', 'empacador_user_id', 'capturado_por_id', 'empacador_nombre', 'cliente_nombre', 'folio_pedido',
        'folio_remision', 'logistica', 'cantidad_origen', 'cantidad_comisionable', 'importe_centavos', 'situacion',
        'revision_estado', 'revision_motivo', 'revision_por_id', 'revision_at', 'corte_id', 'incorporacion_tardia',
        'excepcion_resguardo', 'huella', 'ancla_decision', 'ancla_huella', 'ancla_motivo', 'ancla_por_id', 'ancla_at',
        'clave_idempotencia', 'posible_duplicado', 'duplicados_relacionados', 'duplicados_reconocidos',
        'operacion_folio', 'es_complemento', 'diferencia', 'notas',
    ];

    protected $casts = [
        'empacado_at' => 'datetime',
        'incorporado_at' => 'datetime',
        'revision_at' => 'datetime',
        'ancla_at' => 'datetime',
        'logistica' => 'array',
        'posible_duplicado' => 'boolean',
        'duplicados_relacionados' => 'array',
        'duplicados_reconocidos' => 'array',
        'cantidad_origen' => 'integer',
        'cantidad_comisionable' => 'integer',
        'importe_centavos' => 'integer',
        'incorporacion_tardia' => 'boolean',
        'excepcion_resguardo' => 'boolean',
        'es_complemento' => 'boolean',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaComisionEmpaque::class, 'area_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(UnidadLineaComisionEmpaque::class, 'unidad_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(IncidenciaComisionEmpaque::class, 'unidad_id');
    }

    public function corte(): BelongsTo
    {
        return $this->belongsTo(CorteComisionEmpaque::class, 'corte_id');
    }

    public function empacador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empacador_user_id');
    }
}
