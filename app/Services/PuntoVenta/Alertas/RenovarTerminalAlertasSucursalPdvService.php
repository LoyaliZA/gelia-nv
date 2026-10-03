<?php

namespace App\Services\PuntoVenta\Alertas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\PdvTerminalAlertasSucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RenovarTerminalAlertasSucursalPdvService
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
        string $terminalId,
        CarbonInterface $ahora,
        string $proposito = PdvTerminalAlertasSucursal::PROPOSITO_ALERTAS,
    ): array {
        $proposito = PdvTerminalAlertasSucursal::normalizarProposito($proposito);
        $permiso = PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL;
        if ($proposito === PdvTerminalAlertasSucursal::PROPOSITO_GENERAL
            && $this->alcance->tienePermisoPdv($actor, PuntoVentaModulo::PERMISO_TURNOS_ATENDER)) {
            $permiso = PuntoVentaModulo::PERMISO_TURNOS_ATENDER;
        }
        $this->alcance->asegurarMutacionPiso($actor, $permiso, $sucursalId);

        return DB::transaction(function () use ($actor, $sucursalId, $terminalId, $ahora, $proposito): array {
            $this->consulta->marcarVencidas($sucursalId, $ahora, $proposito);

            $designacion = PdvTerminalAlertasSucursal::query()
                ->where('terminal_id', $terminalId)
                ->where('proposito', $proposito)
                ->lockForUpdate()
                ->first();

            $duenoDistinto = $proposito !== PdvTerminalAlertasSucursal::PROPOSITO_GENERAL
                && (int) $designacion?->user_id !== (int) $actor->id;

            if (
                ! $designacion instanceof PdvTerminalAlertasSucursal
                || $designacion->estado !== PdvTerminalAlertasSucursal::ESTADO_ACTIVA
                || (int) $designacion->sucursal_id !== $sucursalId
                || $duenoDistinto
            ) {
                $this->auditoria->registrar(
                    $sucursalId,
                    $actor->id,
                    'latido_rechazado',
                    $ahora,
                    ['terminal_id' => $terminalId],
                );

                throw ValidationException::withMessages([
                    'terminal' => 'La designación de terminal no está activa para este equipo.',
                ]);
            }

            $designacion->update([
                'ultima_senal_at' => $ahora,
                'user_id' => $actor->id,
            ]);

            return $this->consulta->estadoParaUsuario($actor, $sucursalId, $terminalId, $ahora, $proposito);
        });
    }
}
