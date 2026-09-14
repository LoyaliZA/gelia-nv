<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubePrecioImport extends Model
{
    public const ESTADO_REVISION = 'revision';

    public const ESTADO_CONFIRMADO = 'confirmado';

    public const ESTADO_CONFIRMADO_PARCIAL = 'confirmado_parcial';

    public const ESTADO_ERROR = 'error';

    protected $table = 'tiendanube_precio_imports';

    protected $fillable = [
        'user_id',
        'store_id',
        'estado',
        'delimiter',
        'decimal_sep',
        'mapeo_json',
        'archivo_path',
        'total_filas',
        'validas',
        'errores',
        'mensaje_error',
        'confirmado_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'store_id' => 'integer',
            'mapeo_json' => 'array',
            'total_filas' => 'integer',
            'validas' => 'integer',
            'errores' => 'integer',
            'confirmado_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TiendanubePrecioImportItem::class, 'import_id');
    }
}
