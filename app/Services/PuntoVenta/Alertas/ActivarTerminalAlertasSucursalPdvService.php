<?php

namespace App\Services\PuntoVenta\Alertas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\PdvTerminalAlertasSucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivarTerminalAlertasSucursalPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ConsultarTerminalAlertasSucursalPdvService $consulta,
        private readonly RegistrarAuditoriaTerminalAlertasPdvService $auditoria,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        User $actor,
        int $sucursalId,
        ?string $terminalId,
        CarbonInterface $ahora,
        string $proposito = PdvTerminalAlertasSucursal::PROPOSITO_ALERTAS,
    ): array {
        $proposito = PdvTerminalAlertasSucursal::normalizarProposito($proposito);
        $this->asegurarPermiso($actor, $sucursalId, $proposito);

        $terminalId = $this->normalizarTerminalId($terminalId);

        return DB::transaction(function () use ($actor, $sucursalId, $terminalId, $ahora, $proposito): array {
            $this->consulta->marcarVencidas($sucursalId, $ahora, $proposito);

            $activaExistente = PdvTerminalAlertasSucursal::query()
                ->where('sucursal_id', $sucursalId)
                ->where('proposito', $proposito)
                ->where('estado', PdvTerminalAlertasSucursal::ESTADO_ACTIVA)
                ->lockForUpdate()
                ->first();

            $maxActivas = max(1, (int) config('pdv_alertas.terminal.max_activas_por_sucursal', 1));

            if ($activaExistente instanceof PdvTerminalAlertasSucursal) {
                if ((string) $activaExistente->terminal_id === $terminalId) {
                    $activaExistente->update(['ultima_senal_at' => $ahora]);

                    return $this->consulta->estadoParaUsuario($actor, $sucursalId, $terminalId, $ahora, $proposito);
                }

                if ($maxActivas <= 1) {
                    $this->auditoria->registrar(
                        $sucursalId,
                        $actor->id,
                        'rechazo_terminal_ocupada',
                        $ahora,
                        [
                            'terminal_solicitada' => $terminalId,
                            'terminal_activa' => $activaExistente->terminal_id,
                        ],
                        $activaExistente->id,
                    );

                    throw ValidationException::withMessages([
                        'terminal' => 'Otra terminal ya está activa en esta sucursal.',
                    ]);
                }
            }

            $previa = PdvTerminalAlertasSucursal::query()
                ->where('terminal_id', $terminalId)
                ->where('proposito', $proposito)
                ->lockForUpdate()
                ->first();

            if ($previa instanceof PdvTerminalAlertasSucursal) {
                if (
                    $previa->estado === PdvTerminalAlertasSucursal::ESTADO_ACTIVA
                    && (int) $previa->sucursal_id !== $sucursalId
                ) {
                    $this->liberarDesignacion($previa, $ahora, 'cambio_sucursal');
                } elseif ($previa->estado === PdvTerminalAlertasSucursal::ESTADO_ACTIVA) {
                    $previa->update([
                        'user_id' => $actor->id,
                        'ultima_senal_at' => $ahora,
                    ]);

                    return $this->consulta->estadoParaUsuario($actor, $sucursalId, $terminalId, $ahora, $proposito);
                }
            }

            $designacion = PdvTerminalAlertasSucursal::query()->updateOrCreate(
                ['terminal_id' => $terminalId, 'proposito' => $proposito],
                [
                    'sucursal_id' => $sucursalId,
                    'user_id' => $actor->id,
                    'estado' => PdvTerminalAlertasSucursal::ESTADO_ACTIVA,
                    'activada_at' => $ahora,
                    'ultima_senal_at' => $ahora,
                    'liberada_at' => null,
                ],
            );

            $this->auditoria->registrar(
                $sucursalId,
                $actor->id,
                'activacion',
                $ahora,
                ['terminal_id' => $terminalId],
                $designacion->id,
            );

            return $this->consulta->estadoParaUsuario($actor, $sucursalId, $terminalId, $ahora, $proposito);
        });
    }

    private function asegurarPermiso(User $actor, int $sucursalId, string $proposito): void
    {
        $permiso = PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL;
        if ($proposito === PdvTerminalAlertasSucursal::PROPOSITO_GENERAL
            && $this->alcance->tienePermisoPdv($actor, PuntoVentaModulo::PERMISO_TURNOS_ATENDER)) {
            $permiso = PuntoVentaModulo::PERMISO_TURNOS_ATENDER;
        }

        $this->alcance->asegurarMutacionPiso($actor, $permiso, $sucursalId);
    }

    private function normalizarTerminalId(?string $terminalId): string
    {
        $valor = trim((string) $terminalId);
        if ($valor !== '' && Str::isUuid($valor)) {
            return $valor;
        }

        return (string) Str::uuid();
    }

    private function liberarDesignacion(PdvTerminalAlertasSucursal $designacion, CarbonInterface $ahora, string $motivo): void
    {
        $designacion->update([
            'estado' => PdvTerminalAlertasSucursal::ESTADO_LIBERADA,
            'liberada_at' => $ahora,
        ]);

        $this->auditoria->registrar(
            (int) $designacion->sucursal_id,
            (int) $designacion->user_id,
            'liberacion',
            $ahora,
            ['motivo' => $motivo, 'terminal_id' => $designacion->terminal_id],
            $designacion->id,
        );
    }
}
