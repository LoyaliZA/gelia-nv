<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoHorarioTraspaso extends Model
{
    protected $table = 'catalogo_horarios_traspaso';

    protected $fillable = [
        'nombre',
        'hora_inicio',
        'hora_fin',
        'dias_para_entrega',
        'descripcion',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'dias_para_entrega' => 'integer',
            'orden' => 'integer',
        ];
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudTraspaso::class, 'catalogo_horario_traspaso_id');
    }

    /**
     * Resuelve la ventana activa que contiene la hora dada (HH:MM:SS).
     * Incluye inicio, excluye fin salvo la última ventana del día.
     */
    public static function resolverParaHora(string $hora): ?self
    {
        $ventanas = static::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('hora_inicio')
            ->get();

        foreach ($ventanas as $ventana) {
            $inicio = substr((string) $ventana->hora_inicio, 0, 8);
            $fin = substr((string) $ventana->hora_fin, 0, 8);

            if ($hora >= $inicio && $hora < $fin) {
                return $ventana;
            }

            // Última ventana del día puede cerrar en 23:59:59
            if ($hora >= $inicio && $hora <= $fin && $fin >= '23:59:00') {
                return $ventana;
            }
        }

        return $ventanas->last();
    }
}
