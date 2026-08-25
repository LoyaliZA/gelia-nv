<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;

class MarcarEmpacadoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private AvanzarColaErroresPedidoBmaService $colaErroresService,
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
            AssertPedidoNoBloqueadoFase7::assert($miembro);
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

        if ($pedido->tieneErroresGravesBloqueanEmpaque()) {
            throw new \RuntimeException("El pedido {$pedido->folio} tiene errores graves de guía, pago o productos sin resolver.");
        }

        $pedido->assertSinExistenciaAtendida();

        if ($pedido->es_resguardo && !$pedido->esResguardoComplementario()) {
            throw new \RuntimeException("El pedido {$pedido->folio} está en resguardo; libérelo primero.");
        }

        if ($pedido->tieneTrasladoCedisPendiente()) {
            throw new \RuntimeException("El pedido {$pedido->folio} tiene mercancía de Tienda pendiente de recepción en CEDIS.");
        }
    }

    private function empacarUno(PedidoBma $pedido, int $usuarioId, ?string $folioGrupo = null): PedidoBma
    {
        $pedido->loadMissing(['paqueteria', 'origen', 'estatus']);
        $estatusAnterior = $pedido->estatus;

        $tieneGuia = !empty($pedido->numero_rastreo);
        $faseDestino = MaquinaEstadosPedidoBma::faseDestinoEmpaque($pedido);
        MaquinaEstadosPedidoBma::assertTransicion($estatusAnterior?->fase_ciclo, $faseDestino);

        $estatusNuevo = CatalogoEstatusPedido::porFase($faseDestino);

        if (!$estatusNuevo) {
            throw new \RuntimeException("No se encontró el estatus {$faseDestino}.");
        }

        $attrs = [
            'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            'empacado_at' => now(),
            'empacado_por_id' => $usuarioId,
            'detalle_incidencia_empaque' => null,
            'incidencia_empaque_at' => null,
            'incidencia_empaque_por_id' => null,
        ];

        $pedido->update(array_merge($attrs, $this->attrsColaTrasEmpacar($pedido, $usuarioId)));

        $comentario = match ($faseDestino) {
            CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE => 'Pedido empacado; pendiente de guía del cliente.',
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA => 'Pedido empacado; pendiente de captura de guía.',
            default => $tieneGuia
                ? 'Pedido empacado; guía ya asignada, pendiente de recolección.'
                : 'Pedido empacado; pendiente de recolección o envío.',
        };

        if ($folioGrupo) {
            $comentario .= " Grupo {$folioGrupo}.";
        }

        $this->historialService->registrarTransicion(
            $pedido->id,
            $usuarioId,
            $estatusAnterior,
            $estatusNuevo,
            $comentario,
            AccionesHistorialPedidoBma::EMPAQUE
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
        } elseif ($faseDestino === CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE) {
            // La vendedora carga la guía; no notificar a Delegado.
        } else {
            $this->notificarService->ejecutar(
                $pedido,
                'pedido_pendiente_envio',
                'Pedido empacado; pendiente de recolección',
                ['control_pedidos.cedis'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/cedis?tab=PENDIENTES_ENVIO&q='.$q]
            );
        }

        return $pedido;
    }

    /** @return array<string, mixed> */
    private function attrsColaTrasEmpacar(PedidoBma $pedido, int $usuarioId): array
    {
        if (empty($pedido->campos_incorrectos)) {
            return [];
        }

        $restantes = $this->colaErroresService->quitarDueno(
            $pedido,
            \App\Support\ControlPedidos\CamposIncorrectosPedidoBma::DUENO_CEDIS,
            $usuarioId,
            'Error CEDIS resuelto al empacar'
        );

        return $restantes === []
            ? $this->colaErroresService->attrsColaVacia()
            : $this->colaErroresService->attrsColaPendiente($restantes);
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
