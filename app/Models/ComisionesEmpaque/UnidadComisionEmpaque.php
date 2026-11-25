<?php

namespace App\Models\ComisionesEmpaque;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadComisionEmpaque extends Model
{
    public const PENDIENTE_REVISION = 'pendiente_revision';

    public const PENDIENTE_CONCILIACION = 'pendiente_conciliacion';

    public const MOTIVOS_COMERCIALES = [
        'cobrado_remision' => 'Cobrado en remisión',
        'por_cobrar' => 'Envío por cobrar',
        'caja_pagada' => 'Caja pagada por el cliente',
        'guia_cliente' => 'Guía proporcionada por el cliente',
        'otro_autorizado' => 'Otro motivo autorizado',
        'sin_informacion' => 'Sin información suficiente',
        'captura_incorrecta' => 'Captura incorrecta o empaque no comisionable',
    ];

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

    public const LIQUIDADA_FUERA = 'liquidada_fuera';

    public const LIQUIDACION_PENDIENTE = 'pendiente';

    public const LIQUIDACION_PAGADO_FUERA = 'pagado_fuera';

    public const ETIQUETAS = [
        self::PENDIENTE_CONCILIACION => 'Pendiente de conciliación',
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
        self::LIQUIDADA_FUERA => 'Pagado fuera',
    ];

    public const POR_REVISAR = [
        self::PENDIENTE_CONCILIACION,
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
        'folio_remision', 'logistica', 'cantidad_origen', 'cantidad_comisionable', 'importe_centavos', 'situacion', 'liquidacion',
        'revision_estado', 'revision_motivo', 'revision_por_id', 'revision_at', 'corte_id', 'incorporacion_tardia',
        'excepcion_resguardo', 'huella', 'ancla_decision', 'ancla_huella', 'ancla_motivo', 'ancla_por_id', 'ancla_at',
        'clave_idempotencia', 'posible_duplicado', 'duplicados_relacionados', 'duplicados_reconocidos',
        'operacion_folio', 'es_complemento', 'diferencia', 'notas', 'conciliacion',
    ];

    protected $casts = [
        'empacado_at' => 'datetime',
        'incorporado_at' => 'datetime',
        'revision_at' => 'datetime',
        'ancla_at' => 'datetime',
        'logistica' => 'array',
        'conciliacion' => 'array',
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

    public function conciliacionVigente(): ?array
    {
        return ($this->conciliacion['huella'] ?? null) === $this->huella
            && ($this->conciliacion['fuente'] ?? null) === ($this->logistica['huella_comercial'] ?? null)
            ? $this->conciliacion
            : null;
    }

    public static function fuenteYaEnCorte(?string $tipo, ?int $origenId, ?int $excepto = null): bool
    {
        return $origenId !== null && static::query()->whereNotNull('corte_id')
            ->where('origen_registro', 'sincronizado')->where('origen_tipo', $tipo)->where('origen_id', $origenId)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto))->exists();
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(AuditoriaComisionEmpaque::class, 'entidad_id')->where('entidad', 'unidad');
    }

    public function versionRevision(): string
    {
        $auditoria = $this->getAttributes()['auditorias_max_id'] ?? $this->auditorias()->max('id');

        return hash('sha256', json_encode([
            $auditoria, $this->area_id, $this->huella, $this->logistica['huella_comercial'] ?? null,
            $this->situacion, $this->corte_id, $this->posible_duplicado, $this->duplicados_relacionados,
        ]));
    }

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
