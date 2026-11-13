<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudBeneficio extends Model
{
    public const CAMPO_LISTA = 'lista';

    public const CAMPO_TIPO = 'tipo';

    public const CAMPO_VENDEDOR = 'vendedor';

    public const CAMPO_MODALIDAD = 'es_menudeo';

    public const ESTADO_VIGENTE = 'vigente';

    public const ESTADO_REVOCADO = 'revocado';

    public const ESTADO_REEMPLAZADO = 'reemplazado';

    protected $table = 'solicitud_beneficios';

    protected $fillable = [
        'cliente_id',
        'solicitud_tag_id',
        'campo',
        'valor_anterior',
        'valor_aplicado',
        'estado',
        'motivo',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTag::class, 'solicitud_tag_id');
    }
};
