<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoModalidadPreparacionPedido extends Model
{
    public const CODIGO_RECOGE_TIENDA = 'RECOGE_TIENDA';

    public const CODIGO_RECOGE_TIENDA_TRANSFERENCIA = 'RECOGE_TIENDA_TRANSFERENCIA';

    public const CODIGO_ENVIO_BODEGA_NORMAL = 'ENVIO_BODEGA_NORMAL';

    public const CODIGO_ENVIO_BODEGA_COMPLEMENTO = 'ENVIO_BODEGA_COMPLEMENTO';

    public const CODIGO_ENVIO_MUNICIPIO = 'ENVIO_MUNICIPIO';

    public const CODIGOS_FASE4 = [
        self::CODIGO_RECOGE_TIENDA,
        self::CODIGO_RECOGE_TIENDA_TRANSFERENCIA,
    ];

    public const CODIGOS_FASE5 = [
        self::CODIGO_ENVIO_BODEGA_NORMAL,
        self::CODIGO_ENVIO_BODEGA_COMPLEMENTO,
    ];

    public const CODIGOS_FASE6 = [
        self::CODIGO_ENVIO_MUNICIPIO,
    ];

    /** Códigos permitidos al solicitar preparación (Fase 4 + 5 + 6). */
    public const CODIGOS_SOLICITABLES = [
        ...self::CODIGOS_FASE4,
        ...self::CODIGOS_FASE5,
        ...self::CODIGOS_FASE6,
    ];

    protected $table = 'catalogo_modalidades_preparacion_pedido';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'area_responsable_codigo',
        'requisitos_json',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'requisitos_json' => 'array',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(PedidoBmaTareaPreparacion::class, 'catalogo_modalidad_preparacion_id');
    }

    public function esDestinoSucursal(): bool
    {
        return in_array($this->codigo, self::CODIGOS_FASE4, true);
    }

    public function esTransferencia(): bool
    {
        return $this->codigo === self::CODIGO_RECOGE_TIENDA_TRANSFERENCIA;
    }

    public function esEnvioBodega(): bool
    {
        return in_array($this->codigo, self::CODIGOS_FASE5, true);
    }

    public function esEnvioMunicipio(): bool
    {
        return $this->codigo === self::CODIGO_ENVIO_MUNICIPIO;
    }

    public function requiereTrasladoCedisPorDefecto(): bool
    {
        return $this->esEnvioBodega()
            || (bool) (($this->requisitos_json['traslado_cedis'] ?? false));
    }
}
