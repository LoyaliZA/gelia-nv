<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EstadoRecepcionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorBultosEmpaqueCedisPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorPedidoRevisionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorRetiroPedidoResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConsultaFormularioRecepcionFisicaPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly SincronizarCantidadBultosEsperadaResguardoPdvService $sincronizarCantidadBultos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function obtener(User $user, ResguardoPdv $resguardo): array
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE);

        $activaId = $this->alcance->sucursalActivaId($user);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }

        if ((int) $resguardo->cantidad_bultos_esperada < 1) {
            $resguardo = $this->sincronizarCantidadBultos->ejecutar($resguardo);
        }

        $resguardo->load([
            'sucursal:id,nombre',
            'cliente:id,numero_cliente',
            'pedido:id,folio,folio_remision,envia_a_otra_persona,envia_otra_persona,estado_fisico_general,comentario_fisico_general,tiene_observaciones_fisicas,cantidad_piezas',
            'pedido.revisionesProducto',
            'pedido.documentos' => fn ($q) => $q->vigente()->orderBy('orden')->orderBy('id'),
            'pedido.cajas' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
            'pedido.bultosEmpaque.documentos',
            'bultos' => fn ($q) => $q->orderBy('folio')->orderBy('id'),
        ]);

        return [
            'resguardo' => $this->serializarResguardo($resguardo),
            'catalogos' => [
                'estados' => EtiquetasResguardoPdv::estados(),
            ],
            'admite_recepcion' => EstadoRecepcionResguardoPdv::admiteRecepcion($resguardo),
            'motivo_no_recepcion' => EstadoRecepcionResguardoPdv::motivoNoRecepcion($resguardo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarResguardo(ResguardoPdv $resguardo): array
    {
        $retiro = SerializadorRetiroPedidoResguardoPdv::desdeResguardo($resguardo);

        return [
            'id' => $resguardo->id,
            'estado' => $resguardo->estado,
            'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
            'version' => (int) $resguardo->version,
            'snapshot_folio' => $resguardo->snapshot_folio,
            'snapshot_cliente_nombre' => $resguardo->snapshot_cliente_nombre,
            'referencia_cliente' => $this->referenciaCliente($resguardo),
            'cantidad_bultos_esperada' => $resguardo->cantidad_bultos_esperada,
            'cantidad_bultos_recibida' => EstadoRecepcionResguardoPdv::cantidadRecibida($resguardo),
            'cantidad_bultos_pendiente' => EstadoRecepcionResguardoPdv::cantidadPendiente($resguardo),
            'recepcion_completa' => EstadoRecepcionResguardoPdv::recepcionCompleta($resguardo),
            'salida_cedis_at' => $resguardo->salida_cedis_at?->toIso8601String(),
            'envia_a_otra_persona' => $retiro['envia_a_otra_persona'],
            'envia_otra_persona' => $retiro['envia_otra_persona'],
            'etiqueta_retiro' => $retiro['etiqueta_retiro'],
            'sucursal' => $resguardo->sucursal ? [
                'id' => $resguardo->sucursal->id,
                'nombre' => $resguardo->sucursal->nombre,
            ] : null,
            'pedido' => $resguardo->pedido ? [
                'id' => $resguardo->pedido->id,
                'folio' => $resguardo->pedido->folio,
                'folio_remision' => $resguardo->pedido->folio_remision,
                'envia_a_otra_persona' => (bool) $resguardo->pedido->envia_a_otra_persona,
                'envia_otra_persona' => $resguardo->pedido->envia_otra_persona,
            ] : null,
            'bultos_empaque_cedis' => SerializadorBultosEmpaqueCedisPdv::desdePedido($resguardo->pedido),
            'pedido_revision' => SerializadorPedidoRevisionResguardoPdv::desdePedido($resguardo->pedido),
        ];
    }

    private function referenciaCliente(ResguardoPdv $resguardo): string
    {
        $numero = $resguardo->cliente?->numero_cliente;

        if ($numero !== null && $numero !== '') {
            return '#'.(string) $numero;
        }

        return $resguardo->snapshot_folio ?: 'Sin referencia';
    }
}
