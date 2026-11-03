<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Atiende un retiro ya solicitado. Solicitar no libera stock; aplicarlo sí ajusta lo comprometido y avisa a quien vende. */
final class AtenderRetiroPiezasTiendaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
        private CompromisoInventarioTienda $inventario,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $piezasIdentificadas
     */
    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        int $version,
        string $decision,
        array $piezasIdentificadas = [],
        string $nota = '',
    ): PedidoBma {
        if (! $usuario->can('control_pedidos.tienda.responder') && ! $usuario->can('control_pedidos.tienda.apartado.separar')) {
            throw ValidationException::withMessages(['permiso' => 'El auxiliar asignado atiende el retiro.']);
        }

        return DB::transaction(function () use ($tarea, $usuario, $version, $decision, $piezasIdentificadas, $nota) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($tarea->pedido_bma_id);
            if ((int) $tarea->asignada_a_id !== (int) $usuario->id) {
                throw ValidationException::withMessages(['tarea' => 'Solo el auxiliar que tomó la tarea atiende el retiro.']);
            }
            if ($pedido->pedidoPagadoORemisionado()) {
                throw ValidationException::withMessages([
                    'retiro' => 'El pedido ya está pagado o remisionado. Use su gestión documentada.',
                ]);
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $retiros = is_array($json['retiro_piezas'] ?? null) ? $json['retiro_piezas'] : [];
            $indice = $this->indice($retiros, $version);
            $retiro = $retiros[$indice];
            $estado = (string) ($retiro['estado'] ?? 'pendiente');
            if ($estado !== 'pendiente') {
                throw ValidationException::withMessages(['retiro' => 'Este retiro ya fue atendido. No se aplica de nuevo.']);
            }

            $nota = trim($nota);
            if ($decision === 'rechazar') {
                if ($nota === '') {
                    throw ValidationException::withMessages(['nota' => 'Indique por qué se rechaza el retiro.']);
                }
                $retiro['estado'] = 'rechazada';
                $retiro['resultado'] = $nota;
            } elseif ($decision === 'aplicar') {
                $piezas = ($retiro['entrada'] ?? 'catalogo') === 'documento'
                    ? $this->lineas($piezasIdentificadas)
                    : $this->lineas($retiro['piezas'] ?? []);
                if ($piezas === []) {
                    throw ValidationException::withMessages([
                        'piezas' => 'Identifique los productos y las cantidades comprometidas antes de retirarlas.',
                    ]);
                }
                $this->aplicarPiezas($pedido, $tarea, $piezas);
                $pedido->refresh();
                $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
                $retiros = is_array($json['retiro_piezas'] ?? null) ? $json['retiro_piezas'] : [];
                $indice = $this->indice($retiros, $version);
                $retiro = $retiros[$indice];
                $retiro['estado'] = 'aplicada';
                $retiro['piezas'] = $piezas;
                $retiro['resultado'] = 'Retiro aplicado. Las piezas indicadas salieron del conjunto. No hay pago ni entrega.';
            } else {
                throw ValidationException::withMessages(['decision' => 'Elija aplicar o rechazar el retiro.']);
            }

            $retiro['atendido_at'] = now()->toIso8601String();
            $retiro['atendido_por_id'] = $usuario->id;
            $retiro['atendido_por'] = $usuario->name;
            $retiros[$indice] = $retiro;
            $json['retiro_piezas'] = array_values($retiros);
            if (is_array($json['revision_disponibilidad'] ?? null)) {
                $pedido->solicitud_preparacion_json = $json;
                if (! $pedido->tieneAjusteTiendaPendiente()) {
                    $json['revision_disponibilidad']['aceptacion_vigente'] = true;
                }
            }
            $pedido->solicitud_preparacion_json = $json;
            $pedido->save();

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            if ($estatusId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    (string) $retiro['resultado'],
                    AccionesHistorialPedidoBma::COMPLEMENTO
                );
            }
            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_decision_cliente',
                (string) $retiro['resultado'],
                ['control_pedidos.crear'],
                $usuario->id,
                true,
                ['url' => '/control-pedidos?pedido='.$pedido->id]
            );

            return $pedido->fresh(['documentos', 'estatus']);
        });
    }

    /**
     * @param  list<mixed>  $retiros
     */
    private function indice(array $retiros, int $version): int
    {
        foreach ($retiros as $indice => $retiro) {
            if (is_array($retiro) && (int) ($retiro['version'] ?? 0) === $version) {
                return $indice;
            }
        }

        throw ValidationException::withMessages(['retiro' => 'No se encontró la solicitud de retiro.']);
    }

    /**
     * @param  list<array<string, mixed>>  $piezas
     */
    private function aplicarPiezas(PedidoBma $pedido, PedidoBmaTareaPreparacion $tarea, array $piezas): void
    {
        foreach ($piezas as $pieza) {
            $producto = PedidoBmaTareaProducto::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->whereRaw('lower(sku) = ?', [mb_strtolower($pieza['sku'])])
                ->lockForUpdate()
                ->first();
            $comprometida = $producto ? (int) ($producto->cantidad_encontrada ?? $producto->cantidad_solicitada ?? 0) : 0;
            if (! $producto || $comprometida < $pieza['cantidad']) {
                throw ValidationException::withMessages([
                    'piezas' => 'La pieza no está comprometida en este pedido o la cantidad supera la reserva.',
                ]);
            }
            $queda = $comprometida - $pieza['cantidad'];
            $producto->update([
                'cantidad_encontrada' => $queda,
                'observacion' => $queda === 0
                    ? 'Retirada por petición del cliente.'
                    : trim((string) $producto->observacion),
            ]);
            $this->liberarCompromisoCompleto($pedido, $pieza['sku'], $pieza['cantidad'], $comprometida);
        }
    }

    private function liberarCompromisoCompleto(PedidoBma $pedido, string $sku, int $cantidad, int $comprometida): void
    {
        if ($cantidad < $comprometida) {
            // shortcut: la liberación de inventario es por clave completa; un retiro parcial ajusta la cantidad de la pieza y no parte el apartado, cuando haya que liberar una fracción.
            return;
        }
        $pedido->refresh();
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        foreach ((array) ($json['compromisos_inventario'] ?? []) as $clave => $fila) {
            if (is_array($fila) && ($fila['sku'] ?? null) === $sku && ($fila['activo'] ?? false) === true) {
                $this->inventario->liberarClave($pedido, (string) $clave);
            }
        }
    }

    /**
     * @param  list<mixed>  $piezas
     * @return list<array{sku: string, descripcion: string, cantidad: int}>
     */
    private function lineas(array $piezas): array
    {
        $salida = [];
        foreach ($piezas as $pieza) {
            if (! is_array($pieza)) {
                continue;
            }
            $sku = trim((string) ($pieza['sku'] ?? ''));
            $descripcion = trim((string) ($pieza['descripcion'] ?? ''));
            $cantidad = (int) ($pieza['cantidad'] ?? 0);
            if ($sku === '' || $descripcion === '' || $cantidad < 1) {
                continue;
            }
            $salida[] = ['sku' => $sku, 'descripcion' => $descripcion, 'cantidad' => $cantidad];
        }

        return $salida;
    }
}
