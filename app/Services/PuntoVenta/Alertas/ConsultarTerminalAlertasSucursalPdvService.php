<?php

namespace App\Services\PuntoVenta\Alertas;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\PdvTerminalAlertasSucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Alertas\CatalogoAlertasTurnosPdv;
use Carbon\CarbonInterface;

class ConsultarTerminalAlertasSucursalPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function estadoParaUsuario(
        User $actor,
        int $sucursalId,
        ?string $terminalId,
        CarbonInterface $ahora,
        string $proposito = PdvTerminalAlertasSucursal::PROPOSITO_ALERTAS,
    ): array {
        $proposito = PdvTerminalAlertasSucursal::normalizarProposito($proposito);
        $autorizado = $this->usuarioAutorizado($actor, $sucursalId, $proposito);

        $this->marcarVencidas($sucursalId, $ahora, $proposito);

        $activaSucursal = PdvTerminalAlertasSucursal::query()
            ->where('sucursal_id', $sucursalId)
            ->where('proposito', $proposito)
            ->where('estado', PdvTerminalAlertasSucursal::ESTADO_ACTIVA)
            ->orderByDesc('ultima_senal_at')
            ->first();

        $propia = null;
        if ($terminalId !== null && trim($terminalId) !== '') {
            $propia = PdvTerminalAlertasSucursal::query()
                ->where('terminal_id', $terminalId)
                ->where('proposito', $proposito)
                ->first();
        }

        $estadoUi = $this->resolverEstadoUi(
            $autorizado,
            $activaSucursal,
            $propia,
            $terminalId,
            $actor,
            $proposito === PdvTerminalAlertasSucursal::PROPOSITO_GENERAL,
        );

        return [
            'autorizado' => $autorizado,
            'estado' => $estadoUi,
            'terminal_id' => $propia?->terminal_id,
            'designacion' => $activaSucursal instanceof PdvTerminalAlertasSucursal
                ? $this->serializar($activaSucursal, $actor, $ahora)
                : null,
            'config' => $this->configTerminal(),
            'catalogo' => CatalogoAlertasTurnosPdv::definiciones(),
        ];
    }

    public function marcarVencidas(
        int $sucursalId,
        CarbonInterface $ahora,
        string $proposito = PdvTerminalAlertasSucursal::PROPOSITO_ALERTAS,
    ): void {
        $proposito = PdvTerminalAlertasSucursal::normalizarProposito($proposito);
        $limiteSegundos = max(30, (int) config('pdv_alertas.terminal.vencimiento_sin_latido_segundos', 120));
        $umbral = $ahora->copy()->subSeconds($limiteSegundos);

        PdvTerminalAlertasSucursal::query()
            ->where('sucursal_id', $sucursalId)
            ->where('proposito', $proposito)
            ->where('estado', PdvTerminalAlertasSucursal::ESTADO_ACTIVA)
            ->where('ultima_senal_at', '<', $umbral)
            ->update([
                'estado' => PdvTerminalAlertasSucursal::ESTADO_VENCIDA,
                'liberada_at' => $ahora,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(PdvTerminalAlertasSucursal $designacion, User $actor, CarbonInterface $ahora): array
    {
        return [
            'terminal_id' => $designacion->terminal_id,
            'sucursal_id' => (int) $designacion->sucursal_id,
            'user_id' => (int) $designacion->user_id,
            'estado' => $designacion->estado,
            'activada_at' => $designacion->activada_at?->toIso8601String(),
            'ultima_senal_at' => $designacion->ultima_senal_at?->toIso8601String(),
            'es_propia' => (int) $designacion->user_id === (int) $actor->id,
            'vigente' => $designacion->estado === PdvTerminalAlertasSucursal::ESTADO_ACTIVA
                && $this->esVigente($designacion, $ahora),
        ];
    }

    /**
     * @return array{latido_segundos: int, vencimiento_sin_latido_segundos: int}
     */
    private function configTerminal(): array
    {
        return [
            'latido_segundos' => max(10, (int) config('pdv_alertas.terminal.latido_segundos', 30)),
            'vencimiento_sin_latido_segundos' => max(30, (int) config('pdv_alertas.terminal.vencimiento_sin_latido_segundos', 120)),
        ];
    }

    private function esVigente(PdvTerminalAlertasSucursal $designacion, CarbonInterface $ahora): bool
    {
        if ($designacion->estado !== PdvTerminalAlertasSucursal::ESTADO_ACTIVA) {
            return false;
        }

        $limiteSegundos = max(30, (int) config('pdv_alertas.terminal.vencimiento_sin_latido_segundos', 120));

        return $designacion->ultima_senal_at !== null
            && $designacion->ultima_senal_at->greaterThanOrEqualTo($ahora->copy()->subSeconds($limiteSegundos));
    }

    private function usuarioAutorizado(User $actor, int $sucursalId, string $proposito): bool
    {
        if (! $this->alcance->idsSucursalesOperables($actor)->contains($sucursalId)) {
            return false;
        }

        if ($this->alcance->tienePermisoPdv($actor, PuntoVentaModulo::PERMISO_TURNOS_ALERTAS_SUCURSAL)) {
            return true;
        }

        return $proposito === PdvTerminalAlertasSucursal::PROPOSITO_GENERAL
            && $this->alcance->tienePermisoPdv($actor, PuntoVentaModulo::PERMISO_TURNOS_ATENDER);
    }

    private function resolverEstadoUi(
        bool $autorizado,
        ?PdvTerminalAlertasSucursal $activaSucursal,
        ?PdvTerminalAlertasSucursal $propia,
        ?string $terminalId,
        User $actor,
        bool $equipoCompartido = false,
    ): string {
        if (! $autorizado) {
            return 'no_autorizada';
        }

        $dueno = ! $equipoCompartido;
        if ($propia instanceof PdvTerminalAlertasSucursal
            && $propia->estado === PdvTerminalAlertasSucursal::ESTADO_ACTIVA
            && (! $dueno || (int) $propia->user_id === (int) $actor->id)
        ) {
            return 'terminal_activa';
        }

        if ($activaSucursal instanceof PdvTerminalAlertasSucursal) {
            if ($terminalId !== null && (string) $activaSucursal->terminal_id === $terminalId) {
                return 'conexion_perdida';
            }

            return 'otra_terminal_activa';
        }

        return 'disponible';
    }
}
