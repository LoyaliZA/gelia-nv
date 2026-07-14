<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteDireccion extends Model
{
    use SoftDeletes;

    public const ESTADO_PENDING = 'pending';

    public const ESTADO_VERIFIED = 'verified';

    public const ESTADO_REJECTED = 'rejected';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_PUBLIC_FORM = 'public_form';

    public const ORIGEN_LEGACY = 'legacy_migration';

    public const ORIGEN_INTERNAL = 'internal';

    public const TIPO_ENVIO = 'envio';

    public const TIPO_OCURRE = 'ocurre';

    public const TIPO_SUCURSAL = 'sucursal';

    protected $table = 'cliente_direcciones';

    protected $fillable = [
        'cliente_id',
        'numero_direccion',
        'etiqueta',
        'tipo_direccion',
        'nombre_destinatario',
        'telefono_destinatario',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'codigo_postal',
        'municipio',
        'ciudad',
        'estado',
        'pais',
        'referencias',
        'indicaciones_entrega',
        'es_principal',
        'esta_activa',
        'estado_verificacion',
        'origen',
        'version',
        'direccion_anterior_id',
        'verificada_en',
        'verificada_por',
        'creada_por',
        'actualizada_por',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'esta_activa' => 'boolean',
        'numero_direccion' => 'integer',
        'version' => 'integer',
        'verificada_en' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function direccionAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'direccion_anterior_id');
    }

    public function versionesSiguientes(): HasMany
    {
        return $this->hasMany(self::class, 'direccion_anterior_id');
    }

    public function verificadaPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificada_por');
    }

    public function creadaPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function scopeActivas($query)
    {
        return $query->where('esta_activa', true)->whereNull('deleted_at');
    }

    public function scopeVerificadas($query)
    {
        return $query->where('estado_verificacion', self::ESTADO_VERIFIED);
    }

    public function scopeVigentes($query)
    {
        return $query->activas()->where('esta_activa', true);
    }

    public function esSnapshotable(): bool
    {
        return $this->esta_activa
            && $this->estado_verificacion === self::ESTADO_VERIFIED
            && $this->deleted_at === null;
    }
}
