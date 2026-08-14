<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;

class CargarGuiaClientePedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBma $pedido, string $numeroRastreo, UploadedFile $archivo, int $usuarioId): PedidoBma
    {
        $guia = trim($numeroRastreo);

        if ($guia === '') {
            throw new \InvalidArgumentException('El número de guía es obligatorio.');
        }

        $pedido->loadMissing(['estatus']);

        if (! $pedido->puedeCargarGuiaCliente()) {
            throw new \RuntimeException('El pedido no está pendiente de guía del cliente.');
        }

        if ($archivo->getMimeType() !== 'application/pdf' && ! str_ends_with(strtolower($archivo->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('La guía debe ser un archivo PDF.');
        }

        MaquinaEstadosPedidoBma::assertTransicion(
            $pedido->estatus?->fase_ciclo,
            MaquinaEstadosPedidoBma::faseDestinoTrasAsignarGuia()
        );
        $estatusPendienteEnvio = CatalogoEstatusPedido::porFase(MaquinaEstadosPedidoBma::faseDestinoTrasAsignarGuia());

        if (! $estatusPendienteEnvio) {
            throw new \RuntimeException('No se encontró el estatus PENDIENTE_DE_ENVIO.');
        }

        return DB::transaction(function () use ($pedido, $guia, $archivo, $usuarioId, $estatusPendienteEnvio) {
            $estatusAnterior = $pedido->estatus;

            $this->eliminarGuiasPdf($pedido);

            $ruta = $archivo->store("pedidos_bma/guias/{$pedido->id}", 'public');

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_GUIA,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => 0,
            ]);

            $pedido->update([
                'numero_rastreo' => $guia,
                'guia_subida_at' => now(),
                'catalogo_estatus_pedido_id' => $estatusPendienteEnvio->id,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusPendienteEnvio,
                "Guía del cliente cargada: {$guia}. Pendiente de recolección.",
                AccionesHistorialPedidoBma::CARGA_GUIA_CLIENTE
            );

            $pedido = $pedido->fresh([
                'cliente', 'paqueteria', 'estatus', 'vendedor', 'documentos', 'origen',
            ]);

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_guia_asignada',
                "Guía del cliente cargada: {$guia}",
                ['control_pedidos.cedis'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/cedis?tab=PENDIENTES_ENVIO&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $pedido;
        });
    }

    private function eliminarGuiasPdf(PedidoBma $pedido): void
    {
        $guias = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->get();

        foreach ($guias as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
