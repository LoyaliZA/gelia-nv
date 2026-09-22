<?php

namespace App\Models\Medios;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedioCarga extends Model
{
    public const TIPO_SINGLE = 'single';

    public const TIPO_MULTIPART = 'multipart';

    public const ESTADO_PENDING = 'pending';

    public const ESTADO_UPLOADING = 'uploading';

    public const ESTADO_COMPLETING = 'completing';

    public const ESTADO_COMPLETED = 'completed';

    public const ESTADO_FAILED = 'failed';

    public const ESTADO_CANCELLED = 'cancelled';

    public const ESTADO_EXPIRED = 'expired';

    protected $table = 'medio_cargas';

    protected $fillable = [
        'uuid',
        'medio_id',
        'r2_upload_id',
        'object_key',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'upload_type',
        'chunk_size',
        'estado',
        'proposito',
        'subido_por',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'chunk_size' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function medio(): BelongsTo
    {
        return $this->belongsTo(Medio::class, 'medio_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function vigente(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDING, self::ESTADO_UPLOADING], true)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
