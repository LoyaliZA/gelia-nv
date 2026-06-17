<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoporteCatalogoPrioridad extends Model
{
    use HasFactory;

    protected $table = 'soporte_catalogo_prioridades';

    protected $fillable = [
        'nombre',
        'tiempo_respuesta_horas',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tiempo_respuesta_horas' => 'integer',
    ];

    protected $appends = ['color'];

    public function getColorAttribute(): string
    {
        return match ($this->nombre) {
            'Crítica' => '#dc2626',
            'Alta' => '#ef4444',
            'Media' => '#f59e0b',
            'Baja' => '#3b82f6',
            default => '#6b7280',
        };
    }
}
