<?php

namespace App\Models\ControlPedidos;

use App\Models\Almacen;
use App\Models\CatalogoBanco;
use App\Models\Cliente;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafIncidencia;
use App\Models\SaldosAFavor\SafPedidoAplicacion;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoBma extends Model
{
    use SoftDeletes;

    protected $table = 'pedidos_bma';

    public const ESTATUS_ENVIO_COMPLETO = 'completo';
    public const ESTATUS_ENVIO_PENDIENTE_REGULARIZACION = 'pendiente_regularizacion';
    public const ESTATUS_ENVIO_PENDIENTE_REVISION_ANEXO = 'pendiente_revision_anexo';
    public const ESTATUS_ENVIO_ANEXO_RECHAZADO = 'anexo_rechazado';
    public const ESTATUS_ENVIO_PENDIENTE_LIBERACION = 'pendiente_liberacion';
    public const ESTATUS_ENVIO_PENDIENTE_CONSOLIDACION = 'pendiente_consolidacion';
    public const ESTATUS_ENVIO_CONSOLIDADO = 'consolidado';
    public const ESTATUS_ENVIO_PENDIENTE_PESAJE = 'pendiente_pesaje';
    public const ESTATUS_ENVIO_PESAJE_LISTO = 'pesaje_listo';

    public const MOTIVO_REPESAJE_ANEXO_PIEZAS = 'anexo_piezas';
    public const MOTIVO_REPESAJE_QUITA_PIEZAS = 'quita_piezas';
    public const MOTIVO_REPESAJE_CAMBIO_SURTIDO = 'cambio_surtido';
    public const MOTIVO_REPESAJE_OTRO = 'otro';

    public const MOTIVOS_REPESAJE = [
        self::MOTIVO_REPESAJE_ANEXO_PIEZAS,
        self::MOTIVO_REPESAJE_QUITA_PIEZAS,
        self::MOTIVO_REPESAJE_CAMBIO_SURTIDO,
        self::MOTIVO_REPESAJE_OTRO,
    ];

    protected $fillable = [
        'folio',
        'folio_remision',
        'fecha',
        'vendedor_id',
        'cliente_id',
        'cliente_direccion_id',
        'origen_id',
        'tipo_operacion_envio_id',
        'pedido_principal_id',
        'almacen_id',
        'catalogo_banco_id',
        'saldo_a_favor',
        'catalogo_tipo_caja_id',
        'numero_cajas',
        'cantidad_piezas',
        'peso_real_kg',
        'peso_volumetrico_kg',
        'peso_cobrado_guia_kg',
        'catalogo_paqueteria_id',
        'catalogo_tipo_guia_id',
        'catalogo_zona_id',
        'catalogo_envio_tienda_id',
        'envio_tienda_otro',
        'codigo_postal',
        'domicilio_entrega',
        'envia_otra_persona',
        'es_resguardo',
        'resguardo_apartado_at',
        'resguardo_apartado_por_id',
        'detalle_resguardo_apartado',
        'anexar_remision',
        'envia_a_otra_persona',
        'total_mercancia',
        'costo_envio',
        'estatus_envio',
        'pesaje_solicitado_at',
        'pesaje_respondido_at',
        'pesaje_respondido_por_id',
        'estado_fisico_general',
        'comentario_fisico_general',
        'tiene_observaciones_fisicas',
        'motivo_repesaje',
        'aplica_seguro',
        'cliente_proporciona_guia',
        'costo_seguro',
        'total_a_cobrar',
        'catalogo_estatus_pedido_id',
        'comentarios_drive',
        'numero_rastreo',
        'guia_subida_at',
        'guia_retraso',
        'guia_corregida_at',
        'guia_corregida_por_id',
        'motivo_rechazo',
        'pago_validado_at',
        'pago_validado_por_id',
        'empacado_at',
        'empacado_por_id',
        'detalle_incidencia_empaque',
        'incidencia_empaque_at',
        'incidencia_empaque_por_id',
        'campos_incorrectos',
        'detalle_error_datos',
        'error_datos_at',
        'error_datos_por_id',
        'motivo_cancelacion',
        'comentario_cancelacion',
        'resolucion_financiera_cancelacion',
        'cancelado_por_id',
        'cancelado_at',
    ];

    protected $casts = [
        'pago_validado_at' => 'datetime',
        'empacado_at' => 'datetime',
        'guia_subida_at' => 'datetime',
        'guia_corregida_at' => 'datetime',
        'guia_retraso' => 'boolean',
        'incidencia_empaque_at' => 'datetime',
        'error_datos_at' => 'datetime',
        'pesaje_solicitado_at' => 'datetime',
        'pesaje_respondido_at' => 'datetime',
        'campos_incorrectos' => 'array',
        'fecha' => 'date',
        'aplica_seguro' => 'boolean',
        'cliente_proporciona_guia' => 'boolean',
        'es_resguardo' => 'boolean',
        'resguardo_apartado_at' => 'datetime',
        'anexar_remision' => 'boolean',
        'envia_a_otra_persona' => 'boolean',
        'tiene_observaciones_fisicas' => 'boolean',
        'cancelado_at' => 'datetime',
        'saldo_a_favor' => 'decimal:2',
        'peso_real_kg' => 'decimal:4',
        'peso_volumetrico_kg' => 'decimal:4',
        'peso_cobrado_guia_kg' => 'decimal:4',
        'total_mercancia' => 'decimal:2',
        'costo_envio' => 'decimal:2',
        'costo_seguro' => 'decimal:2',
        'total_a_cobrar' => 'decimal:2',
        'numero_cajas' => 'integer',
        'cantidad_piezas' => 'integer',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function clienteDireccion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ClienteDireccion::class, 'cliente_direccion_id');
    }

    public function direccionesSnapshot(): HasMany
    {
        return $this->hasMany(PedidoBmaDireccion::class, 'pedido_bma_id');
    }

    public function direccionVigente(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PedidoBmaDireccion::class, 'pedido_bma_id')->where('es_vigente', true);
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(CatalogoOrigenPedido::class, 'origen_id');
    }

    public function tipoOperacionEnvio(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoOperacionEnvio::class, 'tipo_operacion_envio_id');
    }

    public function anexosEnvio(): HasMany
    {
        return $this->hasMany(PedidoBmaAnexoEnvio::class, 'pedido_bma_id')->orderByDesc('created_at');
    }

    public function pagosExhibicion(): HasMany
    {
        return $this->hasMany(PedidoBmaPago::class, 'pedido_bma_id')->orderBy('numero_exhibicion');
    }

    /**
     * Bancos/métodos derivados de exhibiciones; fallback al banco general legacy.
     *
     * @return list<string>
     */
    public function fuentesPagoResumen(): array
    {
        $this->loadMissing(['pagosExhibicion.banco', 'banco']);

        $labels = [];
        $seen = [];
        foreach ($this->pagosExhibicion as $pago) {
            $label = $pago->banco?->nombre
                ?? PedidoBmaPago::labelForma($pago->forma_pago);
            if ($label === null || $label === '') {
                continue;
            }
            $key = mb_strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $labels[] = $label;
        }

        if ($labels === [] && $this->banco?->nombre) {
            $labels[] = $this->banco->nombre;
        }

        return $labels;
    }

    public function puedeEditarExhibicionesPago(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
        ], true);
    }

    public function safAplicaciones(): HasMany
    {
        return $this->hasMany(SafPedidoAplicacion::class, 'pedido_bma_id');
    }

    public function safIncidencias(): HasMany
    {
        return $this->hasMany(SafIncidencia::class, 'pedido_bma_id');
    }

    public function anexoEnvioPendiente(): HasOne
    {
        return $this->hasOne(PedidoBmaAnexoEnvio::class, 'pedido_bma_id')
            ->where('estatus', PedidoBmaAnexoEnvio::ESTATUS_PENDIENTE)
            ->latestOfMany();
    }

    public function miembroOperacionEmpaque(): HasOne
    {
        return $this->hasOne(OperacionEmpaqueMiembro::class, 'pedido_bma_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pedido_principal_id');
    }

    public function complementos(): HasMany
    {
        return $this->hasMany(self::class, 'pedido_principal_id')->orderBy('id');
    }

    public function envioTienda(): BelongsTo
    {
        return $this->belongsTo(CatalogoEnvioTienda::class, 'catalogo_envio_tienda_id');
    }

    public function esComplemento(): bool
    {
        return $this->pedido_principal_id !== null;
    }

    public function esPrincipalConComplementos(): bool
    {
        if ($this->esComplemento()) {
            return false;
        }

        if ($this->relationLoaded('complementos')) {
            return $this->complementos->isNotEmpty();
        }

        return $this->complementos()->exists();
    }

    public function raizEmpaque(): self
    {
        if ($this->esComplemento()) {
            $this->loadMissing('principal');

            return $this->principal ?? $this;
        }

        return $this;
    }

    public function folioVisibleCabecera(): string
    {
        if ($this->esComplemento()) {
            $this->loadMissing('principal');

            return (string) ($this->principal?->folio ?? $this->folio);
        }

        return (string) $this->folio;
    }

    public function esMunicipioDiferido(): bool
    {
        $this->loadMissing('tipoOperacionEnvio');

        return (bool) $this->tipoOperacionEnvio?->esMunicipioDiferido();
    }

    public function esResguardoAbierto(): bool
    {
        $this->loadMissing('tipoOperacionEnvio');

        return (bool) $this->tipoOperacionEnvio?->esResguardoAbierto();
    }

    public function esResguardoComplementario(): bool
    {
        $this->loadMissing('tipoOperacionEnvio');

        return (bool) $this->tipoOperacionEnvio?->esResguardoComplementario();
    }

    /** @deprecated Fase 5: usar pedido_principal_id / complementos */
    public function operacionEmpaqueActual(): ?OperacionEmpaque
    {
        $this->loadMissing('miembroOperacionEmpaque.operacion');

        return $this->miembroOperacionEmpaque?->operacion;
    }

    /** @deprecated Fase 5 */
    public function estaConsolidado(): bool
    {
        return $this->esPrincipalConComplementos()
            || $this->esComplemento()
            || $this->estatus_envio === self::ESTATUS_ENVIO_CONSOLIDADO;
    }

    public function puedeLiberarConCaptura(): bool
    {
        return $this->esResguardoAbierto()
            && (bool) $this->es_resguardo
            && $this->estatus_envio === self::ESTATUS_ENVIO_PENDIENTE_LIBERACION;
    }

    public function puedeAnexarPagoEnvio(): bool
    {
        return in_array($this->estatus_envio, [
            self::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION,
            self::ESTATUS_ENVIO_ANEXO_RECHAZADO,
        ], true);
    }

    public function tieneAnexoEnvioPorRevisar(): bool
    {
        return $this->estatus_envio === self::ESTATUS_ENVIO_PENDIENTE_REVISION_ANEXO;
    }

    public function estatus(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstatusPedido::class, 'catalogo_estatus_pedido_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(CatalogoBanco::class, 'catalogo_banco_id');
    }

    public function tipoCaja(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoCajaPedido::class, 'catalogo_tipo_caja_id');
    }

    public function paqueteria(): BelongsTo
    {
        return $this->belongsTo(CatalogoPaqueteriaPedido::class, 'catalogo_paqueteria_id');
    }

    public function tipoGuia(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoGuiaPedido::class, 'catalogo_tipo_guia_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(CatalogoZonaPedido::class, 'catalogo_zona_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_id')->orderBy('orden');
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(PedidoBmaCaja::class, 'pedido_bma_id')->orderBy('orden');
    }

    public function revisionesProducto(): HasMany
    {
        return $this->hasMany(PedidoBmaRevisionProducto::class, 'pedido_bma_id')->orderBy('orden');
    }

    public function pesajeRespondidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pesaje_respondido_por_id');
    }

    public function tienePesajeRespondido(): bool
    {
        return $this->pesaje_respondido_at !== null;
    }

    public function pdfPedido(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_id')
            ->where('tipo', PedidoBmaDocumento::TIPO_PDF_PEDIDO)
            ->orderBy('orden');
    }

    public function tienePdfPedido(): bool
    {
        return $this->pdfPedido()->exists();
    }

    public function anexoPiezas(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_id')
            ->where('tipo', PedidoBmaDocumento::TIPO_ANEXO_PIEZAS)
            ->orderBy('orden');
    }

    public function tieneAnexoPiezas(): bool
    {
        return $this->anexoPiezas()->exists();
    }

    public function puedeSolicitarPesaje(): bool
    {
        $fase = $this->estatus?->fase_ciclo;
        if (! in_array($fase, [
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
        ], true)) {
            return false;
        }

        $this->loadMissing('origen');
        if (! ($this->origen?->requiere_logistica ?? true)) {
            return false;
        }

        if ($this->estatus_envio === self::ESTATUS_ENVIO_PENDIENTE_PESAJE) {
            return false;
        }

        return ! $this->tienePesajeRespondido();
    }

    public function puedeResponderPesaje(): bool
    {
        return $this->estatus_envio === self::ESTATUS_ENVIO_PENDIENTE_PESAJE
            && $this->empacado_at === null;
    }

    public function puedeSolicitarRepesaje(): bool
    {
        if ($this->empacado_at !== null) {
            return false;
        }

        if (! $this->tienePesajeRespondido()) {
            return false;
        }

        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        ], true);
    }

    public function puedeEliminarPreVenta(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO,
        ], true);
    }

    /**
     * Cancelación directa pre-hitos (política 1A).
     * Bloquea si ya hay guía/rastreo o fases avanzadas; permite resguardo sin guía.
     */
    public function puedeCancelarDirecto(): bool
    {
        $this->loadMissing('estatus');
        $fase = $this->estatus?->fase_ciclo;

        if ($fase === CatalogoEstatusPedido::FASE_CANCELADO || $this->cancelado_at) {
            return false;
        }

        if ($this->numero_rastreo || in_array($fase, [
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_ENTREGADO,
            CatalogoEstatusPedido::FASE_EN_RUTA,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        ], true)) {
            return false;
        }

        if (in_array($fase, [
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
        ], true)) {
            return true;
        }

        // Pedido pagado/en empaque detenido por sin existencias: Ventas puede cancelar.
        if (in_array($fase, [
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        ], true) && $this->tieneSinExistenciaAbierta()) {
            return true;
        }

        // Resguardo apartado en flujo temprano, sin guía.
        if ($this->es_resguardo && in_array($fase, [
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        ], true)) {
            return true;
        }

        return false;
    }

    public function puedeVolverABorrador(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO,
        ], true);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(PedidoBmaHistorialEstado::class, 'pedido_bma_id')->orderByDesc('created_at');
    }

    public function errores(): HasMany
    {
        return $this->hasMany(PedidoBmaError::class, 'pedido_bma_id')->orderByDesc('reportado_at');
    }

    public function pagoValidadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pago_validado_por_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_id');
    }

    public function empacadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empacado_por_id');
    }

    public function incidenciaEmpaquePor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incidencia_empaque_por_id');
    }

    public function guiaCorregidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guia_corregida_por_id');
    }

    public function errorDatosPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'error_datos_por_id');
    }

    public function resguardoApartadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resguardo_apartado_por_id');
    }

    public function puedeMarcarResguardoApartado(): bool
    {
        return (bool) $this->es_resguardo
            && $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_EN_CEDIS
            && $this->resguardo_apartado_at === null;
    }

    public function puedeReportarErrorDatos(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        ], true);
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_id')
            ->where('tipo', PedidoBmaDocumento::TIPO_COMPROBANTE)
            ->orderBy('orden');
    }

    public function remision(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_id')
            ->where('tipo', PedidoBmaDocumento::TIPO_REMISION)
            ->orderBy('orden');
    }

    public function guiaPdf(): HasMany
    {
        return $this->hasMany(PedidoBmaDocumento::class, 'pedido_bma_id')
            ->where('tipo', PedidoBmaDocumento::TIPO_GUIA)
            ->orderBy('orden');
    }

    public function tieneGuiaPdf(): bool
    {
        return $this->guiaPdf()->exists();
    }

    public function esEmpacado(): bool
    {
        return $this->empacado_at !== null && in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_ENTREGADO,
            CatalogoEstatusPedido::FASE_ENVIADO,
        ], true);
    }

    public function puedeGestionarGuiaPdf(): bool
    {
        if ($this->es_resguardo || $this->guiaSoloLecturaHastaEmpaque()) {
            return false;
        }

        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_ENVIADO,
        ], true);
    }

    public function puedeCargarGuiaCliente(): bool
    {
        return (bool) $this->cliente_proporciona_guia
            && empty($this->numero_rastreo)
            && ! $this->es_resguardo
            && $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE;
    }

    public function guiaBloqueadaPorResguardo(): bool
    {
        return (bool) $this->es_resguardo;
    }

    public function guiaSoloLecturaHastaEmpaque(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_EN_CEDIS
            && !empty($this->numero_rastreo)
            && $this->empacado_at === null;
    }

    public function puedeAsignarGuia(): bool
    {
        if ($this->es_resguardo || !empty($this->numero_rastreo) || $this->cliente_proporciona_guia) {
            return false;
        }

        $this->loadMissing(['paqueteria', 'origen']);

        if (!$this->ofreceRastreo()) {
            return false;
        }

        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        ], true);
    }

    public function tienePagoValidado(): bool
    {
        return $this->pago_validado_at !== null;
    }

    public function tieneRemision(): bool
    {
        return $this->remision()->exists();
    }

    public function esAuditablePorAuxiliar(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR;
    }

    public function esEditablePorVendedora(): bool
    {
        $fase = $this->estatus?->fase_ciclo;

        return in_array($fase, [
            CatalogoEstatusPedido::FASE_BORRADOR,
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE,
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO,
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
        ], true);
    }

    public function esBorrador(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_BORRADOR;
    }

    public function esPesajePendiente(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE;
    }

    public function esPesajeRespondido(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO;
    }

    public function hitoAuditoria(): ?string
    {
        return MaquinaEstadosPedidoBma::hitoAuditoria($this);
    }

    public function puedeReabrirEnvio(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_ENVIADO
            && $this->empacado_at !== null;
    }

    public function tieneErroresGravesBloqueanEmpaque(): bool
    {
        return MaquinaEstadosPedidoBma::erroresGravesBloqueanEmpaque($this);
    }

    public function tieneSinExistenciaAbierta(): bool
    {
        $revisiones = $this->relationLoaded('revisionesProducto')
            ? $this->getRelation('revisionesProducto')
            : $this->revisionesProducto()->get();

        return collect($revisiones)->contains(
            fn (PedidoBmaRevisionProducto $r) => $r->estaSinExistenciaAbierta()
        );
    }

    public function assertSinExistenciaAtendida(): void
    {
        if ($this->tieneSinExistenciaAbierta()) {
            throw new \RuntimeException(
                'Hay productos sin existencias sin atender. Ventas debe elegir una acción (retirar, sustituir, esperar o cancelar) antes de continuar.'
            );
        }
    }

    public function esGestionablePorCedis(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        ], true);
    }

    public function ofreceRastreo(): bool
    {
        if ($this->cliente_proporciona_guia) {
            return true;
        }

        if ($this->paqueteria) {
            return $this->paqueteria->ofreceRastreo();
        }

        return (bool) ($this->origen?->requiere_logistica ?? false);
    }

    public function tieneGuiaLista(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_ENVIADO,
        ], true) && !empty($this->numero_rastreo);
    }

    public function puedeMarcarEnviado(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO
            && $this->empacado_at !== null;
    }

    public function puedeMarcarEmpacado(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        ], true);
    }

    public function puedeRevertirEmpacado(): bool
    {
        return in_array($this->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_ENTREGADO,
        ], true) && empty($this->numero_rastreo);
    }

    public function puedeReportarIncidencia(): bool
    {
        return $this->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_EN_CEDIS;
    }

    public static function calcularTotal(float $mercancia, float $envio, bool $seguro, float $costoSeguro, float $saldoFavor): float
    {
        $total = $mercancia + $envio + ($seguro ? $costoSeguro : 0) - $saldoFavor;

        return max(0, round($total, 2));
    }

    /**
     * Fórmula Drive: se cobra el mayor entre peso real y peso volumétrico de la caja.
     */
    public static function calcularPesoCobradoGuia(?float $pesoReal, ?float $pesoVolumetrico): ?float
    {
        if ($pesoReal === null && $pesoVolumetrico === null) {
            return null;
        }

        return round(max((float) ($pesoReal ?? 0), (float) ($pesoVolumetrico ?? 0)), 4);
    }
}
