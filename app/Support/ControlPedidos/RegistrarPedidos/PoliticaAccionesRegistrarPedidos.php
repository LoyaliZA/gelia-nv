<?php

namespace App\Support\ControlPedidos\RegistrarPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;

/**
 * Acciones de la captura inicial de Registrar pedidos.
 * Guardar borrador no crea tarea. Solicitar solo cuando el receptor ya está definido.
 */
final class PoliticaAccionesRegistrarPedidos
{
    public const PERMISO_MD = 'control_pedidos.tienda.md';

    public const MENSAJE_ARCHIVO = 'Adjunta el PDF o la imagen del pedido.';

    public const MENSAJE_SABADO = 'Mismo día no está habilitado para esta fecha.';

    public const MENSAJE_FACULTAD = 'Este departamento no tiene la facultad de Mismo día (Tienda MD).';

    public const MENSAJE_COMBINACION = 'Mismo día (Tienda MD) solo acompaña a la transferencia.';

    /**
     * @param  array{
     *   modalidad?: string,
     *   tiene_cliente?: bool,
     *   tiene_departamento?: bool,
     *   tiene_folio?: bool,
     *   tiene_archivo?: bool,
     *   area?: string,
     *   receptor_definido?: bool,
     *   enviada?: bool,
     *   motivo_receptor?: ?string
     * }  $entrada
     * @return array{
     *   guardar: array{habilitada: bool, motivo: ?string, etiqueta: string},
     *   principal: array{habilitada: bool, motivo: ?string, etiqueta: string, espera: ?string}
     * }
     */
    public function evaluar(array $entrada): array
    {
        $modalidad = (string) ($entrada['modalidad'] ?? '');
        $area = (string) ($entrada['area'] ?? '');
        $etiqueta = DestinoRevisionRegistrarPedidos::etiqueta($area);
        $motivo = null;
        $espera = null;
        $enviada = (bool) ($entrada['enviada'] ?? false);

        if ($modalidad === 'tienda') {
            if (! ($entrada['tiene_cliente'] ?? false)) {
                $motivo = 'Indica el número de cliente.';
            } elseif (! ($entrada['tiene_departamento'] ?? false)) {
                $motivo = 'Indica el departamento.';
            } elseif (! ($entrada['tiene_folio'] ?? false)) {
                $motivo = 'Indica el folio del pedido.';
            } elseif (! ($entrada['tiene_archivo'] ?? false)) {
                $motivo = self::MENSAJE_ARCHIVO;
            } elseif (! in_array($area, [
                DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA,
                DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS,
            ], true)) {
                $motivo = DestinoRevisionRegistrarPedidos::MENSAJE_AREA;
            } elseif (! ($entrada['receptor_definido'] ?? false)) {
                $motivo = $entrada['motivo_receptor'] ?? DestinoRevisionRegistrarPedidos::MENSAJE_ALMACEN;
            } elseif ($enviada) {
                $espera = DestinoRevisionRegistrarPedidos::espera($area, null);
            }
        }

        return [
            'guardar' => [
                'habilitada' => true,
                'motivo' => null,
                'etiqueta' => 'Guardar borrador',
            ],
            'principal' => [
                'habilitada' => $modalidad === 'tienda' && $motivo === null && ! $enviada,
                'motivo' => $motivo,
                'etiqueta' => $etiqueta,
                'espera' => $espera,
            ],
        ];
    }

    /**
     * Regla de una etapa posterior. La captura inicial no la muestra ni la confirma.
     *
     * @param  array{cobertura?: string, atencion?: string, facultad?: bool, sabado?: bool}  $entrada
     * @return array{estado: string, confirmada: bool, motivo: ?string}
     */
    public function evaluarAtencion(array $entrada): array
    {
        $cobertura = (string) ($entrada['cobertura'] ?? '');
        $quiereMd = ($entrada['atencion'] ?? '') === 'prevista';
        $facultad = (bool) ($entrada['facultad'] ?? false);
        $sabado = (bool) ($entrada['sabado'] ?? false);

        $motivo = null;
        if ($quiereMd && $cobertura !== 'transferencia') {
            $motivo = self::MENSAJE_COMBINACION;
        } elseif ($quiereMd && $sabado) {
            $motivo = self::MENSAJE_SABADO;
        } elseif ($quiereMd && ! $facultad) {
            $motivo = self::MENSAJE_FACULTAD;
        }

        $estado = 'no';
        if ($quiereMd && $motivo === null) {
            $estado = 'prevista';
        } elseif ($quiereMd) {
            $estado = 'bloqueada';
        }

        return [
            'estado' => $estado,
            'confirmada' => false,
            'motivo' => $motivo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function desdePedido(PedidoBma $pedido, User $usuario): array
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $modalidad = (string) ($json['modalidad_entrega'] ?? '');
        if ($modalidad === '' && self::coberturaDeCodigo($json['codigo_modalidad'] ?? null) !== '') {
            $modalidad = 'tienda';
        }

        $departamento = $pedido->departamento_operacion_id
            ?: \App\Support\ControlPedidos\ContextoDepartamentoPedido::dePedido($pedido);
        $area = DestinoRevisionRegistrarPedidos::areaGuardada($pedido);
        $almacenId = isset($json['almacen_revision_id']) ? (int) $json['almacen_revision_id'] : null;
        $receptor = ['definido' => false, 'motivo' => null];
        if ($area === DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS) {
            $receptor = ['definido' => true, 'motivo' => null];
        } elseif ($area === DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA) {
            $receptor = DestinoRevisionRegistrarPedidos::resolverAlmacen(
                app(DestinoRevisionRegistrarPedidos::class)->almacenesTienda()->pluck('id')->all(),
                $almacenId > 0 ? $almacenId : null
            );
        }

        return $this->evaluar([
            'modalidad' => $modalidad,
            'tiene_cliente' => (bool) $pedido->cliente_id,
            'tiene_departamento' => (bool) $departamento,
            'tiene_folio' => trim((string) $pedido->folio_remision) !== '',
            'tiene_archivo' => $pedido->documentos()
                ->whereIn('tipo', [
                    PedidoBmaDocumento::TIPO_PDF_PEDIDO,
                    PedidoBmaDocumento::TIPO_COTIZACION,
                ])
                ->exists(),
            'area' => $area,
            'receptor_definido' => $receptor['definido'],
            'motivo_receptor' => $receptor['motivo'],
            'enviada' => DestinoRevisionRegistrarPedidos::yaEnviada($pedido),
        ]);
    }

    public static function coberturaDeCodigo(mixed $codigo): string
    {
        return match ($codigo) {
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA => 'apartado',
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA => 'transferencia',
            CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO => 'credito',
            default => '',
        };
    }

    public static function codigoDeCobertura(string $cobertura): ?string
    {
        return match ($cobertura) {
            'apartado' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA,
            'transferencia' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA,
            'credito' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO,
            default => null,
        };
    }
}
