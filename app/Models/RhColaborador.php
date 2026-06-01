<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RhColaborador extends Model
{
    use SoftDeletes;

    protected $table = 'rh_colaboradores';

    protected $fillable = [
        'uuid',
        'folio',
        'user_id',
        'departamento_id',
        'area_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'catalogo_puesto_id',
        'salario_base',
        'bono_productividad',
        'bono_puntualidad',
        'horas_laboradas_oficiales',
        'salario_diario',
        'bono_productividad_diario',
        'bono_puntualidad_diario',
        'salario_por_hora',
        'salario_por_minuto',
        'saldo_comisiones',
        'hora_entrada_oficial',
        'hora_salida_oficial',
        'activo',
        'registrado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'salario_base' => 'decimal:2',
            'bono_productividad' => 'decimal:2',
            'bono_puntualidad' => 'decimal:2',
            'horas_laboradas_oficiales' => 'decimal:2',
            'salario_diario' => 'decimal:2',
            'bono_productividad_diario' => 'decimal:2',
            'bono_puntualidad_diario' => 'decimal:2',
            'salario_por_hora' => 'decimal:4',
            'salario_por_minuto' => 'decimal:8',
            'saldo_comisiones' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim(collect([
            $this->nombre,
            $this->apellido_paterno,
            $this->apellido_materno,
        ])->filter()->implode(' '));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(CatalogoPuesto::class, 'catalogo_puesto_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function horasExtra(): HasMany
    {
        return $this->hasMany(RhHorasExtra::class, 'rh_colaborador_id');
    }

    public function deducciones(): HasMany
    {
        return $this->hasMany(RhDeduccion::class, 'rh_colaborador_id');
    }

    public function prestamosPagosFijos(): HasMany
    {
        return $this->hasMany(RhPrestamoPagoFijo::class, 'rh_colaborador_id');
    }

    /** @deprecated Use deducciones() */
    public function incidencias(): HasMany
    {
        return $this->deducciones();
    }

    public function bonos(): BelongsToMany
    {
        return $this->belongsToMany(CatalogoBono::class, 'rh_colaborador_bonos', 'rh_colaborador_id', 'catalogo_bono_id')
            ->withPivot('monto')
            ->withTimestamps();
    }
}
