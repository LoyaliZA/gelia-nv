<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipoAsistenciaDiaPdv extends Model
{
    protected $table = 'pdv_equipo_asistencia_dia';

    protected $fillable = [
        'sucursal_id',
        'user_id',
        'fecha_operativa',
        'no_llego_at',
        'no_llego_por_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'fecha_operativa' => 'date',
            'no_llego_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function marcadoNoLlegoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_llego_por_id');
    }

    public function estaMarcadoNoLlego(): bool
    {
        return $this->no_llego_at !== null;
    }
}
