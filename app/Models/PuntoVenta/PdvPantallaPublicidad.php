<?php

namespace App\Models\PuntoVenta;

use App\Models\Sucursal;
use App\Models\User;
use Database\Factories\PuntoVenta\PdvPantallaPublicidadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PdvPantallaPublicidad extends Model
{
    use HasFactory;

    public const TIPO_IMAGEN = 'imagen';

    public const TIPO_VIDEO = 'video';

    public const AJUSTE_COVER = 'cover';

    public const AJUSTE_CONTAIN = 'contain';

    public const DISK = 'public';

    public const DIRECTORIO = 'pdv/pantalla-publicidad';

    protected $table = 'pdv_pantalla_publicidades';

    protected $fillable = [
        'sucursal_id',
        'tipo',
        'ruta',
        'duracion_seg',
        'ajuste',
        'orden',
        'activa',
        'vigente_desde',
        'vigente_hasta',
        'nombre_original',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'sucursal_id' => 'integer',
            'duracion_seg' => 'integer',
            'orden' => 'integer',
            'activa' => 'boolean',
            'vigente_desde' => 'datetime',
            'vigente_hasta' => 'datetime',
        ];
    }

    protected static function newFactory(): PdvPantallaPublicidadFactory
    {
        return PdvPantallaPublicidadFactory::new();
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function urlPublica(): ?string
    {
        if ($this->ruta === null || $this->ruta === '') {
            return null;
        }

        return Storage::disk(self::DISK)->url($this->ruta);
    }
}
