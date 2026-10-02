<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Support\ControlPedidos\PoliticaSalidaPreparacion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class AbrirCustodiaDiferidaPdvService
{
    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        PedidoBmaCumplimientoFisico $cumplimiento,
        User $usuario,
    ): ?ResguardoPdv {
        $politica = PoliticaSalidaPreparacion::desdeTarea($tarea);
        if (! $politica->abreResguardo()) {
            return null;
        }

        if ($cumplimiento->resguardo_pdv_id) {
            return ResguardoPdv::query()->find($cumplimiento->resguardo_pdv_id);
        }

        $tarea->loadMissing('pedido.cliente');
        $pedido = $tarea->pedido;
        $sucursalId = (int) ($pedido?->sucursal_destino_id ?? 0);
        if ($sucursalId < 1) {
            throw ValidationException::withMessages([
                'sucursal' => 'La transferencia diferida necesita sucursal de custodia para abrir el resguardo.',
            ]);
        }

        $existente = ResguardoPdv::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('sucursal_id', $sucursalId)
            ->first();

        $resguardo = $existente ?: $this->crear($tarea, $cumplimiento, $usuario, $sucursalId);
        $cumplimiento->update(['resguardo_pdv_id' => $resguardo->id]);

        return $resguardo;
    }

    private function crear(
        PedidoBmaTareaPreparacion $tarea,
        PedidoBmaCumplimientoFisico $cumplimiento,
        User $usuario,
        int $sucursalId,
    ): ResguardoPdv {
        $pedido = $tarea->pedido;
        $clave = 'pdv:custodia:'.$tarea->id;
        $ahora = now();

        try {
            $resguardo = ResguardoPdv::query()->create([
                'pedido_bma_id' => $pedido->id,
                'cliente_id' => $pedido->cliente_id,
                'sucursal_id' => $sucursalId,
                'almacen_id' => $tarea->almacen_id,
                'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
                'cantidad_bultos_esperada' => 1,
                'recepcion_fisica_at' => $ahora,
                'custodia_confirmada_at' => $ahora,
                'snapshot_folio' => $pedido->folio,
                'snapshot_cliente_nombre' => $pedido->cliente?->nombre,
                'snapshot_json' => [
                    'pedido_bma_id' => (int) $pedido->id,
                    'pedido_bma_tarea_preparacion_id' => (int) $tarea->id,
                    'folio' => $pedido->folio,
                    'handoff' => 'custodia_diferida',
                ],
                'version' => 1,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $recuperado = ResguardoPdv::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('sucursal_id', $sucursalId)
                ->first();
            if ($recuperado) {
                return $recuperado;
            }
            throw $e;
        }

        $bulto = ResguardoPdvBulto::query()->create([
            'resguardo_id' => $resguardo->id,
            'pedido_bma_id' => $pedido->id,
            'folio' => 'APARTADO-'.$tarea->id,
            'tipo' => ResguardoPdvBulto::TIPO_BOLSA,
            'piezas' => max(1, (int) $cumplimiento->cantidad),
            'estado' => ResguardoPdvBulto::ESTADO_EN_CUSTODIA,
            'recepcion_at' => $ahora,
            'recepcion_por_id' => $usuario->id,
            'custodia_at' => $ahora,
            'custodia_por_id' => $usuario->id,
            'version' => 1,
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'bulto_id' => $bulto->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_CUSTODIA_PREPARACION,
            'estado_anterior' => null,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $usuario->id,
            'ocurrido_at' => $ahora,
            'snapshot_json' => [
                'pedido_bma_id' => (int) $pedido->id,
                'pedido_bma_tarea_preparacion_id' => (int) $tarea->id,
                'handoff' => 'custodia_diferida',
            ],
            'idempotency_key' => $clave,
        ]);

        return $resguardo;
    }
}
