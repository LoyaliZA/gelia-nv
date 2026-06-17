<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoporteTicketInteraccion extends Model
{
    use HasFactory;

    protected $table = 'soporte_ticket_interacciones';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'mensaje',
        'es_nota_interna',
    ];

    protected $casts = [
        'es_nota_interna' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SoporteTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(SoporteTicketAdjunto::class, 'interaccion_id');
    }
}
