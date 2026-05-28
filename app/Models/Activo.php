<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activo extends Model
{
    use SoftDeletes;

    public const ESTADOS = ['disponible', 'asignado', 'mantenimiento', 'baja'];

    protected $fillable = [
        'folio',
        'catalogo_tipo_activo_id',
        'departamento_id',
        'area_id',
        'nombre',
        'descripcion',
        'estado',
        'atributos',
        'fecha_adquisicion',
        'fecha_vencimiento',
        'valor',
        'responsable_user_id',
        'registrado_por_id',
    ];

    protected $casts = [
        'atributos' => 'array',
        'fecha_adquisicion' => 'date',
        'fecha_vencimiento' => 'date',
        'valor' => 'decimal:2',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(CatalogoTipoActivo::class, 'catalogo_tipo_activo_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(ActivoAsignacion::class)->orderByDesc('fecha_inicio');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(ActivoMovimiento::class)->orderByDesc('created_at');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ActivoFoto::class)->orderBy('orden');
    }

    public function fotoPrincipal(): HasMany
    {
        return $this->hasMany(ActivoFoto::class)->where('es_principal', true);
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(ActivoMantenimiento::class)->orderByDesc('created_at');
    }

    public function mantenimientoActivo(): HasMany
    {
        return $this->hasMany(ActivoMantenimiento::class)
            ->whereIn('estado', ['programado', 'en_proceso']);
    }
}
