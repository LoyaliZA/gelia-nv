<?php

namespace App\Support\ControlPedidos\RegistrarPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Support\ControlPedidos\AlmacenPdv;
use App\Support\ControlPedidos\InstruccionEntrega;
use Illuminate\Support\Collection;

/** Quién revisa la mercancía de Tienda. No asigna cobertura, crédito ni Mismo día. */
final class DestinoRevisionRegistrarPedidos
{
    public const PREPARACION_TIENDA = 'preparacion_tienda';

    public const CONTROL_PEDIDOS = 'control_pedidos';

    public const MENSAJE_AREA = 'Elige el destino de la revisión.';

    public const MENSAJE_MISMO_DIA = 'Mismo día (T-02.1) no se envía como apartado ni como consulta desde esta captura.';

    public const MENSAJE_ALMACEN = 'Elige la sucursal o el almacén de tienda.';

    public const MENSAJE_SIN_ALMACEN = 'No hay un almacén de tienda para recibir el apartado.';

    public const MENSAJE_ALMACEN_AJENO = 'El almacén indicado no puede recibir este apartado.';

    public const MENSAJE_DESTINO_FIJO = 'La solicitud ya se envió. El destino no cambia desde este guardado.';

    public function __construct(
        private PreparacionTiendaConfig $config,
    ) {}

    /**
     * @return Collection<int, Almacen>
     */
    public function almacenesTienda(): Collection
    {
        $habilitados = $this->config->almacenesHabilitados();
        $consulta = Almacen::query()
            ->where('activo', true)
            ->where('visible_en_pedidos', true)
            ->where(function ($query) {
                $query->where('permite_salida_pedidos', true)
                    ->orWhereNull('permite_salida_pedidos');
            })
            ->where(function ($query) {
                $query->whereRaw('UPPER(TRIM(codigo)) = ?', [AlmacenPdv::CODIGO])
                    ->orWhereHas('tipoAlmacen', function ($tipo) {
                        $tipo->whereRaw('UPPER(TRIM(nombre)) = ?', [AlmacenPdv::TIPO]);
                    });
            })
            ->orderBy('nombre');

        if ($habilitados !== []) {
            $consulta->whereIn('id', $habilitados);
        }

        return $consulta->get(['id', 'nombre', 'codigo', 'sucursal_id']);
    }

    /**
     * @return list<array{id: int, nombre: string, sucursal_id: ?int}>
     */
    public function catalogoTienda(): array
    {
        return $this->almacenesTienda()
            ->map(fn (Almacen $almacen) => [
                'id' => (int) $almacen->id,
                'nombre' => (string) $almacen->nombre,
                'sucursal_id' => $almacen->sucursal_id !== null ? (int) $almacen->sucursal_id : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array{definido: bool, id: ?int, motivo: ?string}
     */
    public static function resolverAlmacen(array $ids, ?int $solicitado): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return ['definido' => false, 'id' => null, 'motivo' => self::MENSAJE_SIN_ALMACEN];
        }

        if (count($ids) === 1) {
            $unico = $ids[0];
            if ($solicitado !== null && $solicitado !== $unico) {
                return ['definido' => false, 'id' => null, 'motivo' => self::MENSAJE_ALMACEN_AJENO];
            }

            return ['definido' => true, 'id' => $unico, 'motivo' => null];
        }

        if ($solicitado === null || ! in_array($solicitado, $ids, true)) {
            return ['definido' => false, 'id' => null, 'motivo' => self::MENSAJE_ALMACEN];
        }

        return ['definido' => true, 'id' => $solicitado, 'motivo' => null];
    }

    public static function etiqueta(string $area): string
    {
        return match ($area) {
            self::PREPARACION_TIENDA => 'Revisar y apartar en tienda',
            self::CONTROL_PEDIDOS => 'Consultar disponibilidad en CEDIS',
            default => 'Solicitar en tienda',
        };
    }

    public static function espera(string $area, ?string $almacen): string
    {
        if ($area === self::PREPARACION_TIENDA) {
            $lugar = $almacen ? ' ('.$almacen.')' : '';

            return 'Enviado a Preparación Tienda'.$lugar.'. Se espera la revisión del archivo. Aún no hay apartado ni disponibilidad confirmada.';
        }

        return 'Enviado a Control Pedidos. Se espera la consulta de disponibilidad. Aún no hay disponibilidad confirmada, traslado, pago ni entrega.';
    }

    public static function yaEnviada(PedidoBma $pedido): bool
    {
        $area = self::areaGuardada($pedido);
        $abiertas = InstruccionEntrega::abiertas((int) $pedido->id);

        if ($area === self::PREPARACION_TIENDA) {
            return $abiertas->isNotEmpty();
        }

        if ($area === self::CONTROL_PEDIDOS) {
            return $abiertas->isEmpty()
                && $pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE;
        }

        return false;
    }

    public static function areaGuardada(PedidoBma $pedido): string
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $area = (string) ($json['area_revision'] ?? '');

        return in_array($area, [self::PREPARACION_TIENDA, self::CONTROL_PEDIDOS], true) ? $area : '';
    }
}
