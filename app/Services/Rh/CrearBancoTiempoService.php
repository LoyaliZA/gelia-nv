<?php

namespace App\Services\Rh;

use App\Models\RhBancoTiempo;
use App\Models\RhColaborador;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearBancoTiempoService
{
    public function __construct(
        private GenerarFolioBancoTiempoService $generarFolio,
    ) {}

    public function ejecutar(User $registrador, array $datos): RhBancoTiempo
    {
        $colaborador = RhColaborador::findOrFail($datos['rh_colaborador_id']);

        if (!$colaborador->activo) {
            throw ValidationException::withMessages([
                'rh_colaborador_id' => 'El colaborador debe estar activo.',
            ]);
        }

        return DB::transaction(function () use ($registrador, $datos) {
            return RhBancoTiempo::create([
                'uuid'               => (string) Str::uuid(),
                'folio'              => $this->generarFolio->ejecutar(),
                'rh_colaborador_id'  => $datos['rh_colaborador_id'],
                'horas_pendientes'   => $datos['horas_pendientes'],
                'origen_deuda'       => trim($datos['origen_deuda']),
                'estado'             => RhBancoTiempo::ESTADO_ACTIVA,
                'fecha_acuerdo'      => $datos['fecha_acuerdo'],
                'fecha_devolucion'   => null,
                'registrado_por_id'  => $registrador->id,
            ])->load(['colaborador.departamento', 'colaborador.area', 'registradoPor']);
        });
    }
}
