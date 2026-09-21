<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\ConsultaBandejaReatencionPdvService;
use Carbon\CarbonInterface;

class ConsultaOperacionGeneralPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ConsultaEstadoOperativoPdvService $estadoOperativo,
        private readonly ConsultaGestionVendedoresPdvService $gestionVendedores,
        private readonly ConsultaMotivosPausaPdvService $motivosPausa,
        private readonly ConsultaBandejaReatencionPdvService $bandejaReatencion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(User $actor, ?CarbonInterface $ahora = null): array
    {
        $ahora = $ahora ?? now();
        $puedeVerOperacion = $this->alcance->tienePermisoPdv($actor, PuntoVentaModulo::PERMISO_TURNOS_VER);
        $puedeVerEquipo = $this->alcance->tienePermisoPdv($actor, PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER);

        $payload = [
            'servidor_at' => $ahora->toIso8601String(),
        ];

        if ($puedeVerOperacion) {
            $payload = array_merge($payload, $this->estadoOperativo->ejecutar($actor, $ahora));
            if (! $puedeVerEquipo) {
                $payload['equipo'] = [];
            }
        }

        if ($puedeVerEquipo) {
            $payload = array_merge($payload, $this->gestionVendedores->ejecutar($actor, $ahora));
            $payload['motivos_pausa'] = $this->motivosPausa->listarActivos();
            $payload['reatencion'] = $this->bandejaReatencion->listar($actor, $ahora);
        }

        return $payload;
    }
}
