<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiAuditoria extends Model
{
    public $timestamps = false;

    protected $table = 'api_auditoria';

    protected $fillable = [
        'api_aplicacion_id',
        'metodo',
        'ruta',
        'ip',
        'user_agent',
        'query_params',
        'request_id',
        'status_code',
        'duracion_ms',
        'error_resumen',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'query_params' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function aplicacion(): BelongsTo
    {
        return $this->belongsTo(ApiAplicacion::class, 'api_aplicacion_id');
    }
}
