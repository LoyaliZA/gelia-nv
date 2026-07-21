<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use Illuminate\Support\Facades\DB;

class ReportarErrorDatosPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  list<string>  $campos
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, array $campos, string $detalle = ''): PedidoBma
    {
        $campos = CamposIncorrectosPedidoBma::filtrar($campos);
        $detalle = trim($detalle);

        if ($campos === [] && $detalle === '') {
            throw new \InvalidArgumentException('Seleccione al menos un dato incorrecto o describa el error.');
        }

        if ($campos === []) {
            throw new \InvalidArgumentException('Seleccione al menos un dato incorrecto.');
        }

        $fase = $pedido->estatus?->fase_ciclo;
        $fasesPermitidas = [
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
        ];

        if (! in_array($fase, $fasesPermitidas, true)) {
            throw new \RuntimeException('Solo se puede reportar error de datos en pedidos en CEDIS, pendientes de guía o de envío.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $campos, $detalle) {
            $estatusAnterior = $pedido->estatus;
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA);

            if (! $estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus RECHAZADO_VENDEDORA.');
            }

            $etiquetas = array_map(
                fn (string $k) => CamposIncorrectosPedidoBma::ETIQUETAS[$k] ?? $k,
                $campos
            );
            $resumenCampos = implode(', ', $etiquetas);
            $comentario = "Error de datos reportado: {$resumenCampos}";
            if ($detalle !== '') {
                $comentario .= ". Detalle: {$detalle}";
            }

            $attrs = [
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'campos_incorrectos' => $campos,
                'detalle_error_datos' => $detalle !== '' ? $detalle : null,
                'error_datos_at' => now(),
                'error_datos_por_id' => $usuarioId,
                'motivo_rechazo' => $comentario,
            ];

            if (CamposIncorrectosPedidoBma::invalidanGuia($campos) && ! empty($pedido->numero_rastreo)) {
                $guias = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->get();
                foreach ($guias as $guia) {
                    $guia->update([
                        'nombre_original' => '[INVALIDADA] '.($guia->nombre_original ?? 'guia.pdf'),
                    ]);
                }
                $attrs['numero_rastreo'] = null;
                $attrs['guia_subida_at'] = null;
                $attrs['guia_retraso'] = false;
                $attrs['guia_corregida_at'] = null;
                $attrs['guia_corregida_por_id'] = null;
            }

            $pedido->update($attrs);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                $comentario
            );

            $pedido = $pedido->fresh([
                'cliente', 'estatus', 'vendedor', 'documentos', 'paqueteria', 'direccionVigente',
            ]);

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_error_datos',
                "Error de datos: {$resumenCampos}. No enviar hasta corregir.".($detalle !== '' ? " {$detalle}" : ''),
                [
                    'control_pedidos.cedis',
                    'control_pedidos.auditar',
                    'control_pedidos.delegado',
                ],
                $usuarioId,
                true,
                [
                    'url' => '/control-pedidos?tab=RECHAZADAS&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->id)),
                    'campos_incorrectos' => $campos,
                ]
            );

            return $pedido;
        });
    }
}
