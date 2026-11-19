<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CatalogoTipoCliente extends Model
{
    use HasFactory;

    protected $table = 'catalogo_tipo_clientes';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

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