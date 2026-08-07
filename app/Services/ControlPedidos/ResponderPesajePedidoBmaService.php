<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class ResponderPesajePedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  list<array{
     *   catalogo_tipo_caja_id:int|string,
     *   largo:float|int|string,
     *   ancho:float|int|string,
     *   alto:float|int|string,
     *   peso_real_kg:float|int|string,
     *   peso_volumetrico_kg:float|int|string
     * }>  $lineasCaja
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, array $lineasCaja): PedidoBma
    {
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeResponderPesaje()) {
            throw new \RuntimeException('Este pedido no está pendiente de pesaje.');
        }

        $lineas = $this->normalizarLineas($lineasCaja);
        if ($lineas === []) {
            throw new \InvalidArgumentException('Debe indicar al menos un envío con tipo de caja y pesos.');
        }

        $tipos = CatalogoTipoCajaPedido::query()
            ->whereIn('id', array_column($lineas, 'catalogo_tipo_caja_id'))
            ->get()
            ->keyBy('id');

        if ($tipos->count() !== count(array_unique(array_column($lineas, 'catalogo_tipo_caja_id')))) {
            throw new \InvalidArgumentException('Una o más cajas del catálogo no existen.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $lineas, $tipos) {
            PedidoBmaCaja::where('pedido_bma_id', $pedido->id)->delete();

            $pesoRealTotal = 0.0;
            $pesoVolumetricoTotal = 0.0;
            $pesoCobradoTotal = 0.0;
            $cajaPrincipalId = null;
            $maxVol = -1.0;
            $orden = 0;

            foreach ($lineas as $linea) {
                $tipo = $tipos->get($linea['catalogo_tipo_caja_id']);
                $pesoReal = $linea['peso_real_kg'];
                $pesoVol = $linea['peso_volumetrico_kg'];
                $pesoCobrado = PedidoBma::calcularPesoCobradoGuia($pesoReal, $pesoVol);

                PedidoBmaCaja::create([
                    'pedido_bma_id' => $pedido->id,
                    'catalogo_tipo_caja_id' => $tipo->id,
                    'cantidad' => 1,
                    'orden' => $orden,
                    'largo' => $linea['largo'],
                    'ancho' => $linea['ancho'],
                    'alto' => $linea['alto'],
                    'peso_real_kg' => $pesoReal,
                    'peso_volumetrico_kg' => $pesoVol,
                    'peso_cobrado_kg' => $pesoCobrado,
                    'catalogo_tipo_guia_id' => null,
                ]);

                $pesoRealTotal += $pesoReal;
                $pesoVolumetricoTotal += $pesoVol;
                $pesoCobradoTotal += (float) $pesoCobrado;

                if ($pesoVol > $maxVol) {
                    $maxVol = $pesoVol;
                    $cajaPrincipalId = $tipo->id;
                }

                $orden++;
            }

            if ($cajaPrincipalId === null) {
                $cajaPrincipalId = $lineas[0]['catalogo_tipo_caja_id'];
            }

            $estatus = $pedido->estatus;
            $numeroCajas = count($lineas);

            $pedido->update([
                'peso_real_kg' => round($pesoRealTotal, 4),
                'peso_volumetrico_kg' => round($pesoVolumetricoTotal, 4),
                'peso_cobrado_guia_kg' => round($pesoCobradoTotal, 4),
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
                    'Pesaje CEDIS respondido: %.4f kg cobrados, %d envío(s).',
                    $pesoCobradoTotal,
                    $numeroCajas
                ),
                AccionesHistorialPedidoBma::RESPUESTA_PESAJE
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
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'tipoCaja', 'tipoGuia',
                'pesajeRespondidoPor',
            ]);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lineasCaja
     * @return list<array{
     *   catalogo_tipo_caja_id:int,
     *   largo:float,
     *   ancho:float,
     *   alto:float,
     *   peso_real_kg:float,
     *   peso_volumetrico_kg:float
     * }>
     */
    private function normalizarLineas(array $lineasCaja): array
    {
        $out = [];
        foreach ($lineasCaja as $linea) {
            $tipoId = (int) ($linea['catalogo_tipo_caja_id'] ?? 0);
            $pesoReal = isset($linea['peso_real_kg']) ? (float) $linea['peso_real_kg'] : null;
            $pesoVol = isset($linea['peso_volumetrico_kg']) ? (float) $linea['peso_volumetrico_kg'] : null;
            $largo = isset($linea['largo']) ? (float) $linea['largo'] : null;
            $ancho = isset($linea['ancho']) ? (float) $linea['ancho'] : null;
            $alto = isset($linea['alto']) ? (float) $linea['alto'] : null;

            if ($tipoId <= 0 || $pesoReal === null || $pesoVol === null
                || $largo === null || $ancho === null || $alto === null) {
                continue;
            }
            if ($pesoReal < 0 || $pesoVol < 0 || $largo < 0 || $ancho < 0 || $alto < 0) {
                continue;
            }

            $out[] = [
                'catalogo_tipo_caja_id' => $tipoId,
                'largo' => round($largo, 2),
                'ancho' => round($ancho, 2),
                'alto' => round($alto, 2),
                'peso_real_kg' => round($pesoReal, 4),
                'peso_volumetrico_kg' => round($pesoVol, 4),
            ];
        }

        return $out;
    }
}
