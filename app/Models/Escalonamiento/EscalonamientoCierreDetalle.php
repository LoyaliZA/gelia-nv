<?php

namespace App\Models\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalonamientoCierreDetalle extends Model
{
    protected $table = 'escalonamiento_cierre_detalles';

    protected $fillable = [
        'escalonamiento_cierre_id',
        'cliente_id',
        'compras',
        'devoluciones',
        'neto',
        'lista_base_id',
        'lista_vigente_cierre_id',
        'clasificacion_mes_id',
        'lista_siguiente_id',
        'lista_operativa_id',
        'motivo',
        'aplica_cambio_lista',
        'propone_inactivo',
        'meses_sin_compra',
        'extras',
    ];

    protected $casts = [
        'compras' => 'decimal:2',
        'devoluciones' => 'decimal:2',
        'neto' => 'decimal:2',
        'aplica_cambio_lista' => 'boolean',
        'propone_inactivo' => 'boolean',
        'meses_sin_compra' => 'integer',
        'extras' => 'array',
    ];

    public function cierre(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoCierre::class, 'escalonamiento_cierre_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function listaSiguiente(): BelongsTo
    {
        return $this->belongsTo(CatalogoListaDescuento::class, 'lista_siguiente_id');
    }
}
