<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** La recepción abre custodia. No entrega al cliente. */
final class RegistrarRecepcionCustodiaTiendaService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private AbrirCustodiaDiferidaPdvService $custodia,
        private CompromisoInventarioTienda $inventario,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario): PedidoBmaTareaPreparacion
    {
        if (\App\Support\ControlPedidos\TransferenciaT02::documental($tarea->pedido)) {
            throw ValidationException::withMessages(['custodia' => 'En T-02 registre por separado la recepción de bolsas y su ubicación de custodia.']);
        }
        if (! $usuario->can('control_pedidos.tienda.responder') && ! $usuario->can('control_pedidos.tienda.trasladar')) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien recibe en sucursal confirma la custodia.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario) {
            $tarea = InstruccionEntrega::bloquearParaEjecutar($tarea);
            $tarea->loadMissing(['pedido', 'productos', 'modalidad']);
            $pedido = $tarea->pedido;
            if (! $pedido || ! filled($pedido->numero_remision)) {
                throw ValidationException::withMessages([
                    'remision' => 'Falta la remisión. La recepción no la sustituye.',
                ]);
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $credito = ($json['credito_wizerp']['resultado'] ?? null) === 'aprobado';
            if ($pedido->pago_validado_at === null && ! $credito) {
                throw ValidationException::withMessages([
                    'pago' => 'Falta el pago validado o el crédito aprobado.',
                ]);
            }
            if (($json['retiro_recoleccion']['estado'] ?? null) === 'pendiente_traslado') {
                throw ValidationException::withMessages([
                    'retiro' => 'Esta mercancía va hacia CEDIS. No se recibe como recolección.',
                ]);
            }

            $this->asegurar->ejecutar($tarea);
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();
            $resguardo = $this->custodia->ejecutar($tarea, $cumplimiento, $usuario);
            if (! $resguardo) {
                throw ValidationException::withMessages([
                    'modalidad' => 'Esta modalidad no abre custodia en sucursal.',
                ]);
            }

            $pedido->update(['estatus_sucursal' => EstatusSucursalPedidoBma::EN_CUSTODIA]);
            foreach ($tarea->productos as $producto) {
                $cantidad = (int) $producto->cantidad_encontrada > 0
                    ? (int) $producto->cantidad_encontrada
                    : (int) $producto->cantidad_solicitada;
                if (! $producto->producto_id || $cantidad < 1) {
                    continue;
                }
                $this->inventario->comprometer(
                    $pedido,
                    (int) $tarea->almacen_id,
                    (int) $producto->producto_id,
                    (string) $cantidad,
                    'tarea:'.$tarea->id.':producto:'.$producto->id,
                    (string) $producto->sku
                );
            }

            $this->avanzar($cumplimiento, $usuario, PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, PedidoBmaCumplimientoEvento::TIPO_PIEZA_SEPARADA, 'Bolsas recibidas en sucursal.');
            $cumplimiento->refresh();
            $this->avanzar($cumplimiento, $usuario, PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA, PedidoBmaCumplimientoEvento::TIPO_SALIDA_AUTORIZADA, 'En custodia. Falta la entrega a la clientela.');

            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_custodia_recibida',
                'La sucursal recibió la mercancía y la tiene en custodia. Falta la entrega.',
                ['control_pedidos.crear'],
                $usuario->id,
                true,
                ['url' => '/control-pedidos/tienda/tareas/'.$tarea->id]
            );

            return $tarea->fresh(['cumplimientoFisico', 'pedido']);
        });
    }

    private function avanzar(PedidoBmaCumplimientoFisico $cumplimiento, User $usuario, string $destino, string $tipo, string $texto): void
    {
        if ($cumplimiento->estado === $destino) {
            return;
        }
        if (! MaquinaEstadosCumplimientoFisico::puedeTransicionar($cumplimiento->estado, $destino)) {
            return;
        }

        $cumplimiento->update([
            'estado' => $destino,
            'version' => $cumplimiento->version + 1,
        ]);
        $this->eventos->ejecutar(
            $cumplimiento,
            $tipo,
            $usuario,
            'recepcion-custodia:'.$cumplimiento->id.':'.$destino,
            $texto
        );
    }
}
