<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteDocumento extends Model
{
    use SoftDeletes;

    public const TIPO_IDENTIFICACION = 'identificacion';

    protected $table = 'cliente_documentos';

    protected $fillable = [
        'consignatario_id', 'tipo', 'ruta', 'nombre_original', 'mime_type', 'tamano_bytes',
        'hash_sha256', 'vigente', 'version', 'anterior_id', 'creado_por_id',
    ];

    protected $hidden = ['ruta'];

    protected $casts = ['vigente' => 'boolean', 'version' => 'integer', 'tamano_bytes' => 'integer'];

    public function consignatario(): BelongsTo
    {
        return $this->belongsTo(ClienteConsignatario::class, 'consignatario_id')->withTrashed();
    }
}
