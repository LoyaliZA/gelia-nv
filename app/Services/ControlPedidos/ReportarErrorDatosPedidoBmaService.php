<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        ];

        if (! in_array($fase, $fasesPermitidas, true)) {
            throw new \RuntimeException('Solo se puede reportar error de datos en pedidos pendientes de auxiliar, en CEDIS, pendientes de guía o de envío.');
        }

        $duenoActivo = CamposIncorrectosPedidoBma::duenoActivo($campos);
        if ($duenoActivo === null) {
            throw new \InvalidArgumentException('Seleccione al menos un dato incorrecto.');
        }

        // La auxiliar no se reporta a sí misma: remisión se corrige en su bandeja, no vía este flujo.
        if ($duenoActivo === CamposIncorrectosPedidoBma::DUENO_AUXILIAR
            && $fase === CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR) {
            throw new \InvalidArgumentException('Seleccione datos de la vendedora o de guía. La remisión se corrige aquí mismo en auditoría.');
        }

        $destino = CamposIncorrectosPedidoBma::destinoPara($duenoActivo);
        $faseDestino = $destino['fase'];
        // Sin empaque aún no puede vivir en PENDIENTE_DE_GUIA: se queda en CEDIS con la cola marcada.
        if ($duenoActivo === CamposIncorrectosPedidoBma::DUENO_GUIAS && $pedido->empacado_at === null) {
            $faseDestino = CatalogoEstatusPedido::FASE_EN_CEDIS;
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $campos, $detalle, $duenoActivo, $destino, $faseDestino) {
            $estatusAnterior = $pedido->estatus;
            $estatusNuevo = CatalogoEstatusPedido::porFase($faseDestino);

            if (! $estatusNuevo) {
                throw new \RuntimeException("No se encontró el estatus {$faseDestino}.");
            }

            $etiquetas = CamposIncorrectosPedidoBma::etiquetasDe($campos);
            $resumenCampos = implode(', ', $etiquetas);
            $duenos = CamposIncorrectosPedidoBma::duenosEnCola($campos);
            $colaPendiente = array_slice($duenos, 1);
            $comentario = "Error de datos reportado ({$destino['etiqueta']}): {$resumenCampos}";
            if ($detalle !== '') {
                $comentario .= ". Detalle: {$detalle}";
            }
            if ($colaPendiente !== []) {
                $etiquetasCola = array_map(
                    fn (string $d) => CamposIncorrectosPedidoBma::destinoPara($d)['etiqueta'],
                    $colaPendiente
                );
                $comentario .= '. Pendiente después: '.implode(', ', $etiquetasCola);
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

            if (CamposIncorrectosPedidoBma::invalidanRemision($campos)
                || $duenoActivo === CamposIncorrectosPedidoBma::DUENO_VENDEDORA) {
                // Al devolver a vendedora (como el rechazo clásico) hay que revalidar pago/remisión.
                $this->eliminarRemisiones($pedido);
                $attrs['pago_validado_at'] = null;
                $attrs['pago_validado_por_id'] = null;
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

            $activos = CamposIncorrectosPedidoBma::camposDeDueno($campos, $duenoActivo);
            $resumenActivo = implode(', ', CamposIncorrectosPedidoBma::etiquetasDe($activos));
            $q = urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id));

            $this->notificarService->ejecutar(
                $pedido,
                $destino['tipo_alerta'],
                $this->mensajeAlerta($duenoActivo, $resumenActivo, $detalle),
                $destino['permisos'],
                $usuarioId,
                $destino['incluir_vendedora'],
                [
                    'url' => $destino['url_path'].'&q='.$q,
                    'campos_incorrectos' => $campos,
                ]
            );

            return $pedido;
        });
    }

    private function mensajeAlerta(string $dueno, string $resumen, string $detalle): string
    {
        $extra = $detalle !== '' ? " {$detalle}" : '';

        return match ($dueno) {
            CamposIncorrectosPedidoBma::DUENO_VENDEDORA => "Error de datos: {$resumen}. Corrija y reenvíe.{$extra}",
            CamposIncorrectosPedidoBma::DUENO_AUXILIAR => "Error de remisión: {$resumen}. Corrija antes de aprobar.{$extra}",
            CamposIncorrectosPedidoBma::DUENO_GUIAS => "Error de guía grave: {$resumen}. No enviar hasta corregir.{$extra}",
            default => "Error de datos: {$resumen}.{$extra}",
        };
    }

    private function eliminarRemisiones(PedidoBma $pedido): void
    {
        $remisiones = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->get();

        foreach ($remisiones as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
