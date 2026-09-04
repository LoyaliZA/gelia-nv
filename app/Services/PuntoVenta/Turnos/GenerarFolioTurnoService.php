<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\PuntoVenta\ContadorFolioTurnoPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Support\PuntoVenta\Turnos\FolioTurnoGenerado;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerarFolioTurnoService
{
    private const MAX_REINTENTOS = 3;

    public function __construct(
        private readonly TurnosPdvConfig $config,
    ) {}

    public function ejecutar(
        Sucursal $sucursal,
        string $servicio = TurnoPdv::SERVICIO_VENTAS,
        ?Carbon $momento = null,
    ): FolioTurnoGenerado {
        $this->validarServicio($servicio);

        $zona = $this->config->zonaHorariaOperativa($sucursal->id);
        $momentoOperativo = ($momento ?? now())->copy()->timezone($zona);
        $fechaOperativa = $momentoOperativo->toDateString();

        $intentos = 0;

        while (true) {
            try {
                return $this->generarEnTransaccion(
                    $sucursal->id,
                    $servicio,
                    $fechaOperativa,
                );
            } catch (QueryException|UniqueConstraintViolationException $exception) {
                if (! $this->debeReintentar($exception) || ++$intentos >= self::MAX_REINTENTOS) {
                    throw $exception;
                }
            }
        }
    }

    private function generarEnTransaccion(
        int $sucursalId,
        string $servicio,
        string $fechaOperativa,
    ): FolioTurnoGenerado {
        return DB::transaction(function () use ($sucursalId, $servicio, $fechaOperativa): FolioTurnoGenerado {
            $contador = $this->obtenerContadorBloqueado($sucursalId, $fechaOperativa, $servicio);
            $secuencia = $contador->ultimo_numero + 1;

            $contador->update([
                'ultimo_numero' => $secuencia,
                'version' => $contador->version + 1,
            ]);

            return new FolioTurnoGenerado(
                folio: $this->config->formatearFolio($servicio, $secuencia),
                secuencia: $secuencia,
                fechaOperativa: $fechaOperativa,
                servicio: $servicio,
                sucursalId: $sucursalId,
            );
        });
    }

    private function obtenerContadorBloqueado(
        int $sucursalId,
        string $fechaOperativa,
        string $servicio,
    ): ContadorFolioTurnoPdv {
        $contador = ContadorFolioTurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->where('servicio', $servicio)
            ->lockForUpdate()
            ->first();

        if ($contador !== null) {
            return $contador;
        }

        try {
            return ContadorFolioTurnoPdv::query()->create([
                'sucursal_id' => $sucursalId,
                'fecha_operativa' => $fechaOperativa,
                'servicio' => $servicio,
                'ultimo_numero' => 0,
                'version' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            return ContadorFolioTurnoPdv::query()
                ->where('sucursal_id', $sucursalId)
                ->whereDate('fecha_operativa', $fechaOperativa)
                ->where('servicio', $servicio)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    private function validarServicio(string $servicio): void
    {
        if ($servicio !== TurnoPdv::SERVICIO_VENTAS) {
            throw new InvalidArgumentException("Servicio de turno no soportado: {$servicio}");
        }
    }

    private function debeReintentar(QueryException|UniqueConstraintViolationException $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        $codigo = $exception->errorInfo[1] ?? null;

        return in_array($codigo, [1062, 1213], true);
    }
}
