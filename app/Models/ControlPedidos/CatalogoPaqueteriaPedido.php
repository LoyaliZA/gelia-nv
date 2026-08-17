<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoPaqueteriaPedido extends Model
{
    protected $table = 'catalogo_paqueterias_pedido';

    public const CATEGORIA_COMERCIAL = 'comercial';
    public const CATEGORIA_LOCAL_REGIONAL = 'local_regional';
    public const MODALIDAD_FIJA = 'fija';
    public const MODALIDAD_POR_PESO = 'por_peso';
    public const UNIDAD_KG = 'kg';
    public const UNIDAD_G = 'g';

    protected $fillable = [
        'nombre', 'categoria', 'permite_costo_diferido', 'activo', 'costo_seguro_default',
        'modalidad_tarifa', 'tarifa_monto', 'tarifa_unidad_peso', 'tarifa_paso_peso',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'permite_costo_diferido' => 'boolean',
        'costo_seguro_default' => 'decimal:2',
        'tarifa_monto' => 'decimal:2',
        'tarifa_paso_peso' => 'decimal:4',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoBma::class, 'catalogo_paqueteria_id');
    }

    public function ofreceRastreo(): bool
    {
        return $this->categoria === self::CATEGORIA_COMERCIAL;
    }

    public function permiteCostoDiferido(): bool
    {
        return (bool) $this->permite_costo_diferido;
    }

    public function esLocalRegional(): bool
    {
        return $this->categoria === self::CATEGORIA_LOCAL_REGIONAL;
    }

    public function calcularCostoEnvio(?float $pesoCobradoKg = null): ?float
    {
        if ($this->categoria === self::CATEGORIA_COMERCIAL || ! $this->modalidad_tarifa) {
            return null;
        }

        $monto = $this->tarifa_monto !== null && $this->tarifa_monto !== ''
            ? (float) $this->tarifa_monto
            : null;
        if ($monto === null) {
            return null;
        }

        if ($this->modalidad_tarifa === self::MODALIDAD_FIJA) {
            return round($monto, 2);
        }

        if ($this->modalidad_tarifa !== self::MODALIDAD_POR_PESO) {
            return null;
        }
        if ($pesoCobradoKg === null || $pesoCobradoKg <= 0) {
            return null;
        }

        $paso = (float) ($this->tarifa_paso_peso ?: 0);
        if (! ($paso > 0)) {
            return null;
        }

        $pesoEnUnidad = $this->tarifa_unidad_peso === self::UNIDAD_G
            ? $pesoCobradoKg * 1000
            : $pesoCobradoKg;
        $pasos = (int) ceil($pesoEnUnidad / $paso);
        if ($pasos < 1) {
            $pasos = 1;
        }

        return round($pasos * $monto, 2);
    }
}
