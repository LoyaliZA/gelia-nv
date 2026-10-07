<?php

namespace App\Models\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalonamientoCambioLista extends Model
{
    protected $table = 'escalonamiento_cambios_lista';

    protected $fillable = [
        'escalonamiento_cierre_id',
        'cliente_id',
        'lista_anterior_id',
        'lista_nueva_id',
        'monto_anterior',
        'motivo',
        'aplicado_en',
        'idempotency_key',
    ];

    protected $casts = [
        'monto_anterior' => 'decimal:2',
        'aplicado_en' => 'datetime',
    ];

    public function cierre(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoCierre::class, 'escalonamiento_cierre_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function listaAnterior(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'lista_anterior_id');
    }

    public function listaNueva(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'lista_nueva_id');
    }
}
