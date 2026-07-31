<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class MarcarEmpacadoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'paqueteria', 'origen', 'complementos.estatus', 'complementos.paqueteria', 'complementos.origen']);

        $raiz = $pedido->raizEmpaque()->loadMissing([
            'estatus', 'paqueteria', 'origen',
            'complementos.estatus', 'complementos.paqueteria', 'complementos.origen',
        ]);

        $grupo = collect([$raiz])->merge($raiz->complementos ?? []);

        foreach ($grupo as $miembro) {
            if (!$miembro->esGestionablePorCedis()) {
                continue;
            }
            $this->assertPuedeEmpacar($miembro);
        }

        $aEmpacar = $grupo->filter(fn (PedidoBma $p) => $p->esGestionablePorCedis() && $p->puedeMarcarEmpacado());

        if ($aEmpacar->isEmpty()) {
            throw new \RuntimeException('No hay pedidos del grupo listos para empacar en CEDIS.');
        }

        foreach ($aEmpacar as $miembro) {
            $this->assertPuedeEmpacar($miembro);
        }

        return DB::transaction(function () use ($aEmpacar, $raiz, $usuarioId) {
            foreach ($aEmpacar as $miembro) {
                $this->empacarUno(
                    $miembro->loadMissing(['estatus', 'paqueteria', 'origen']),
                    $usuarioId,
                    $raiz->esPrincipalConComplementos() ? $raiz->folio : null
                );
            }

            return $raiz->fresh($this->relacionesFresh());
        });
    }

    private function assertPuedeEmpacar(PedidoBma $pedido): void
    {
        if (!$pedido->esGestionablePorCedis()) {
            throw new \RuntimeException("El pedido {$pedido->folio} no está en la bandeja de CEDIS.");
        }

        if (!$pedido->puedeMarcarEmpacado()) {
            throw new \RuntimeException("El pedido {$pedido->folio} no puede marcarse como empacado.");
        }

        if (!$pedido->tienePagoValidado() || !$pedido->tieneRemision()) {
            throw new \RuntimeException("El pedido {$pedido->folio} debe tener pago validado y remisión adjunta.");
        }

        if ($pedido->es_resguardo && !$pedido->esResguardoComplementario()) {
            throw new \RuntimeException("El pedido {$pedido->folio} está en resguardo; libérelo primero.");
        }
    }

    private function empacarUno(PedidoBma $pedido, int $usuarioId, ?string $folioGrupo = null): PedidoBma
    {
        $pedido->loadMissing(['paqueteria', 'origen', 'estatus']);
        $estatusAnterior = $pedido->estatus;

        $tieneGuia = !empty($pedido->numero_rastreo);
        $faseDestino = (!$pedido->ofreceRastreo() || $tieneGuia)
            ? CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO
            : CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA;

        $estatusNuevo = CatalogoEstatusPedido::porFase($faseDestino);

        if (!$estatusNuevo) {
            throw new \RuntimeException("No se encontró el estatus {$faseDestino}.");
        }

        $pedido->update([
            'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            'empacado_at' => now(),
            'empacado_por_id' => $usuarioId,
            'detalle_incidencia_empaque' => null,
            'incidencia_empaque_at' => null,
            'incidencia_empaque_por_id' => null,
        ]);

        $comentario = $faseDestino === CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA
            ? 'Pedido empacado; pendiente de captura de guía.'
            : ($tieneGuia
                ? 'Pedido empacado; guía ya asignada, pendiente de envío.'
                : 'Pedido empacado; pendiente de envío.');

        if ($folioGrupo) {
            $comentario .= " Grupo {$folioGrupo}.";
        }

        $this->historialService->registrarTransicion(
            $pedido->id,
            $usuarioId,
            $estatusAnterior,
            $estatusNuevo,
            $comentario
        );

        $pedido = $pedido->fresh($this->relacionesFresh());

        $q = urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id));

        if ($faseDestino === CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA) {
            $this->notificarService->ejecutar(
                $pedido,
                'pedido_pendiente_guia',
                'Pedido empacado; pendiente de captura de guía',
                ['control_pedidos.delegado'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/delegado?tab=PENDIENTES_GUIA&q='.$q]
            );
        } else {
            $this->notificarService->ejecutar(
                $pedido,
                'pedido_pendiente_envio',
                'Pedido empacado; pendiente de envío',
                ['control_pedidos.cedis'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/cedis?tab=PENDIENTES_ENVIO&q='.$q]
            );
        }

        return $pedido;
    }

    /** @return list<string> */
    private function relacionesFresh(): array
    {
        return [
            'cliente', 'estatus', 'documentos', 'almacen', 'origen',
            'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'incidenciaEmpaquePor',
            'complementos.documentos', 'complementos.estatus', 'complementos.cliente',
            'principal', 'vendedor',
        ];
    }
}
