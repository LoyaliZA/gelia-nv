<?php

namespace App\Models\Escalonamiento;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalonamientoPeriodo extends Model
{
    public const ESTADO_ABIERTO = 'abierto';

    public const ESTADO_EN_REVISION = 'en_revision';

    public const ESTADO_AUTORIZADO = 'autorizado';

    public const ESTADO_APLICACION_PENDIENTE = 'aplicacion_interna_pendiente';

    public const ESTADO_CERRADO = 'cerrado';

    public const ESTADO_HISTORIAL = 'historial';

    protected $table = 'escalonamiento_periodos';

    protected $fillable = [
        'anio',
        'mes',
        'zona_horaria',
        'estado',
        'fecha_corte',
        'escalonamiento_regla_version_id',
        'escalonamiento_cierre_vigente_id',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'fecha_corte' => 'datetime',
    ];

    public function reglaVersion(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoReglaVersion::class, 'escalonamiento_regla_version_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoVenta::class, 'escalonamiento_periodo_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(EscalonamientoMovimiento::class, 'escalonamiento_periodo_id');
    }

    public function resumenes(): HasMany
    {
        return $this->hasMany(EscalonamientoResumenCliente::class, 'escalonamiento_periodo_id');
    }

    public function cierres(): HasMany
    {
        return $this->hasMany(EscalonamientoCierre::class, 'escalonamiento_periodo_id');
    }

    public function cierreVigente(): BelongsTo
    {
        return $this->belongsTo(EscalonamientoCierre::class, 'escalonamiento_cierre_vigente_id');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === self::ESTADO_ABIERTO;
    }

    public function permiteOperacionDocumentos(): bool
    {
        return $this->estaAbierto();
    }

    public function tieneCierreAplicado(): bool
    {
        if ($this->estado === self::ESTADO_CERRADO) {
            return true;
        }

        $this->loadMissing('cierreVigente');

        return $this->cierreVigente !== null && $this->cierreVigente->estaAplicado();
    }

    public function estaCerradoOficialmente(): bool
    {
        return $this->tieneCierreAplicado();
    }

    public function permiteBackfillDocumentos(): bool
    {
        if ($this->estado === self::ESTADO_HISTORIAL) {
            return true;
        }

        if ($this->estado === self::ESTADO_ABIERTO) {
            return ! $this->tieneCierreAplicado();
        }

        return false;
    }

    public function permiteEscrituraMovimientos(): bool
    {
        return $this->permiteBackfillDocumentos();
    }

    /**
     * Un mes histórico se cierra en paralelo al operativo: no sustituye al período abierto.
     */
    public function permiteSimularCierre(): bool
    {
        if ($this->tieneCierreAplicado()) {
            return false;
        }

        return in_array($this->estado, [self::ESTADO_ABIERTO, self::ESTADO_HISTORIAL], true);
    }

    public function etiquetaMes(): string
    {
        return sprintf('%04d-%02d', $this->anio, $this->mes);
    }
}
