<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoReglaVersion;
use Illuminate\Support\Facades\DB;

class AbrirPeriodoEscalonamiento
{
    public function __construct(
        private ConstruirSnapshotListasEscalonamiento $construirSnapshot,
    ) {}

    public function abrir(int $anio, int $mes): EscalonamientoPeriodo
    {
        return DB::transaction(function () use ($anio, $mes) {
            $existente = EscalonamientoPeriodo::query()
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $existente;
            }

            $version = EscalonamientoReglaVersion::create([
                'snapshot' => $this->construirSnapshot->desdeCatalogo(),
            ]);

            return EscalonamientoPeriodo::create([
                'anio' => $anio,
                'mes' => $mes,
                'zona_horaria' => config('app.timezone', 'America/Mexico_City'),
                'estado' => EscalonamientoPeriodo::ESTADO_ABIERTO,
                'escalonamiento_regla_version_id' => $version->id,
            ]);
        });
    }

}
