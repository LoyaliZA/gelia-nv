<?php

namespace App\Models\ComisionesEmpaque;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditoriaComisionEmpaque extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'comisiones_empaque_auditorias';

    protected $fillable = ['entidad', 'entidad_id', 'accion', 'motivo', 'responsable_id', 'datos', 'created_at'];

    protected $casts = ['datos' => 'array', 'created_at' => 'datetime'];

    public static function anotar(User $responsable, string $entidad, int $entidadId, string $accion, ?string $motivo, array $datos = []): void
    {
        static::query()->create([
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'accion' => $accion,
            'motivo' => $motivo,
            'responsable_id' => $responsable->id,
            'datos' => $datos,
            'created_at' => now(),
        ]);
    }
}
