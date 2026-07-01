<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambioListaImportacionCliente extends Model
{
    public const TIPO_ASCENSO = 'ascenso';

    public const TIPO_DESCENSO = 'descenso';

    public const TIPO_CAMBIO = 'cambio';

    protected $table = 'cambios_lista_importacion_clientes';

    protected $fillable = [
        'importacion_cliente_id',
        'numero_cliente',
        'nombre_cliente',
        'lista_anterior',
        'lista_nueva',
        'tipo_cambio',
        'codigo_lista',
        'monto_nuevo',
    ];

    protected $casts = [
        'monto_nuevo' => 'decimal:2',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionCliente::class, 'importacion_cliente_id');
    }
}
