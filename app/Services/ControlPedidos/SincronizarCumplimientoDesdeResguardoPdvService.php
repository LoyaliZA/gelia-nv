<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use Illuminate\Support\Facades\DB;

/**
 * Refleja un hecho ya registrado en PDV. No abre una segunda captura.
 */
class SincronizarCumplimientoDesdeResguardoPdvService
{
    public function __construct(
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private SolicitarDevolucionCumplimientoService $devolucion,
    ) {}

    public function reflejarEntrega(ResguardoPdv $resguardo, ResguardoPdvEntrega $entrega, int $actorId): void
    {
        DB::transaction(function () use ($resguardo, $entrega, $actorId) {
            $cumplimiento = $this->cumplimiento($resguardo);
            if (! $cumplimiento) {
                return;
            }

            $cumplimiento = PedidoBmaCumplimientoFisico::query()->lockForUpdate()->find($cumplimiento->id);
            if (! $cumplimiento) {
                return;
            }

            if ($cumplimiento->estado === PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA) {
                return;
            }

            if ($cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA) {
                return;
            }

            if (! MaquinaEstadosCumplimientoFisico::puedeTransicionar(
                $cumplimiento->estado,
                PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA
            )) {
                return;
            }

            $ahora = $entrega->entregado_at ?? now();
            $cumplimiento->update([
                'estado' => PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA,
                'entregada_at' => $ahora,
                'entregada_por_id' => $actorId,
                'receptor_nombre' => $entrega->nombre_quien_retira,
                'entrega_pdv_id' => $entrega->id,
                'resguardo_pdv_id' => $resguardo->id,
                'version' => $cumplimiento->version + 1,
            ]);

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_ENTREGADA,
                User::query()->find($actorId),
                'entrega-pdv:'.$entrega->id,
                'Entrega registrada en PDV. No se vuelve a capturar en Tienda.',
                [
                    'resguardo_pdv_id' => (int) $resguardo->id,
                    'entrega_pdv_id' => (int) $entrega->id,
                    'receptor' => $entrega->nombre_quien_retira,
                    'entregada_at' => $ahora->toIso8601String(),
                ]
            );
        });
    }

    public function reflejarDevolucion(ResguardoPdv $resguardo, ResguardoPdvEvento $evento, ?int $actorId): void
    {
        $cumplimiento = $this->cumplimiento($resguardo);
        if (! $cumplimiento || ! $cumplimiento->tarea) {
            return;
        }

        if (in_array($cumplimiento->estado, [
            PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA,
            PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL,
            PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
        ], true)) {
            return;
        }

        $this->devolucion->ejecutar(
            $cumplimiento->tarea,
            $actorId ? User::query()->find($actorId) : null,
            'Devolución confirmada en el resguardo de PDV.',
            'devolucion-pdv:'.$evento->id,
            null
        );
    }

    private function cumplimiento(ResguardoPdv $resguardo): ?PedidoBmaCumplimientoFisico
    {
        return PedidoBmaCumplimientoFisico::query()
            ->with('tarea')
            ->where('resguardo_pdv_id', $resguardo->id)
            ->first();
    }
}
