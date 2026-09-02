<?php

namespace App\Models\PuntoVenta;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResguardoPdvEvidencia extends Model
{
    public const TIPO_FOTO = 'foto';

    public const TIPO_FIRMA = 'firma';

    protected $table = 'pdv_resguardo_evidencias';

    protected $fillable = [
        'resguardo_id',
        'evento_id',
        'bulto_id',
        'incidencia_id',
        'entrega_id',
        'tipo',
        'ruta_interna',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
        'hash_sha256',
        'actor_id',
        'capturado_at',
        'inmutable',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'capturado_at' => 'datetime',
            'inmutable' => 'boolean',
            'metadata_json' => 'array',
        ];
    }

    public function resguardo(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdv::class, 'resguardo_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdvEvento::class, 'evento_id');
    }

    public function bulto(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdvBulto::class, 'bulto_id');
    }

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdvIncidencia::class, 'incidencia_id');
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(ResguardoPdvEntrega::class, 'entrega_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
