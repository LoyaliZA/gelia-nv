<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;

class ConsultaFormularioEntregaMultipleResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ConsultaFormularioEntregaResguardoPdvService $consultaEntrega,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return array{
     *     resguardos: list<array<string, mixed>>,
     *     catalogos: array<string, mixed>,
     *     puede_entregar: bool,
     *     motivo_no_entregable: string|null
     * }
     */
    public function obtener(User $user, array $ids): array
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR);

        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->filter()->values()->all();

        if (count($ids) < 2) {
            return [
                'resguardos' => [],
                'catalogos' => [
                    'estados' => EtiquetasResguardoPdv::estados(),
                    'relaciones' => EtiquetasResguardoPdv::relacionesEntrega(),
                    'tipos_incidencia' => EtiquetasResguardoPdv::tiposIncidencia(),
                    'metodo_validacion' => RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
                ],
                'puede_entregar' => false,
                'motivo_no_entregable' => 'Seleccione al menos dos resguardos para entrega múltiple.',
            ];
        }

        $resguardos = [];
        $catalogos = null;
        $bloqueados = [];

        foreach ($ids as $id) {
            $resguardo = ResguardoPdv::query()->findOrFail($id);
            $payload = $this->consultaEntrega->obtener($user, $resguardo);
            $catalogos = $payload['catalogos'];
            $item = $payload['resguardo'];
            $item['puede_entregar'] = $payload['puede_entregar'];
            $item['motivo_no_entregable'] = $payload['motivo_no_entregable'];
            $resguardos[] = $item;

            if (! $payload['puede_entregar']) {
                $bloqueados[] = $item['snapshot_folio'] ?: '#'.$item['id'];
            }
        }

        $puedeEntregar = $bloqueados === [];
        $motivo = $puedeEntregar
            ? null
            : 'No se pueden incluir resguardos no entregables: '.implode(', ', $bloqueados).'.';

        return [
            'resguardos' => $resguardos,
            'catalogos' => $catalogos ?? [],
            'puede_entregar' => $puedeEntregar,
            'motivo_no_entregable' => $motivo,
        ];
    }
}
