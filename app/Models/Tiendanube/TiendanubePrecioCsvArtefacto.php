<?php

namespace App\Models\Tiendanube;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePrecioCsvArtefacto extends Model
{
    public const ESTADO_GENERADO = 'generado';

    public const ESTADO_DESCARGADO = 'descargado';

    public const ESTADO_IMPORTACION_DECLARADA = 'importacion_declarada';

    protected $table = 'tiendanube_precio_csv_artefactos';

    protected $fillable = [
        'lote_id',
        'revision_id',
        'revision_checksum',
        'perfil_id',
        'perfil_version',
        'store_id',
        'preset_usado',
        'columnas_exportadas',
        'columnas_hash',
        'estado',
        'archivo_path',
        'nombre_archivo',
        'hash_sha256',
        'filas_exportadas',
        'filas_con_cambio_precio',
        'filas_contexto',
        'particion',
        'particiones_total',
        'generado_por',
        'generado_at',
        'descargado_at',
        'importacion_declarada_at',
    ];

    protected function casts(): array
    {
        return [
            'lote_id' => 'string',
            'revision_id' => 'integer',
            'perfil_id' => 'integer',
            'perfil_version' => 'integer',
            'store_id' => 'integer',
            'columnas_exportadas' => 'array',
            'filas_exportadas' => 'integer',
            'filas_con_cambio_precio' => 'integer',
            'filas_contexto' => 'integer',
            'particion' => 'integer',
            'particiones_total' => 'integer',
            'generado_por' => 'integer',
            'generado_at' => 'datetime',
            'descargado_at' => 'datetime',
            'importacion_declarada_at' => 'datetime',
        ];
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioCsvPerfil::class, 'perfil_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(TiendanubePrecioLote::class, 'lote_id');
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
