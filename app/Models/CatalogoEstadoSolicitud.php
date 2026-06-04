<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoEstadoSolicitud extends Model
{
    protected $table = 'catalogo_estados_solicitud';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudTag::class, 'catalogo_estado_solicitud_id');
    }

    /** @var array<string, int>|null */
    private static ?array $idsPorNombre = null;

    public static function idDe(string $nombre): ?int
    {
        if (self::$idsPorNombre === null) {
            self::$idsPorNombre = static::query()->pluck('id', 'nombre')->all();
        }

        return self::$idsPorNombre[$nombre] ?? null;
    }

    public static function reiniciarCache(): void
    {
        self::$idsPorNombre = null;
    }
}