<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaError;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;

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
        $pedido->loadMissing('origen');
        $campos = CamposIncorrectosPedidoBma::filtrarParaPedido($campos, $pedido);
        $detalle = trim($detalle);

        if ($campos === [] && $detalle === '') {
            throw new \InvalidArgumentException('Seleccione al menos un dato incorrecto o describa el error.');
        }

        if ($campos === []) {
            throw new \InvalidArgumentException('Seleccione al menos un dato incorrecto válido para el origen de este pedido.');
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
            throw new \InvalidArgumentException('Seleccione datos de la vendedora, CEDIS o de guía. La remisión se corrige aquí mismo en auditoría.');
        }

        $destino = CamposIncorrectosPedidoBma::destinoPara($duenoActivo);
        $faseDestino = $destino['fase'];
        if ($duenoActivo === CamposIncorrectosPedidoBma::DUENO_GUIAS && $pedido->empacado_at === null) {
            $faseDestino = CatalogoEstatusPedido::FASE_EN_CEDIS;
        }

        $teniaGuia = ! empty($pedido->numero_rastreo)
            || $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->exists();

        return DB::transaction(function () use (
            $pedido, $usuarioId, $campos, $detalle, $duenoActivo, $destino, $faseDestino, $teniaGuia
        ) {
            $estatusAnterior = $pedido->estatus;
            MaquinaEstadosPedidoBma::assertTransicion($estatusAnterior?->fase_ciclo, $faseDestino);
            $estatusNuevo = CatalogoEstatusPedido::porFase($faseDestino);

            if (! $estatusNuevo) {
                throw new \RuntimeException("No se encontró el estatus {$faseDestino}.");
            }

            $etiquetas = CamposIncorrectosPedidoBma::etiquetasDe($campos);
            $resumenCampos = implode(', ', $etiquetas);
            $duenos = CamposIncorrectosPedidoBma::duenosEnCola($campos);
            $colaPendiente = array_slice($duenos, 1);
            $comentario = "Error reportado ({$destino['etiqueta']}): {$resumenCampos}";
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

            if ($duenoActivo === CamposIncorrectosPedidoBma::DUENO_CEDIS) {
                $attrs['detalle_incidencia_empaque'] = $detalle !== '' ? $detalle : $resumenCampos;
                $attrs['incidencia_empaque_at'] = now();
                $attrs['incidencia_empaque_por_id'] = $usuarioId;
            }

            $invalidaGuia = CamposIncorrectosPedidoBma::invalidanGuia($campos) && $teniaGuia;
            if ($invalidaGuia) {
                $guias = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->get();
                foreach ($guias as $guia) {
                    $guia->update([
                        'nombre_original' => '[INVALIDADA] '.($guia->nombre_original ?? 'guia.pdf'),
                    ]);
                }
                $attrs['numero_rastreo'] = null;
                $attrs['guia_subida_at'] = null;
                $attrs['guia_corregida_at'] = null;
                $attrs['guia_corregida_por_id'] = null;
            }

            // Error detectado después de generar guía → retraso + aviso CEDIS.
            if ($teniaGuia) {
                $attrs['guia_retraso'] = true;
            }

            if (CamposIncorrectosPedidoBma::invalidanRemision($campos)
                || $duenoActivo === CamposIncorrectosPedidoBma::DUENO_VENDEDORA) {
                $this->eliminarRemisiones($pedido);
                $attrs['pago_validado_at'] = null;
                $attrs['pago_validado_por_id'] = null;
            }

            $pedido->update($attrs);

            $this->registrarBitacora($pedido, $usuarioId, $campos, $detalle);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                $comentario,
                AccionesHistorialPedidoBma::ERROR_DATOS
            );

            $pedido = $pedido->fresh([
                'cliente', 'estatus', 'vendedor', 'documentos', 'paqueteria', 'direccionVigente',
                'errores',
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

            $this->notificarInvolucradosEstado(
                $pedido,
                $usuarioId,
                $duenoActivo,
                $resumenCampos,
                $detalle,
                $q
            );

            if ($teniaGuia) {
                $this->notificarService->ejecutar(
                    $pedido,
                    'pedido_guia_retraso',
                    'Se reportó un error después de generar la guía; el pedido queda con retraso.',
                    ['control_pedidos.cedis'],
                    $usuarioId,
                    false,
                    ['url' => '/control-pedidos/cedis?q='.$q]
                );
            }

            return $pedido;
        });
    }

    /**
     * @param  list<string>  $campos
     */
    private function registrarBitacora(PedidoBma $pedido, int $usuarioId, array $campos, string $detalle): void
    {
        foreach (CamposIncorrectosPedidoBma::duenosEnCola($campos) as $dueno) {
            $camposDueno = CamposIncorrectosPedidoBma::camposDeDueno($campos, $dueno);
            PedidoBmaError::create([
                'pedido_bma_id' => $pedido->id,
                'campos' => $camposDueno,
                'descripcion' => $detalle !== '' ? $detalle : null,
                'reportado_por_id' => $usuarioId,
                'responsable_dueno' => $dueno,
                'responsable_user_id' => $dueno === CamposIncorrectosPedidoBma::DUENO_VENDEDORA
                    ? $pedido->vendedor_id
                    : null,
                'reportado_at' => now(),
                'estatus' => PedidoBmaError::ESTATUS_ABIERTO,
            ]);
        }
    }

    private function notificarInvolucradosEstado(
        PedidoBma $pedido,
        int $usuarioId,
        string $duenoActivo,
        string $resumenCampos,
        string $detalle,
        string $q
    ): void {
        $extra = $detalle !== '' ? " Detalle: {$detalle}" : '';
        $mensaje = "Error reportado (corrige {$duenoActivo}): {$resumenCampos}. Solo el responsable puede corregir.{$extra}";

        $permisos = array_values(array_unique(array_filter([
            'control_pedidos.auditar',
            'control_pedidos.cedis',
            'control_pedidos.delegado',
        ])));

        $destinoActivo = CamposIncorrectosPedidoBma::destinoPara($duenoActivo);
        // Excluir permisos del dueño activo para no duplicar su alerta operativa.
        $permisosInfo = array_values(array_diff($permisos, $destinoActivo['permisos']));

        $incluirVendedora = $duenoActivo !== CamposIncorrectosPedidoBma::DUENO_VENDEDORA;

        if ($permisosInfo === [] && ! $incluirVendedora) {
            return;
        }

        $this->notificarService->ejecutar(
            $pedido,
            'pedido_error_estado',
            $mensaje,
            $permisosInfo,
            $usuarioId,
            $incluirVendedora,
            [
                'url' => '/control-pedidos?q='.$q,
                'solo_informativo' => true,
            ]
        );
    }

    private function mensajeAlerta(string $dueno, string $resumen, string $detalle): string
    {
        $extra = $detalle !== '' ? " {$detalle}" : '';

        return match ($dueno) {
            CamposIncorrectosPedidoBma::DUENO_VENDEDORA => "Error de datos: {$resumen}. Corrija y reenvíe.{$extra}",
            CamposIncorrectosPedidoBma::DUENO_AUXILIAR => "Error de remisión: {$resumen}. Corrija antes de aprobar.{$extra}",
            CamposIncorrectosPedidoBma::DUENO_CEDIS => "Error CEDIS: {$resumen}. Corrija en empaque/pesaje.{$extra}",
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
