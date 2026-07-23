<?php

namespace App\Services\Traspasos;

use App\Models\AuditoriaSolicitudTraspaso;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTraspaso;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmarTraspasoCedisService
{
    public function __construct(
        private NotificarTraspasoService $notificar
    ) {}

    public function ejecutar(SolicitudTraspaso $solicitud, User $usuario): SolicitudTraspaso
    {
        return DB::transaction(function () use ($solicitud, $usuario) {
            $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
            $idVerificada = CatalogoEstadoSolicitud::idDe('Verificada');

            if ($idRespondida === null || $idVerificada === null) {
                abort(422, 'Estados de traspaso no configurados.');
            }

            /** @var SolicitudTraspaso $locked */
            $locked = SolicitudTraspaso::query()
                ->whereKey($solicitud->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotente: segundo clic / doble submit no debe fallar.
            if ((int) $locked->catalogo_estado_solicitud_id === $idVerificada) {
                return $locked->fresh(['vendedor', 'estado', 'cliente']);
            }

            if ((int) $locked->catalogo_estado_solicitud_id !== $idRespondida) {
                abort(422, 'Solo se pueden confirmar solicitudes en estado Respondida.');
            }

            $estadoAnterior = $locked->catalogo_estado_solicitud_id;
            $locked->update(['catalogo_estado_solicitud_id' => $idVerificada]);

            AuditoriaSolicitudTraspaso::create([
                'solicitud_traspaso_id' => $locked->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => $estadoAnterior,
                'estado_nuevo_id' => $idVerificada,
                'motivo_reporte' => 'CEDIS confirmó recepción OK.',
            ]);

            $fresh = $locked->fresh(['vendedor', 'estado', 'cliente']);
            $this->notificar->respuesta(
                $fresh,
                'verificada',
                'CEDIS confirmó la recepción del traspaso.',
                $usuario->id
            );

            return $fresh;
        });
    }
}
