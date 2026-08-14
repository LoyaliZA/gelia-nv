<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoEstatusPedido extends Model
{
    protected $table = 'catalogo_estatus_pedidos';

    protected $fillable = [
        'codigo_interno',
        'nombre_visual',
        'color_hex',
        'fase_ciclo',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public const FASE_BORRADOR = 'BORRADOR';
    public const FASE_PESAJE_PENDIENTE = 'PESAJE_PENDIENTE';
    public const FASE_PESAJE_RESPONDIDO = 'PESAJE_RESPONDIDO';
    public const FASE_PENDIENTE_AUXILIAR = 'PENDIENTE_AUXILIAR';
    public const FASE_EN_CEDIS = 'EN_CEDIS';
    public const FASE_RECHAZADO_VENDEDORA = 'RECHAZADO_VENDEDORA';
    public const FASE_INCIDENCIA_CEDIS = 'INCIDENCIA_CEDIS';
    public const FASE_EN_RUTA = 'EN_RUTA';
    public const FASE_PENDIENTE_DE_GUIA = 'PENDIENTE_DE_GUIA';
    public const FASE_PENDIENTE_GUIA_CLIENTE = 'PENDIENTE_GUIA_CLIENTE';
    public const FASE_PENDIENTE_DE_ENVIO = 'PENDIENTE_DE_ENVIO';
    public const FASE_ENTREGADO = 'ENTREGADO';
    public const FASE_ENVIADO = 'ENVIADO';
    public const FASE_CANCELADO = 'CANCELADO';

    /** Etiquetas de negocio por fase (evita nombres de color literales). */
    public const LABELS_POR_FASE = [
        self::FASE_BORRADOR => 'Borrador',
        self::FASE_PESAJE_PENDIENTE => 'Pesaje pendiente',
        self::FASE_PESAJE_RESPONDIDO => 'Pesaje respondido',
        self::FASE_PENDIENTE_AUXILIAR => 'Pendiente de auditoría',
        self::FASE_EN_CEDIS => 'Pendiente de empaque',
        self::FASE_RECHAZADO_VENDEDORA => 'Rechazado o devuelto para corrección',
        self::FASE_INCIDENCIA_CEDIS => 'Error CEDIS',
        self::FASE_EN_RUTA => 'En ruta',
        self::FASE_PENDIENTE_DE_GUIA => 'Pendiente de guía',
        self::FASE_PENDIENTE_GUIA_CLIENTE => 'Pendiente de guía del cliente',
        self::FASE_PENDIENTE_DE_ENVIO => 'Pendiente de recolección o envío',
        self::FASE_ENTREGADO => 'Entregado',
        self::FASE_ENVIADO => 'Enviado',
        self::FASE_CANCELADO => 'Cancelado',
    ];

    public function etiquetaSemantica(?bool $esResguardo = false): string
    {
        // Flag de intención en pre-venta: no sustituye la etiqueta de fase.
        if ($esResguardo
            && $this->fase_ciclo !== self::FASE_BORRADOR
            && $this->fase_ciclo !== self::FASE_PESAJE_PENDIENTE
            && $this->fase_ciclo !== self::FASE_PESAJE_RESPONDIDO
            && $this->fase_ciclo !== self::FASE_RECHAZADO_VENDEDORA
        ) {
            return 'Resguardo';
        }

        return self::LABELS_POR_FASE[$this->fase_ciclo]
            ?? $this->nombre_visual
            ?? $this->fase_ciclo
            ?? '—';
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_estatus_pedido_id');
    }

    public static function porCodigo(string $codigo): ?self
    {
        return static::where('codigo_interno', $codigo)->first();
    }

    public static function porFase(string $fase): ?self
    {
        return static::where('fase_ciclo', $fase)->where('activo', true)->orderBy('orden')->first();
    }
}
