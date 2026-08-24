<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;

class MarcarEnviadoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  list<array{id?: int|string, numero_rastreo?: string|null}>|null  $cajasSeleccion
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, ?array $cajasSeleccion = null): PedidoBma
    {
        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO) {
            throw new \RuntimeException('El pedido no está pendiente de recolección o envío.');
        }

        if ($pedido->empacado_at === null) {
            throw new \RuntimeException('El pedido debe estar empacado antes de marcarlo como enviado.');
        }

        $pedido->loadMissing(['paqueteria', 'origen', 'cajas']);

        if ($pedido->ofreceRastreo() && empty($pedido->numero_rastreo)) {
            throw new \RuntimeException('El pedido requiere número de guía antes de marcarlo como enviado.');
        }

        $cajas = $pedido->cajas
            ->filter(fn (PedidoBmaCaja $c) => $c->estaActiva())
            ->sortBy('orden')
            ->values();
        $pendientes = $cajas->filter(fn (PedidoBmaCaja $c) => $c->estaPendiente())->values();
        $seleccion = is_array($cajasSeleccion) ? array_values($cajasSeleccion) : [];

        if ($pendientes->count() > 1 && $seleccion === []) {
            throw new \InvalidArgumentException('Selecciona qué envíos se recolectaron');
        }

        $idsMarcar = [];
        $rastreos = [];
        if ($seleccion === []) {
            $idsMarcar = $pendientes->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            foreach ($seleccion as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    throw new \InvalidArgumentException('Cada envío seleccionado debe indicar su id.');
                }
                $idsMarcar[] = $id;
                if (isset($row['numero_rastreo']) && trim((string) $row['numero_rastreo']) !== '') {
                    $rastreos[$id] = trim((string) $row['numero_rastreo']);
                }
            }
        }

        $idsMarcar = array_values(array_unique($idsMarcar));
        $idsPedido = $cajas->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($idsMarcar as $id) {
            if (! in_array($id, $idsPedido, true)) {
                throw new \InvalidArgumentException('Uno o más envíos no pertenecen a este pedido.');
            }
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $idsMarcar, $rastreos, $cajas) {
            foreach ($idsMarcar as $id) {
                $caja = $cajas->firstWhere('id', $id) ?? PedidoBmaCaja::query()->find($id);
                if (! $caja || (int) $caja->pedido_bma_id !== (int) $pedido->id) {
                    throw new \InvalidArgumentException('Uno o más envíos no pertenecen a este pedido.');
                }
                if ($caja->estaRecolectada()) {
                    continue;
                }
                $attrs = [
                    'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA,
                    'recolectada_at' => now(),
                    'recolectada_por_id' => $usuarioId,
                ];
                if (isset($rastreos[$id])) {
                    $attrs['numero_rastreo'] = $rastreos[$id];
                }
                $caja->update($attrs);
            }

            $quedanPendientes = $pedido->cajas()->activas()->get()->contains(fn (PedidoBmaCaja $c) => $c->estaPendiente());

            if ($quedanPendientes) {
                return $pedido->fresh([
                    'cliente', 'estatus', 'documentos', 'almacen', 'cajas',
                    'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'vendedor',
                ]);
            }

            MaquinaEstadosPedidoBma::assertTransicion(
                $pedido->estatus?->fase_ciclo,
                CatalogoEstatusPedido::FASE_ENVIADO
            );
            $estatusEnviado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENVIADO);

            if (! $estatusEnviado) {
                throw new \RuntimeException('No se encontró el estatus ENVIADO.');
            }

            $estatusAnterior = $pedido->estatus;

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusEnviado->id,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusEnviado,
                'Paquetería recogió el paquete.',
                AccionesHistorialPedidoBma::ENVIO_FINAL
            );

            $pedido = $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'almacen', 'cajas',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'vendedor',
            ]);

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_enviado',
                'Paquetería recogió el paquete',
                [],
                $usuarioId,
                true,
                ['url' => '/control-pedidos?tab=ENVIADOS&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $pedido;
        });
    }
}
