<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolicitudTraspaso extends Model
{
    use SoftDeletes;

    protected $table = 'solicitudes_traspasos';

    protected $fillable = [
        'folio',
        'vendedor_id',
        'departamento_id',
        'cliente_id',
        'almacen_origen_id',
        'catalogo_estado_solicitud_id',
        'catalogo_horario_traspaso_id',
        'fecha_entrega_estimada',
        'total_piezas',
        'folio_traspaso',
        'evidencia_respuesta_path',
        'motivo_respuesta',
        'motivo_incorrecta',
        'respondida_por_id',
        'respondida_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega_estimada' => 'date',
            'respondida_at' => 'datetime',
            'total_piezas' => 'integer',
        ];
    }

    protected $appends = [
        'tiene_evidencia_respuesta',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function almacenOrigen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstadoSolicitud::class, 'catalogo_estado_solicitud_id');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(CatalogoHorarioTraspaso::class, 'catalogo_horario_traspaso_id');
    }

    public function respondidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondida_por_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(SolicitudTraspasoProducto::class, 'solicitud_traspaso_id');
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(AuditoriaSolicitudTraspaso::class, 'solicitud_traspaso_id')->orderByDesc('created_at');
    }

    public function getTieneEvidenciaRespuestaAttribute(): bool
    {
        return ! empty($this->evidencia_respuesta_path);
    }

    public static function generarFolio(): string
    {
        $anio = now()->format('Y');
        $ultimo = static::withTrashed()
            ->where('folio', 'like', "TRA-{$anio}-%")
            ->orderByDesc('id')
            ->value('folio');

        $secuencia = 1;
        if ($ultimo && preg_match('/TRA-\d{4}-(\d+)/', $ultimo, $m)) {
            $secuencia = (int) $m[1] + 1;
        }

        return sprintf('TRA-%s-%05d', $anio, $secuencia);
    }
}
