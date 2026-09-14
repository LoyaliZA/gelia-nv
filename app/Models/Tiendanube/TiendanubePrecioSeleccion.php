<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubePrecioSeleccion extends Model
{
    use HasUuids;

    public const MODO_PAGINA = 'pagina';

    public const MODO_TODOS_RESULTADOS = 'todos_resultados';

    protected $table = 'tiendanube_precio_selecciones';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'user_id',
        'version',
        'generacion',
        'modo',
        'filtros',
        'total_variantes',
        'total_productos',
        'expires_at',
        'invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'user_id' => 'integer',
            'version' => 'integer',
            'generacion' => 'integer',
            'filtros' => 'array',
            'total_variantes' => 'integer',
            'total_productos' => 'integer',
            'expires_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function miembros(): HasMany
    {
        return $this->hasMany(TiendanubePrecioSeleccionMiembro::class, 'seleccion_id');
    }

    public function expirada(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
