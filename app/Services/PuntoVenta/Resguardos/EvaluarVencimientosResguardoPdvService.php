<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelvePlazosCustodiaResguardoPdv;
use App\Events\PuntoVenta\ResguardoPdvMarcadoRezagado;
use App\Events\PuntoVenta\ResguardoPdvMarcadoVencido;
use App\Events\PuntoVenta\ResguardoPdvProximoAVencer;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Notifications\PuntoVenta\AlertaResguardoPdvNotification;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvaluarVencimientosResguardoPdvService
{
    public function __construct(
        private readonly ResuelvePlazosCustodiaResguardoPdv $plazos,
        private readonly CalcularAntiguedadOperativaResguardoPdvService $antiguedad,
        private readonly NotificarResguardoPdvService $notificaciones,
    ) {}

    /**
     * @return array{vencidos: int, rezagados: int, proximos: int, omitidos: int}
     */
    public function ejecutar(?Carbon $ahora = null): array
    {
        $config = $this->plazos->obtenerGlobal();
        if ($config === null || ! $config['activo']) {
            return ['vencidos' => 0, 'rezagados' => 0, 'proximos' => 0, 'omitidos' => 0];
        }

        $zona = $config['zona_horaria'];
        $ahora = ($ahora ?? now())->copy()->timezone($zona);

        $vencidos = 0;
        $rezagados = 0;
        $proximos = 0;
        $omitidos = 0;

        ResguardoPdv::query()
            ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->whereNotNull('recepcion_fisica_at')
            ->whereNull('entrega_completada_at')
            ->whereNull('devolucion_confirmada_at')
            ->whereDoesntHave('eventos', fn ($q) => $q->where(
                'tipo_evento',
                ResguardoPdvEvento::TIPO_MARCADO_VENCIDO
            ))
            ->orderBy('id')
            ->chunkById(100, function ($resguardos) use ($ahora, &$vencidos, &$omitidos) {
                foreach ($resguardos as $resguardo) {
                    if ($this->marcarVencidoSiCorresponde($resguardo, $ahora)) {
                        $vencidos++;
                    } else {
                        $omitidos++;
                    }
                }
            });

        ResguardoPdv::query()
            ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->whereNotNull('recepcion_fisica_at')
            ->whereNull('entrega_completada_at')
            ->whereNull('devolucion_confirmada_at')
            ->orderBy('id')
            ->chunkById(100, function ($resguardos) use ($ahora, &$proximos, &$omitidos) {
                foreach ($resguardos as $resguardo) {
                    if ($this->notificarProximoAVencerSiCorresponde($resguardo, $ahora)) {
                        $proximos++;
                    }
                }
            });

        ResguardoPdv::query()
            ->where('estado', ResguardoPdv::ESTADO_PENDIENTE_RECEPCION)
            ->whereNull('recepcion_fisica_at')
            ->whereNotNull('salida_cedis_at')
            ->whereDoesntHave('eventos', fn ($q) => $q->where(
                'tipo_evento',
                ResguardoPdvEvento::TIPO_MARCADO_REZAGADO
            ))
            ->orderBy('id')
            ->chunkById(100, function ($resguardos) use ($ahora, &$rezagados, &$omitidos) {
                foreach ($resguardos as $resguardo) {
                    if ($this->marcarRezagadoSiCorresponde($resguardo, $ahora)) {
                        $rezagados++;
                    } else {
                        $omitidos++;
                    }
                }
            });

        return [
            'vencidos' => $vencidos,
            'rezagados' => $rezagados,
            'proximos' => $proximos,
            'omitidos' => $omitidos,
        ];
    }

    private function marcarVencidoSiCorresponde(ResguardoPdv $resguardo, Carbon $ahora): bool
    {
        $evaluacion = $this->antiguedad->evaluar($resguardo, $ahora);
        if (! ($evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::VENCIDO] ?? false)) {
            return false;
        }

        $evento = $this->registrarEvento(
            $resguardo,
            ResguardoPdvEvento::TIPO_MARCADO_VENCIDO,
            $this->idempotencyKey($resguardo->id, 'marcado_vencido'),
            $evaluacion
        );

        if ($evento !== null) {
            ResguardoPdvMarcadoVencido::dispatch(
                $resguardo,
                $evento,
                (int) $resguardo->sucursal_id,
            );
        }

        return $evento !== null;
    }

    private function marcarRezagadoSiCorresponde(ResguardoPdv $resguardo, Carbon $ahora): bool
    {
        $evaluacion = $this->antiguedad->evaluar($resguardo, $ahora);
        if (! ($evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::REZAGADO] ?? false)) {
            return false;
        }

        $evento = $this->registrarEvento(
            $resguardo,
            ResguardoPdvEvento::TIPO_MARCADO_REZAGADO,
            $this->idempotencyKey($resguardo->id, 'marcado_rezagado'),
            $evaluacion
        );

        if ($evento !== null) {
            ResguardoPdvMarcadoRezagado::dispatch(
                $resguardo,
                $evento,
                (int) $resguardo->sucursal_id,
            );
        }

        return $evento !== null;
    }

    private function notificarProximoAVencerSiCorresponde(ResguardoPdv $resguardo, Carbon $ahora): bool
    {
        $evaluacion = $this->antiguedad->evaluar($resguardo, $ahora);
        if (! ($evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER] ?? false)) {
            return false;
        }

        if ($evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::VENCIDO] ?? false) {
            return false;
        }

        $clave = $this->idempotencyKey($resguardo->id, 'proximo_a_vencer');
        if (! $this->notificaciones->requiereNotificacion(
            (int) $resguardo->sucursal_id,
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            $clave,
            AlertaResguardoPdvNotification::TIPO_PROXIMO_A_VENCER,
        )) {
            return false;
        }

        ResguardoPdvProximoAVencer::dispatch(
            $resguardo,
            (int) $resguardo->sucursal_id,
            $clave,
            $evaluacion,
        );

        return true;
    }

    /**
     * @param  array{
     *   clasificaciones: array<string, bool>,
     *   fecha_limite_custodia: string|null,
     *   fecha_limite_rezago: string|null,
     *   plazos_snapshot: array<string, mixed>|null
     * }  $evaluacion
     */
    private function registrarEvento(
        ResguardoPdv $resguardo,
        string $tipoEvento,
        string $idempotencyKey,
        array $evaluacion,
    ): ?ResguardoPdvEvento {
        try {
            return DB::transaction(function () use ($resguardo, $tipoEvento, $idempotencyKey, $evaluacion) {
                if (ResguardoPdvEvento::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                    return null;
                }

                return ResguardoPdvEvento::query()->create([
                    'resguardo_id' => $resguardo->id,
                    'tipo_evento' => $tipoEvento,
                    'estado_anterior' => $resguardo->estado,
                    'estado_nuevo' => $resguardo->estado,
                    'actor_id' => null,
                    'ocurrido_at' => now(),
                    'idempotency_key' => $idempotencyKey,
                    'snapshot_json' => [
                        'clasificaciones' => $evaluacion['clasificaciones'],
                        'fecha_limite_custodia' => $evaluacion['fecha_limite_custodia'],
                        'fecha_limite_rezago' => $evaluacion['fecha_limite_rezago'],
                        'plazos' => $evaluacion['plazos_snapshot'],
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Error al evaluar vencimiento/rezago de resguardo PDV', [
                'resguardo_id' => $resguardo->id,
                'tipo' => $tipoEvento,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return null;
        }
    }

    private function idempotencyKey(int $resguardoId, string $umbral): string
    {
        return sprintf('resguardo:%d:%s', $resguardoId, $umbral);
    }
}
