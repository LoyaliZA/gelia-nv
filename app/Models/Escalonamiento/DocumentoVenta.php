<?php

namespace App\Models\Escalonamiento;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentoVenta extends Model
{
    protected $table = 'documentos_venta';

    protected $fillable = [
        'escalonamiento_periodo_id',
        'cliente_id',
        'tipo',
        'folio',
        'serie',
        'sucursal',
        'moneda',
        'total',
        'estado',
        'fecha_emision',
        'origen',
        'clave_documento',
        'remision_original',
        'datos_fuente',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'fecha_emision' => 'date',
        'datos_fuente' => 'array',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoPeriodo::class, 'escalonamiento_periodo_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function movimiento(): HasOne
    {
        return $this->hasOne(EscalonamientoMovimiento::class, 'documento_venta_id');
    }

    public function aplicacionesComoDevolucion(): HasMany
    {
        return $this->hasMany(EscalonamientoAplicacionDevolucion::class, 'documento_devolucion_id');
    }

    public function aplicacionActivaComoDevolucion(): HasOne
    {
        return $this->hasOne(EscalonamientoAplicacionDevolucion::class, 'documento_devolucion_id')
            ->where('estado', EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA);
    }

    public function aplicacionesComoRemision(): HasMany
    {
        return $this->hasMany(EscalonamientoAplicacionDevolucion::class, 'documento_remision_vinculada_id');
    }
}
