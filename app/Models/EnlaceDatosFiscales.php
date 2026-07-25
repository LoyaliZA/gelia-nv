<?php

namespace App\Models;

use App\Support\FormPublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnlaceDatosFiscales extends Model
{
    public const ACCION_PRIMERA = 'register_first';

    public const ACCION_ACTUALIZAR = 'update_fields';

    public const CAMPOS = [
        'rfc',
        'codigo_postal',
        'regimen_fiscal',
        'correo_electronico',
        'uso_factura',
        'nombre_razon_social',
        'telefono',
    ];

    protected $table = 'enlaces_datos_fiscales';

    protected $fillable = [
        'solicitud_factura_id',
        'cliente_id',
        'token_hash',
        'codigo_publico',
        'accion_permitida',
        'campos_permitidos',
        'destinatario_tipo',
        'expira_en',
        'usado_en',
        'revocado_en',
        'creado_por',
    ];

    protected $casts = [
        'campos_permitidos' => 'array',
        'expira_en' => 'datetime',
        'usado_en' => 'datetime',
        'revocado_en' => 'datetime',
    ];

    protected $appends = [
        'url',
    ];

    public function getUrlAttribute(): ?string
    {
        return $this->urlPublica();
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudFactura::class, 'solicitud_factura_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function creadoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function estaVigente(): bool
    {
        if ($this->revocado_en !== null) {
            return false;
        }

        if ($this->usado_en !== null) {
            return false;
        }

        if ($this->expira_en !== null && $this->expira_en->isPast()) {
            return false;
        }

        return true;
    }

    public function fueUsado(): bool
    {
        return $this->usado_en !== null;
    }

    public function urlPublica(): ?string
    {
        if (! $this->codigo_publico) {
            return null;
        }

        return FormPublicUrl::fiscalShow($this->codigo_publico);
    }
}
