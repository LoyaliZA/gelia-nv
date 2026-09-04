<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ConsultaEstadoOperativoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly OperacionPdvConfig $config,
        private readonly HorarioCierreOperacionPdvConfig $horarioCierre,
        private readonly ConsultaEquipoOperativoPdvService $equipo,
        private readonly ResolverUmbralCierreSucursalPdv $umbralCierre,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(User $actor, ?CarbonInterface $ahora = null): array
    {
        $ahora = $ahora ?? now();

        $this->alcance->asegurarConsultaPiso($actor, PuntoVentaModulo::PERMISO_TURNOS_VER);

        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            return [
                'servidor_at' => $ahora->toIso8601String(),
                'jornada' => null,
                'intervalo' => null,
                'actividad' => null,
                'sucursal_dia' => [
                    'acepta_altas' => null,
                    'version' => null,
                ],
                'horario_cierre' => [
                    'configurado' => false,
                    'hora_cierre' => null,
                    'zona_horaria' => null,
                    'es_override_sucursal' => false,
                ],
                'cierre_programado' => null,
                'equipo' => [],
            ];
        }

        $jornada = JornadaPdv::query()
            ->where('user_id', $actor->id)
            ->where('sucursal_id', $sucursalId)
            ->whereIn('estado', [
                EstadoJornadaPdv::Abierta,
                EstadoJornadaPdv::CerradaConAtencion,
            ])
            ->first();

        $intervalo = IntervaloOperativoPdv::query()
            ->where('user_id', $actor->id)
            ->where('sucursal_id', $sucursalId)
            ->whereNull('fin_at')
            ->first();

        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);
        $dia = SucursalDiaOperacionPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->first();

        $horarioEfectivo = $this->horarioCierre->resolverParaSucursal($sucursalId);
        $global = $this->horarioCierre->obtenerGlobal();
        $claveSucursal = (string) $sucursalId;

        return [
            'servidor_at' => $ahora->toIso8601String(),
            'jornada' => $jornada instanceof JornadaPdv ? [
                'id' => $jornada->id,
                'estado' => $jornada->estado->value,
                'apertura_at' => $jornada->apertura_at?->toIso8601String(),
                'cierre_at' => $jornada->cierre_at?->toIso8601String(),
                'version' => $jornada->version,
            ] : null,
            'intervalo' => $intervalo instanceof IntervaloOperativoPdv ? [
                'tipo' => $intervalo->tipo?->value,
                'inicio_at' => $intervalo->inicio_at?->toIso8601String(),
            ] : null,
            'actividad' => $intervalo?->tipo?->value,
            'sucursal_dia' => [
                'acepta_altas' => $dia?->acepta_altas ?? true,
                'cierre_manual_at' => $dia?->cierre_manual_at?->toIso8601String(),
                'ampliacion_hasta_at' => $dia?->ampliacion_hasta_at?->toIso8601String(),
                'cierre_automatico_invalidado' => (bool) ($dia?->cierre_automatico_invalidado ?? false),
                'version' => $dia?->version ?? 1,
            ],
            'horario_cierre' => [
                'configurado' => $horarioEfectivo !== null,
                'hora_cierre' => $horarioEfectivo['hora_cierre'] ?? null,
                'zona_horaria' => $horarioEfectivo['zona_horaria'] ?? null,
                'es_override_sucursal' => isset($global['por_sucursal'][$claveSucursal]),
            ],
            'cierre_programado' => $this->serializarCierreProgramado($sucursalId, $dia, $ahora),
            'equipo' => $this->equipo->listar($sucursalId),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializarCierreProgramado(
        int $sucursalId,
        ?SucursalDiaOperacionPdv $dia,
        CarbonInterface $ahora,
    ): ?array {
        if (! $dia instanceof SucursalDiaOperacionPdv) {
            return null;
        }

        $evaluacion = $this->umbralCierre->evaluar($sucursalId, $dia, $ahora);
        if (! $evaluacion['aplicable']) {
            $horario = $this->horarioCierre->resolverParaSucursal($sucursalId);
            if ($horario === null || $dia->cierre_manual_at !== null) {
                return null;
            }

            $zona = $horario['zona_horaria'];
            $ahoraLocal = $ahora->copy()->timezone($zona);
            $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahoraLocal);

            if ($dia->ampliacion_hasta_at !== null) {
                return [
                    'tipo' => 'ampliacion',
                    'referencia_at' => $dia->ampliacion_hasta_at->toIso8601String(),
                    'vencido' => $ahoraLocal->gte($dia->ampliacion_hasta_at->copy()->timezone($zona)),
                ];
            }

            [$hora, $minuto] = array_map('intval', explode(':', $horario['hora_cierre']));
            $umbral = Carbon::parse($fechaOperativa, $zona)->setTime($hora, $minuto, 0);

            return [
                'tipo' => 'configuracion',
                'referencia_at' => $umbral->toIso8601String(),
                'vencido' => $ahoraLocal->gte($umbral),
            ];
        }

        return [
            'tipo' => $evaluacion['snapshot']['origen_umbral'] ?? 'configuracion',
            'referencia_at' => $evaluacion['umbral']->toIso8601String(),
            'vencido' => true,
        ];
    }
}
