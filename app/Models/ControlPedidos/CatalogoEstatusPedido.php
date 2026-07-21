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
    public const FASE_PENDIENTE_AUXILIAR = 'PENDIENTE_AUXILIAR';
    public const FASE_EN_CEDIS = 'EN_CEDIS';
    public const FASE_RECHAZADO_VENDEDORA = 'RECHAZADO_VENDEDORA';
    public const FASE_INCIDENCIA_CEDIS = 'INCIDENCIA_CEDIS';
    public const FASE_EN_RUTA = 'EN_RUTA';
    public const FASE_PENDIENTE_DE_GUIA = 'PENDIENTE_DE_GUIA';
    public const FASE_PENDIENTE_DE_ENVIO = 'PENDIENTE_DE_ENVIO';
    public const FASE_ENTREGADO = 'ENTREGADO';
    public const FASE_ENVIADO = 'ENVIADO';

    /** Etiquetas de negocio por fase (evita nombres de color literales). */
    public const LABELS_POR_FASE = [
        self::FASE_BORRADOR => 'Borrador',
        self::FASE_PENDIENTE_AUXILIAR => 'Pendiente Auxiliar',
        self::FASE_EN_CEDIS => 'En CEDIS',
        self::FASE_RECHAZADO_VENDEDORA => 'Rechazado',
        self::FASE_INCIDENCIA_CEDIS => 'Incidencia CEDIS',
        self::FASE_EN_RUTA => 'En ruta',
        self::FASE_PENDIENTE_DE_GUIA => 'Pendiente de guía',
        self::FASE_PENDIENTE_DE_ENVIO => 'Pendiente de envío',
        self::FASE_ENTREGADO => 'Entregado',
        self::FASE_ENVIADO => 'Enviado',
    ];

    public function etiquetaSemantica(?bool $esResguardo = false): string
    {
        // Flag de intención en borrador/rechazado: no sustituye la etiqueta de fase.
        if ($esResguardo
            && $this->fase_ciclo !== self::FASE_BORRADOR
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
