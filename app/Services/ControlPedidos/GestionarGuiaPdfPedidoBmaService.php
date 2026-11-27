<?php

namespace App\Services\ControlPedidos;

use App\Events\PedidoBmaActualizado;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\Nacional\BarreraCreditoNacional;
use App\Support\ControlPedidos\Nacional\BarreraRecepcionComplementoNacional;
use App\Support\ControlPedidos\Nacional\CoberturaEnvioNacional;
use App\Support\ControlPedidos\Nacional\GuiasPdfPorCaja;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GestionarGuiaPdfPedidoBmaService
{
    public function __construct(
        private AvanzarColaErroresPedidoBmaService $colaErroresService,
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function subir(PedidoBma $pedido, ?UploadedFile $archivo, int $usuarioId, ?int $cajaId = null, ?int $guiaGeneralId = null): PedidoBma
    {
        \App\Support\ControlPedidos\Nacional\CoberturaEnvioNacional::assertDespacho($pedido);

        if (!$pedido->puedeGestionarGuiaPdf()) {
            throw new \RuntimeException('Solo se puede adjuntar PDF de guía en pedidos pendientes de guía o enviados.');
        }

        if ($archivo !== null && $archivo->getMimeType() !== 'application/pdf'
            && ! str_ends_with(strtolower($archivo->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('La guía debe ser un archivo PDF.');
        }

        if (GuiasPdfPorCaja::aplica($pedido)) {
            return $this->gestionarPorCaja($pedido, $usuarioId, $cajaId, $archivo, $guiaGeneralId);
        }

        if ($cajaId !== null || $guiaGeneralId !== null || $archivo === null) {
            throw new \InvalidArgumentException('Adjunte el PDF de la guía del pedido.');
        }

        return DB::transaction(function () use ($pedido, $archivo, $usuarioId) {
            if (\App\Support\ControlPedidos\Nacional\CapturaNacional::es($pedido)) {
                $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
                \App\Support\ControlPedidos\Nacional\CoberturaEnvioNacional::assertDespacho($pedido);
                if (GuiasPdfPorCaja::aplica($pedido)) {
                    throw new \RuntimeException('Adjunte el PDF desde la caja correspondiente.');
                }
            }

            $this->eliminarGuiasPdf($pedido);

            $ruta = $archivo->store("pedidos_bma/guias/{$pedido->id}", 'public');
            $nombre = $archivo->getClientOriginalName();

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_GUIA,
                'ruta_archivo' => $ruta,
                'nombre_original' => $nombre,
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => 0,
            ]);

            if (! empty($pedido->campos_incorrectos)) {
                $restantes = $this->colaErroresService->quitarCampos(
                    $pedido,
                    ['guia_pdf'],
                    $usuarioId,
                    'PDF de guía cargado'
                );
                $pedido->update(
                    $restantes === []
                        ? $this->colaErroresService->attrsColaVacia()
                        : $this->colaErroresService->attrsColaPendiente($restantes)
                );
            }

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                "PDF de guía adjuntado: {$nombre}",
                AccionesHistorialPedidoBma::CARGA_GUIA_PDF,
                ['ruta' => $ruta, 'nombre' => $nombre]
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'paqueteria', 'vendedor',
            ]);
        });
    }

    public function eliminar(PedidoBma $pedido, int $usuarioId, ?int $cajaId = null): PedidoBma
    {
        if (!$pedido->puedeGestionarGuiaPdf()) {
            throw new \RuntimeException('Solo se puede eliminar el PDF de guía en pedidos pendientes de guía o enviados.');
        }

        if (GuiasPdfPorCaja::aplica($pedido)) {
            return $this->gestionarPorCaja($pedido, $usuarioId, $cajaId);
        }

        if ($cajaId !== null) {
            throw new \InvalidArgumentException('La guía de este pedido se gestiona en el panel de rastreo.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            if (\App\Support\ControlPedidos\Nacional\CapturaNacional::es($pedido)) {
                $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
                if (GuiasPdfPorCaja::aplica($pedido)) {
                    throw new \RuntimeException('Retire el PDF desde la caja correspondiente.');
                }
            }

            $guia = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->first();
            $nombre = $guia?->nombre_original;

            $this->eliminarGuiasPdf($pedido);

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                $nombre ? "PDF de guía eliminado: {$nombre}" : 'PDF de guía eliminado.',
                AccionesHistorialPedidoBma::ELIMINA_GUIA_PDF
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'paqueteria', 'vendedor',
            ]);
        });
    }

    private function gestionarPorCaja(
        PedidoBma $pedido,
        int $usuarioId,
        ?int $cajaId,
        ?UploadedFile $archivo = null,
        ?int $guiaGeneralId = null,
    ): PedidoBma {
        if ($cajaId === null) {
            throw new \InvalidArgumentException('Seleccione la caja a la que corresponde el PDF de guía.');
        }

        $rutaNueva = null;
        try {
            return DB::transaction(function () use ($pedido, $usuarioId, $cajaId, $archivo, $guiaGeneralId, &$rutaNueva) {
                $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
                $caja = $this->cajaEditable($pedido, $cajaId);
                $previa = GuiasPdfPorCaja::vigentes($pedido)->firstWhere('pedido_bma_caja_id', $caja->id);
                $anterior = $guiaGeneralId === null ? null : $pedido->documentos->first(
                    fn (PedidoBmaDocumento $doc) => $doc->id === $guiaGeneralId
                        && $doc->tipo === PedidoBmaDocumento::TIPO_GUIA && $doc->activo && $doc->pedido_bma_caja_id === null
                );
                if ($guiaGeneralId !== null && $anterior === null) {
                    throw new \InvalidArgumentException('La guía general seleccionada ya no está disponible.');
                }
                if ($archivo === null && $anterior === null && $previa === null) {
                    return $pedido;
                }

                $directorio = GuiasPdfPorCaja::DIRECTORIO.$pedido->id;
                if ($archivo !== null || $anterior !== null) {
                    $rutaNueva = $archivo !== null
                        ? $archivo->store($directorio, 'local')
                        : $this->copiarGuiaGeneral($anterior, $directorio);

                    if (! $rutaNueva) {
                        throw new \RuntimeException('No se pudo guardar el PDF. Vuelva a intentar la carga.');
                    }
                }

                foreach (array_filter([$previa, $anterior]) as $doc) {
                    $doc->update(['activo' => false, 'sustituido_at' => now(), 'sustituido_por_id' => $usuarioId]);
                }
                if ($rutaNueva) {
                    $pedido->documentos()->create([
                        'tipo' => PedidoBmaDocumento::TIPO_GUIA,
                        'pedido_bma_caja_id' => $caja->id,
                        'relacion_tipo' => GuiasPdfPorCaja::AVANCE,
                        'ruta_archivo' => $rutaNueva,
                        'nombre_original' => $archivo?->getClientOriginalName() ?? $anterior->nombre_original,
                        'mime_type' => 'application/pdf',
                        'tamano_bytes' => $archivo?->getSize() ?? $anterior?->tamano_bytes,
                        'reemplaza_documento_id' => ($previa ?? $anterior)?->id,
                        'activo' => true,
                        'orden' => $caja->orden,
                    ]);
                }
                if ($previa?->relacion_tipo === GuiasPdfPorCaja::CONFIRMADA) {
                    PedidoBmaActualizado::dispatch($pedido->id, 'actualizado');
                }

                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    ($rutaNueva ? 'Guardó PDF de guía como avance' : 'Retiró PDF de guía').' de la caja '.($caja->orden + 1).'.',
                    $rutaNueva ? AccionesHistorialPedidoBma::CARGA_GUIA_PDF : AccionesHistorialPedidoBma::ELIMINA_GUIA_PDF,
                    ['caja_id' => $caja->id]
                );

                return $pedido->fresh(['documentos', 'cajas', 'estatus']);
            });
        } catch (\Throwable $error) {
            if ($rutaNueva && ! PedidoBmaDocumento::query()->where('ruta_archivo', $rutaNueva)->exists()) {
                Storage::disk('local')->delete($rutaNueva);
            }
            throw $error;
        }
    }

    private function cajaEditable(PedidoBma $pedido, int $cajaId): PedidoBmaCaja
    {
        if (! GuiasPdfPorCaja::aplica($pedido) || ! in_array($pedido->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        ], true) || ! $pedido->puedeGestionarGuiaPdf()) {
            throw new \RuntimeException('El pedido no está disponible para editar guías por caja.');
        }

        BarreraRecepcionComplementoNacional::assert($pedido);
        BarreraCreditoNacional::assertPuedeGuiar($pedido);
        PoliticaResguardoEnvio::assertPuedeDespachar($pedido);
        app(ResguardosEnvioPedido::class)->assertNoSalePorSuCuenta($pedido);
        InstruccionEntrega::assertGuiaNoPosterior($pedido);
        CoberturaEnvioNacional::assertDespacho($pedido);

        $caja = GuiasPdfPorCaja::pendientes($pedido)->firstWhere('id', $cajaId);
        if ($caja === null || $caja->peso_real_kg === null) {
            throw new \RuntimeException('Seleccione una caja activa, con peso y pendiente de recolección de este pedido.');
        }

        return $caja;
    }

    private function copiarGuiaGeneral(PedidoBmaDocumento $guia, string $directorio): string|false
    {
        $origen = Storage::disk($guia->discoArchivo());
        $archivo = $origen->exists($guia->ruta_archivo) ? $origen->readStream($guia->ruta_archivo) : null;
        if (! is_resource($archivo)) {
            return false;
        }

        $ruta = $directorio.'/'.Str::uuid().'.pdf';
        try {
            return Storage::disk('local')->writeStream($ruta, $archivo) ? $ruta : false;
        } finally {
            fclose($archivo);
        }
    }

    private function eliminarGuiasPdf(PedidoBma $pedido): void
    {
        $guias = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->get();

        foreach ($guias as $doc) {
            Storage::disk($doc->discoArchivo())->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
