<?php

namespace App\Services\PuntoVenta\Alertas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\PdvTerminalAlertasSucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LiberarTerminalAlertasSucursalPdvService
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
        string $motivo = 'manual',
        string $proposito = PdvTerminalAlertasSucursal::PROPOSITO_ALERTAS,
    ): array {
        $proposito = PdvTerminalAlertasSucursal::normalizarProposito($proposito);
        $permiso = PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL;
        if ($proposito === PdvTerminalAlertasSucursal::PROPOSITO_GENERAL
            && $this->alcance->tienePermisoPdv($actor, PuntoVentaModulo::PERMISO_TURNOS_ATENDER)) {
            $permiso = PuntoVentaModulo::PERMISO_TURNOS_ATENDER;
        }
        $this->alcance->asegurarMutacionPiso($actor, $permiso, $sucursalId);

        if ($proposito === PdvTerminalAlertasSucursal::PROPOSITO_GENERAL && trim((string) $terminalId) === '') {
            throw ValidationException::withMessages([
                'terminal_id' => 'Indica el equipo que deja de ser terminal general.',
            ]);
        }

        return DB::transaction(function () use ($actor, $sucursalId, $terminalId, $ahora, $motivo, $proposito): array {
            $query = PdvTerminalAlertasSucursal::query()
                ->where('sucursal_id', $sucursalId)
                ->where('proposito', $proposito)
                ->where('estado', PdvTerminalAlertasSucursal::ESTADO_ACTIVA);

            if ($proposito !== PdvTerminalAlertasSucursal::PROPOSITO_GENERAL) {
                $query->where('user_id', $actor->id);
            }

            if ($terminalId !== null && trim($terminalId) !== '') {
                $query->where('terminal_id', $terminalId);
            }

            $designaciones = $query->lockForUpdate()->get();

            foreach ($designaciones as $designacion) {
                $designacion->update([
                    'estado' => PdvTerminalAlertasSucursal::ESTADO_LIBERADA,
                    'liberada_at' => $ahora,
                ]);

                $this->auditoria->registrar(
                    $sucursalId,
                    $actor->id,
                    'liberacion',
                    $ahora,
                    ['motivo' => $motivo, 'terminal_id' => $designacion->terminal_id],
                    $designacion->id,
                );
            }

            return $this->consulta->estadoParaUsuario($actor, $sucursalId, $terminalId, $ahora, $proposito);
        });
    }

    public function liberarPorCierreSesion(User $actor, CarbonInterface $ahora): void
    {
        $designaciones = PdvTerminalAlertasSucursal::query()
            ->where('user_id', $actor->id)
            ->where('proposito', PdvTerminalAlertasSucursal::PROPOSITO_ALERTAS)
            ->where('estado', PdvTerminalAlertasSucursal::ESTADO_ACTIVA)
            ->lockForUpdate()
            ->get();

        foreach ($designaciones as $designacion) {
            $designacion->update([
                'estado' => PdvTerminalAlertasSucursal::ESTADO_LIBERADA,
                'liberada_at' => $ahora,
            ]);

            $this->auditoria->registrar(
                (int) $designacion->sucursal_id,
                $actor->id,
                'liberacion',
                $ahora,
                ['motivo' => 'cierre_sesion', 'terminal_id' => $designacion->terminal_id],
                $designacion->id,
            );
        }
    }
}
