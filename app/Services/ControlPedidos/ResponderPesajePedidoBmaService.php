<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use Illuminate\Support\Facades\DB;

class ResponderPesajePedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  list<array{catalogo_tipo_caja_id:int, cantidad:int}>  $lineasCaja
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, float $pesoRealKg, array $lineasCaja): PedidoBma
    {
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeResponderPesaje()) {
            throw new \RuntimeException('Este pedido no está pendiente de pesaje.');
        }

        if ($pesoRealKg < 0) {
            throw new \InvalidArgumentException('El peso real no puede ser negativo.');
        }

        $lineas = $this->normalizarLineas($lineasCaja);
        if ($lineas === []) {
            throw new \InvalidArgumentException('Debe indicar al menos una caja con cantidad mayor a cero.');
        }

        $tipos = CatalogoTipoCajaPedido::query()
            ->whereIn('id', array_column($lineas, 'catalogo_tipo_caja_id'))
            ->get()
            ->keyBy('id');

        if ($tipos->count() !== count(array_unique(array_column($lineas, 'catalogo_tipo_caja_id')))) {
            throw new \InvalidArgumentException('Una o más cajas del catálogo no existen.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $pesoRealKg, $lineas, $tipos) {
            PedidoBmaCaja::where('pedido_bma_id', $pedido->id)->delete();

            $numeroCajas = 0;
            $pesoVolumetrico = 0.0;
            $cajaPrincipalId = null;
            $maxVol = -1.0;
            $orden = 0;

            foreach ($lineas as $linea) {
                $tipo = $tipos->get($linea['catalogo_tipo_caja_id']);
                $cantidad = $linea['cantidad'];
                $volUnit = (float) ($tipo->peso_volumetrico ?? 0);

                PedidoBmaCaja::create([
                    'pedido_bma_id' => $pedido->id,
                    'catalogo_tipo_caja_id' => $tipo->id,
                    'cantidad' => $cantidad,
                    'orden' => $orden++,
                ]);

                $numeroCajas += $cantidad;
                $pesoVolumetrico += $volUnit * $cantidad;

                if ($volUnit > $maxVol) {
                    $maxVol = $volUnit;
                    $cajaPrincipalId = $tipo->id;
                }
            }

            if ($cajaPrincipalId === null) {
                $cajaPrincipalId = $lineas[0]['catalogo_tipo_caja_id'];
            }

            $estatus = $pedido->estatus;

            $pedido->update([
                'peso_real_kg' => $pesoRealKg,
                'peso_volumetrico_kg' => round($pesoVolumetrico, 4),
                'peso_cobrado_guia_kg' => PedidoBma::calcularPesoCobradoGuia($pesoRealKg, $pesoVolumetrico),
                'numero_cajas' => $numeroCajas,
                'catalogo_tipo_caja_id' => $cajaPrincipalId,
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
                'pesaje_respondido_at' => now(),
                'pesaje_respondido_por_id' => $usuarioId,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatus->id,
                sprintf(
                    'Pesaje CEDIS respondido: %.4f kg, %d caja(s).',
                    $pesoRealKg,
                    $numeroCajas
                )
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_pesaje_listo',
                'CEDIS respondió el pesaje de tu pedido',
                [],
                $usuarioId,
                true,
                ['url' => '/control-pedidos']
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'tipoCaja',
                'pesajeRespondidoPor',
            ]);
        });
    }

    /**
     * @param  list<array{catalogo_tipo_caja_id?:mixed, cantidad?:mixed}>  $lineasCaja
     * @return list<array{catalogo_tipo_caja_id:int, cantidad:int}>
     */
    private function normalizarLineas(array $lineasCaja): array
    {
        $out = [];
        foreach ($lineasCaja as $linea) {
            $tipoId = (int) ($linea['catalogo_tipo_caja_id'] ?? 0);
            $cantidad = (int) ($linea['cantidad'] ?? 0);
            if ($tipoId <= 0 || $cantidad <= 0) {
                continue;
            }
            if (isset($out[$tipoId])) {
                $out[$tipoId]['cantidad'] += $cantidad;
            } else {
                $out[$tipoId] = [
                    'catalogo_tipo_caja_id' => $tipoId,
                    'cantidad' => $cantidad,
                ];
            }
        }

        return array_values($out);
    }
}
