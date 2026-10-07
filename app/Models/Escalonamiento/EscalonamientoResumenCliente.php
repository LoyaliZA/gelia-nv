<?php

namespace App\Models\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalonamientoResumenCliente extends Model
{
    protected $table = 'escalonamiento_resumenes_cliente';

    protected $fillable = [
        'escalonamiento_periodo_id',
        'cliente_id',
        'acumulado',
        'lista_base_id',
        'clasificacion_mes_id',
        'clasificacion_mes_max_id',
        'lista_vigente_id',
    ];

    protected $casts = [
        'acumulado' => 'decimal:2',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoPeriodo::class, 'escalonamiento_periodo_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function listaBase(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'lista_base_id');
    }

    public function clasificacionMes(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'clasificacion_mes_id');
    }

    public function clasificacionMesMax(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'clasificacion_mes_max_id');
    }

    public function listaVigente(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'lista_vigente_id');
    }
}
