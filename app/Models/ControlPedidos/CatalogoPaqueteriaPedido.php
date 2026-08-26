<?php

namespace App\Models\ControlPedidos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoPaqueteriaPedido extends Model
{
    protected $table = 'catalogo_paqueterias_pedido';

    public const CATEGORIA_COMERCIAL = 'comercial';
    public const CATEGORIA_LOCAL_REGIONAL = 'local_regional';
    public const NOMBRE_PENDIENTE = 'PAQ. PENDIENTE';
    public const MODALIDAD_FIJA = 'fija';
    public const MODALIDAD_POR_PESO = 'por_peso';
    public const UNIDAD_KG = 'kg';
    public const UNIDAD_G = 'g';

    protected $fillable = [
        'nombre', 'categoria', 'permite_costo_diferido', 'activo', 'costo_seguro_default',
        'modalidad_tarifa', 'tarifa_monto', 'tarifa_unidad_peso', 'tarifa_paso_peso',
        'requiere_caratula', 'requiere_identificacion', 'requiere_remision', 'permite_por_cobrar',
        'requiere_peso', 'requiere_caja', 'requiere_evidencia_conjunto', 'campos_destino_obligatorios',
        'plantilla_caratula', 'habilitado_envio_municipio', 'reglas_municipio_pendientes',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'permite_costo_diferido' => 'boolean',
        'requiere_caratula' => 'boolean',
        'requiere_identificacion' => 'boolean',
        'requiere_remision' => 'boolean',
        'permite_por_cobrar' => 'boolean',
        'requiere_peso' => 'boolean',
        'requiere_caja' => 'boolean',
        'requiere_evidencia_conjunto' => 'boolean',
        'habilitado_envio_municipio' => 'boolean',
        'reglas_municipio_pendientes' => 'boolean',
        'campos_destino_obligatorios' => 'array',
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

    public function esPendienteConfirmacion(): bool
    {
        return mb_strtoupper(trim((string) $this->nombre)) === self::NOMBRE_PENDIENTE;
    }

    public function habilitadaParaEnvioMunicipio(): bool
    {
        return (bool) $this->activo
            && (bool) $this->habilitado_envio_municipio
            && ! (bool) $this->reglas_municipio_pendientes;
    }

    /**
     * @return array<string, mixed>
     */
    public function reglasMunicipio(): array
    {
        return [
            'requiere_caratula' => (bool) $this->requiere_caratula,
            'requiere_identificacion' => (bool) $this->requiere_identificacion,
            'requiere_remision' => (bool) $this->requiere_remision,
            'permite_por_cobrar' => (bool) $this->permite_por_cobrar,
            'requiere_peso' => (bool) $this->requiere_peso,
            'requiere_caja' => (bool) $this->requiere_caja,
            'requiere_evidencia_conjunto' => (bool) $this->requiere_evidencia_conjunto,
            'campos_destino_obligatorios' => array_values($this->campos_destino_obligatorios ?? ['municipio', 'destinatario', 'telefono']),
            'plantilla_caratula' => $this->plantilla_caratula ?: 'control_pedidos.caratula',
        ];
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
