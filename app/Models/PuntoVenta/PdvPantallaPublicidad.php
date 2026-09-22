<?php

namespace App\Models\PuntoVenta;

use App\Contracts\Medios\AlmacenObjetosMedio;
use App\Models\Medios\Medio;
use App\Models\Sucursal;
use App\Models\User;
use Carbon\CarbonInterface;
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

    public const ESTADO_PROGRAMADA = 'programada';

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_EXPIRADA = 'expirada';

    public const ESTADO_DESHABILITADA = 'deshabilitada';

    public const DISK = 'public';

    public const DIRECTORIO = 'pdv/pantalla-publicidad';

    protected $table = 'pdv_pantalla_publicidades';

    protected $fillable = [
        'sucursal_id',
        'medio_id',
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
            'medio_id' => 'integer',
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

    public function medio(): BelongsTo
    {
        return $this->belongsTo(Medio::class, 'medio_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function estadoCalculado(?CarbonInterface $ahora = null): string
    {
        $ahora ??= now();
        if (! $this->activa) {
            return self::ESTADO_DESHABILITADA;
        }
        if ($this->vigente_desde && $ahora->lt($this->vigente_desde)) {
            return self::ESTADO_PROGRAMADA;
        }
        if ($this->vigente_hasta && $ahora->gt($this->vigente_hasta)) {
            return self::ESTADO_EXPIRADA;
        }

        return self::ESTADO_ACTIVA;
    }

    public function urlPublica(): ?string
    {
        $medio = $this->medio;
        if ($medio instanceof Medio && $medio->estado === Medio::ESTADO_READY && $medio->object_key) {
            return app(AlmacenObjetosMedio::class)->urlLectura(
                $medio->object_key,
                (int) config('medios.ttl_lectura_seg'),
            );
        }

        if ($this->ruta === null || $this->ruta === '') {
            return null;
        }

        return Storage::disk(self::DISK)->url($this->ruta);
    }
}
