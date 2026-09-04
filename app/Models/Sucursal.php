<?php

namespace App\Models;

use App\Models\PuntoVenta\ContadorFolioTurnoPdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    public function resguardosPdv(): HasMany
    {
        return $this->hasMany(ResguardoPdv::class);
    }

    public function turnosPdv(): HasMany
    {
        return $this->hasMany(TurnoPdv::class);
    }

    public function contadoresFolioTurnoPdv(): HasMany
    {
        return $this->hasMany(ContadorFolioTurnoPdv::class);
    }

    public function jornadasPdv(): HasMany
    {
        return $this->hasMany(JornadaPdv::class);
    }

    public function intervalosOperativosPdv(): HasMany
    {
        return $this->hasMany(IntervaloOperativoPdv::class);
    }

    public function diasOperacionPdv(): HasMany
    {
        return $this->hasMany(SucursalDiaOperacionPdv::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sucursal_user')
            ->using(SucursalUser::class)
            ->withPivot(['es_principal', 'activo'])
            ->withTimestamps();
    }
}
