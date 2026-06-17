<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoporteTicketAdjunto extends Model
{
    use HasFactory;

    protected $table = 'soporte_ticket_adjuntos';

    protected $fillable = [
        'ticket_id',
        'interaccion_id',
        'ruta_archivo',
        'nombre_archivo',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SoporteTicket::class, 'ticket_id');
    }

    public function interaccion(): BelongsTo
    {
        return $this->belongsTo(SoporteTicketInteraccion::class, 'interaccion_id');
    }
}
