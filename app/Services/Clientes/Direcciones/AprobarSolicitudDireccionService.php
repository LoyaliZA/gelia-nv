<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\ClienteDireccion;
use App\Models\SolicitudDireccion;
use Illuminate\Support\Facades\DB;

class AprobarSolicitudDireccionService
{
    public function __construct(
        private GestionDireccionesClienteService $gestion,
        private ServicioAuditoriaDireccion $auditoria,
    ) {}

    /**
     * @param  array{usuario_id: int, correcciones?: array<string, mixed>, notas?: string|null}  $contexto
     */
    public function ejecutar(SolicitudDireccion $solicitud, array $contexto): ClienteDireccion
    {
        return DB::transaction(function () use ($solicitud, $contexto) {
            $solicitud = SolicitudDireccion::query()->lockForUpdate()->findOrFail($solicitud->id);

            if (! in_array($solicitud->estado, [
                SolicitudDireccion::ESTADO_PENDING,
                SolicitudDireccion::ESTADO_VERIFIED,
                SolicitudDireccion::ESTADO_POSSIBLE_DUPLICATE,
                SolicitudDireccion::ESTADO_IDENTITY_REVIEW,
                SolicitudDireccion::ESTADO_REQUIRES_CORRECTION,
            ], true)) {
                throw new \RuntimeException('La solicitud no puede aprobarse en su estado actual.');
            }

            if (! $solicitud->cliente_coincidente_id) {
                throw new \RuntimeException('Debe vincular un cliente antes de aprobar.');
            }

            $datos = array_merge(
                $solicitud->datos_solicitados_json ?? [],
                $contexto['correcciones'] ?? []
            );

            if (empty($datos['nombre_destinatario'])) {
                $datos['nombre_destinatario'] = $solicitud->nombre_declarado ?? 'Sin nombre';
            }

            $ctx = [
                'usuario_id' => $contexto['usuario_id'],
                'origen' => ClienteDireccion::ORIGEN_PUBLIC_FORM,
                'solicitud_id' => $solicitud->id,
                'verificar' => true,
            ];

            $direccion = match ($solicitud->accion_solicitada) {
                SolicitudDireccion::ACCION_PRIMERA => $this->gestion->crearPrimeraDireccion(
                    $solicitud->cliente_coincidente_id,
                    $datos,
                    $ctx
                ),
                SolicitudDireccion::ACCION_ACTUALIZAR => $this->aprobarActualizacion($solicitud, $datos, $ctx),
                default => $this->gestion->crearDireccionAdicional(
                    $solicitud->cliente_coincidente_id,
                    $datos,
                    $ctx
                ),
            };

            $estadoRemision = $solicitud->anexa_remision && $solicitud->archivo_remision
                ? SolicitudDireccion::REMISION_PENDING_ORDER_LINK
                : $solicitud->estado_remision;

            $solicitud->update([
                'estado' => SolicitudDireccion::ESTADO_APPROVED,
                'notas_validacion' => $contexto['notas'] ?? $solicitud->notas_validacion,
                'revisada_por' => $contexto['usuario_id'],
                'revisada_en' => now(),
                'estado_remision' => $estadoRemision,
                'direccion_seleccionada_id' => $direccion->id,
            ]);

            $this->auditoria->ejecutar(
                $solicitud->cliente_coincidente_id,
                'aprobar_solicitud',
                $contexto['usuario_id'],
                $direccion->id,
                $solicitud->id,
                null,
                ['folio' => $solicitud->folio],
                'auxiliar',
            );

            return $direccion;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{usuario_id?: int|null, origen?: string|null, solicitud_id?: int|null, verificar?: bool}  $ctx
     */
    private function aprobarActualizacion(SolicitudDireccion $solicitud, array $datos, array $ctx): ClienteDireccion
    {
        if (! $solicitud->direccion_seleccionada_id) {
            throw new \RuntimeException('La solicitud de actualización requiere una dirección seleccionada.');
        }

        $nueva = $this->gestion->crearNuevaVersion($solicitud->direccion_seleccionada_id, $datos, $ctx);

        return $this->gestion->verificar($nueva->id, $ctx);
    }
}
