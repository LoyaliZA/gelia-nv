<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlataformaPago extends Model
{
    protected $table = 'contabilidad_plataformas_pago';

    protected $fillable = [
        'nombre',
        'frecuencia_pago_id',
        'ultimo_corte',
        'dias_personalizados',
        'tasa_comision_pct',
        'cuota_fija',
        'tasa_iva_pct',
        'activo',
    ];

    protected $casts = [
        'ultimo_corte' => 'date',
        'dias_personalizados' => 'integer',
        'tasa_comision_pct' => 'decimal:2',
        'cuota_fija' => 'decimal:2',
        'tasa_iva_pct' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function frecuenciaPago(): BelongsTo
    {
        return $this->belongsTo(CatalogoFrecuenciaPago::class, 'frecuencia_pago_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'plataforma_pago_id');
    }

    public function lotesPago(): HasMany
    {
        return $this->hasMany(LotePago::class, 'plataforma_pago_id');
    }
}
