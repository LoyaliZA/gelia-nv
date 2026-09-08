<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoVendedorOperacionPdv;
use Carbon\CarbonInterface;

class ConsultaGestionVendedoresPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ConsultaEquipoOperativoPdvService $equipo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(User $actor, ?CarbonInterface $ahora = null): array
    {
        $ahora = $ahora ?? now();

        $this->alcance->asegurarConsultaPiso($actor, PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER);

        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            return [
                'servidor_at' => $ahora->toIso8601String(),
                'equipo' => [],
                'resumen' => $this->resumenVacio(),
                'clientes_en_fila' => 0,
            ];
        }

        $equipo = $this->equipo->listar($sucursalId, $ahora);
        $puedeGestionar = $this->alcance->tienePermisoPdv(
            $actor,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR,
        );

        if (! $puedeGestionar) {
            $equipo = array_map(
                static fn (array $miembro): array => array_merge($miembro, ['acciones' => []]),
                $equipo,
            );
        }

        return [
            'servidor_at' => $ahora->toIso8601String(),
            'equipo' => $equipo,
            'resumen' => $this->calcularResumen($equipo),
            'clientes_en_fila' => $this->contarClientesEnFila($sucursalId),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $equipo
     * @return array<string, int>
     */
    private function calcularResumen(array $equipo): array
    {
        $resumen = [
            'configurados' => count($equipo),
            'activos_hoy' => 0,
            'disponibles' => 0,
            'atendiendo' => 0,
            'en_pausa' => 0,
        ];

        foreach ($equipo as $miembro) {
            $estado = EstadoVendedorOperacionPdv::tryFrom((string) ($miembro['estado_vendedor'] ?? ''));
            if ($estado === null) {
                continue;
            }

            if (! in_array($estado, [
                EstadoVendedorOperacionPdv::NoActivado,
                EstadoVendedorOperacionPdv::NoLlego,
            ], true)) {
                $resumen['activos_hoy']++;
            }

            match ($estado) {
                EstadoVendedorOperacionPdv::Disponible => $resumen['disponibles']++,
                EstadoVendedorOperacionPdv::Atendiendo => $resumen['atendiendo']++,
                EstadoVendedorOperacionPdv::EnRetencion => $resumen['en_pausa']++,
                default => null,
            };
        }

        return $resumen;
    }

    /**
     * @return array<string, int>
     */
    private function resumenVacio(): array
    {
        return [
            'configurados' => 0,
            'activos_hoy' => 0,
            'disponibles' => 0,
            'atendiendo' => 0,
            'en_pausa' => 0,
        ];
    }

    private function contarClientesEnFila(int $sucursalId): int
    {
        return TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('servicio', TurnoPdv::SERVICIO_VENTAS)
            ->where('estado', TurnoPdv::ESTADO_EN_COLA)
            ->whereNull('atencion_actual_id')
            ->count();
    }
}
