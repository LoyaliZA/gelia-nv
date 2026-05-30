<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class ApiAplicacion extends Model
{
    use HasApiTokens;

    protected $table = 'api_aplicaciones';

    protected $fillable = [
        'nombre',
        'descripcion',
        'client_id',
        'client_secret',
        'activa',
        'ips_permitidas',
        'limite_por_minuto',
        'creado_por',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'ips_permitidas' => 'array',
            'client_secret' => 'hashed',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(ApiAplicacionPermiso::class);
    }

    public function campos(): HasMany
    {
        return $this->hasMany(ApiAplicacionCampo::class);
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(ApiAuditoria::class);
    }

    public function ipPermitida(?string $ip): bool
    {
        if (empty($this->ips_permitidas)) {
            return true;
        }

        return in_array($ip, $this->ips_permitidas, true);
    }
}
