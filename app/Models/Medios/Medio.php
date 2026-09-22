<?php

namespace App\Models\Medios;

use App\Models\User;
use Database\Factories\Medios\MedioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medio extends Model
{
    use HasFactory;

    public const TIPO_IMAGEN = 'imagen';

    public const TIPO_VIDEO = 'video';

    public const ESTADO_UPLOADING = 'uploading';

    public const ESTADO_PROCESSING = 'processing';

    public const ESTADO_READY = 'ready';

    public const ESTADO_FAILED = 'failed';

    public const ESTADO_DELETED = 'deleted';

    public const PROPOSITO_PDV_PUBLICIDAD = 'pdv_publicidad';

    protected $table = 'medios';

    protected $fillable = [
        'uuid',
        'nombre_original',
        'object_key',
        'mime_type',
        'extension',
        'tamano_bytes',
        'duracion_seg',
        'tipo',
        'estado',
        'proposito',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'duracion_seg' => 'integer',
        ];
    }

    protected static function newFactory(): MedioFactory
    {
        return MedioFactory::new();
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function cargas(): HasMany
    {
        return $this->hasMany(MedioCarga::class, 'medio_id');
    }

    public function esImagen(): bool
    {
        return $this->tipo === self::TIPO_IMAGEN;
    }

    public function esVideo(): bool
    {
        return $this->tipo === self::TIPO_VIDEO;
    }
}
