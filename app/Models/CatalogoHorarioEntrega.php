<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoHorarioEntrega extends Model
{
    /**
     * Nombre de la tabla asociada al modelo.
     */
    protected $table = 'catalogo_horarios_entrega';

    /**
     * Atributos asignables de forma masiva.
     */
    protected $fillable = [
        'zona_id',
        'hora_inicio',
        'hora_fin',
        'activo',
    ];

    /**
     * Conversión de tipos de atributos nativos.
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'zona_id' => 'integer',
        ];
    }

    /**
     * Relación inversa uno a muchos hacia la zona de entrega correspondiente.
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(CatalogoZonaEntrega::class, 'zona_id');
    }
}