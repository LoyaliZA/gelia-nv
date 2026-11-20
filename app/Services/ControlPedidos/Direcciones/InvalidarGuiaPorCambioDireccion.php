<?php

namespace App\Services\ControlPedidos\Direcciones;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;

class InvalidarGuiaPorCambioDireccion
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $motivo): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $usuarioId, $motivo) {
            $estatusAnterior = $pedido->estatus;
            $rastreoAnterior = $pedido->numero_rastreo;

            $cajas = $pedido->cajas()->get()->keyBy('id');
            $guias = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->get();
            foreach ($guias as $guia) {
                $caja = $guia->pedido_bma_caja_id ? $cajas->get($guia->pedido_bma_caja_id) : null;
                if ($caja instanceof PedidoBmaCaja && $caja->estaRecolectada()) {
                    continue;
                }
                $nombre = (string) ($guia->getRawOriginal('nombre_original') ?: 'guia.pdf');
                if (! str_starts_with($nombre, '[INVALIDADA] ')) {
                    $nombre = '[INVALIDADA] '.$nombre;
                }
                // El archivo queda en disco. activo=false lo saca de la guía vigente.
                $guia->update([
                    'nombre_original' => $nombre,
                    'activo' => false,
                    'sustituido_at' => now(),
                    'sustituido_por_id' => $usuarioId,
                ]);
            }

            foreach ($cajas as $caja) {
                if ($caja->estaActiva() && ! $caja->estaRecolectada()) {
                    $caja->update(['numero_rastreo' => null]);
                }
            }

            MaquinaEstadosPedidoBma::assertTransicion(
                $estatusAnterior?->fase_ciclo,
                CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA
            );
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA)
                ?? $estatusAnterior;

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json['invalidacion_guia'] = [
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
                'at' => now()->toIso8601String(),
                'cancelacion_paqueteria_confirmada' => false,
            ];
            $pedido->update([
                'numero_rastreo' => null,
                'guia_subida_at' => null,
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'solicitud_preparacion_json' => $json,
            ]);

            $this->historial->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                "Guía invalidada por cambio de dirección. Rastreo anterior: {$rastreoAnterior}. Motivo: {$motivo}",
                AccionesHistorialPedidoBma::INVALIDAR_GUIA
            );

            $pedido = $pedido->fresh(['cliente', 'estatus', 'vendedor', 'documentos']);

            if ($estatusNuevo->fase_ciclo === CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA) {
                $this->notificarService->ejecutar(
                    $pedido,
                    'pedido_pendiente_guia',
                    'Guía invalidada por cambio de dirección; pendiente de nueva guía',
                    ['control_pedidos.delegado'],
                    $usuarioId,
                    false,
                    ['url' => '/control-pedidos/delegado?tab=PENDIENTES_GUIA&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
                );
            }

            return $pedido;
        });
    }

    public function confirmarCancelacionExterna(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        if (! is_array($json['invalidacion_guia'] ?? null)) {
            throw new \RuntimeException('No hay una guía invalidada para registrar la cancelación de la paquetería.');
        }

        $json['invalidacion_guia']['cancelacion_paqueteria_confirmada'] = true;
        $json['invalidacion_guia']['cancelacion_confirmada_por_id'] = $usuarioId;
        $json['invalidacion_guia']['cancelacion_confirmada_at'] = now()->toIso8601String();
        $pedido->update(['solicitud_preparacion_json' => $json]);

        return $pedido->fresh();
    }
}
