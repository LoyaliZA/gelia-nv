<?php

namespace App\Services\PuntoVenta\Alertas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\PdvTerminalAlertasSucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

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
    public function ejecutar(User $actor, int $sucursalId, ?string $terminalId, CarbonInterface $ahora, string $motivo = 'manual'): array
    {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL,
            $sucursalId,
        );

        return DB::transaction(function () use ($actor, $sucursalId, $terminalId, $ahora, $motivo): array {
            $query = PdvTerminalAlertasSucursal::query()
                ->where('sucursal_id', $sucursalId)
                ->where('estado', PdvTerminalAlertasSucursal::ESTADO_ACTIVA)
                ->where('user_id', $actor->id);

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

            return $this->consulta->estadoParaUsuario($actor, $sucursalId, $terminalId, $ahora);
        });
    }

    public function liberarPorCierreSesion(User $actor, CarbonInterface $ahora): void
    {
        $designaciones = PdvTerminalAlertasSucursal::query()
            ->where('user_id', $actor->id)
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
