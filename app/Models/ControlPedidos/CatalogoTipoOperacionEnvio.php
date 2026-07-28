<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoTipoOperacionEnvio extends Model
{
    protected $table = 'catalogo_tipos_operacion_envio';

    public const CODIGO_NORMAL = 'NORMAL';
    public const CODIGO_MUNICIPIO_DIFERIDO = 'MUNICIPIO_DIFERIDO';
    public const CODIGO_RESGUARDO_ABIERTO = 'RESGUARDO_ABIERTO';
    public const CODIGO_RESGUARDO_COMPLEMENTARIO = 'RESGUARDO_COMPLEMENTARIO';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'tipo_operacion_envio_id');
    }

    public function esMunicipioDiferido(): bool
    {
        return $this->codigo === self::CODIGO_MUNICIPIO_DIFERIDO;
    }

    public function esResguardoAbierto(): bool
    {
        return $this->codigo === self::CODIGO_RESGUARDO_ABIERTO;
    }

    public function esResguardoComplementario(): bool
    {
        return $this->codigo === self::CODIGO_RESGUARDO_COMPLEMENTARIO;
    }

    public static function porCodigo(string $codigo): ?self
    {
        return static::where('codigo', $codigo)->first();
    }

    public static function idNormal(): ?int
    {
        return static::porCodigo(self::CODIGO_NORMAL)?->id;
    }
}
