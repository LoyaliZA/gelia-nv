<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoporteTicket extends Model
{
    use HasFactory;

    protected $table = 'soporte_tickets';

    protected $fillable = [
        'user_id',
        'asignado_a_id',
        'modulo_id',
        'categoria_id',
        'prioridad_sugerida_id',
        'prioridad_asignada_id',
        'estado_id',
        'titulo',
        'descripcion',
        'fecha_vencimiento_sla',
        'fecha_resolucion',
    ];

    protected $casts = [
        'fecha_vencimiento_sla' => 'datetime',
        'fecha_resolucion' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a_id');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(SoporteCatalogoModulo::class, 'modulo_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(SoporteCatalogoCategoria::class, 'categoria_id');
    }

    public function prioridadSugerida(): BelongsTo
    {
        return $this->belongsTo(SoporteCatalogoPrioridad::class, 'prioridad_sugerida_id');
    }

    public function prioridadAsignada(): BelongsTo
    {
        return $this->belongsTo(SoporteCatalogoPrioridad::class, 'prioridad_asignada_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(SoporteCatalogoEstado::class, 'estado_id');
    }

    public function interacciones(): HasMany
    {
        return $this->hasMany(SoporteTicketInteraccion::class, 'ticket_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(SoporteTicketAdjunto::class, 'ticket_id');
    }
}
