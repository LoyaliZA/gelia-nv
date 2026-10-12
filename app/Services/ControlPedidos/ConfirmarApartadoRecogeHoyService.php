<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico;
use App\Support\ControlPedidos\PlazosApartadoFisico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmarApartadoRecogeHoyService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private TransicionEstadoTareaPreparacionService $transicion,
        private RegistrarHistorialPedidoService $historial,
        private GenerarCaratulaRecogeHoyService $caratula,
        private NotificarPedidoBmaService $notificar,
        private PreparacionTiendaConfig $config,
    ) {}

    /**
     * @param  list<array{id: int, estado_fisico?: string, observacion?: string|null, cantidad_encontrada?: int}>  $productos
     */
    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        ?int $versionEsperada = null,
        array $productos = [],
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.tienda.apartado.separar')
            && ! $usuario->can('control_pedidos.tienda.responder')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para confirmar el apartado.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $versionEsperada, $productos) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
            $tarea->load(['modalidad', 'pedido.estatus']);
            if ((int) $tarea->asignada_a_id !== (int) $usuario->id) {
                throw ValidationException::withMessages(['tarea' => 'Solo el auxiliar que tomó la tarea puede registrar y confirmar las piezas.']);
            }
            if ($tarea->modalidad?->codigo !== CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA) {
                throw ValidationException::withMessages(['modalidad' => 'Confirmar apartado solo aplica a Recoge en tienda.']);
            }
            $this->asegurar->ejecutar($tarea);
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cumplimiento->estado === PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO) {
                return $cumplimiento;
            }

            if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
                throw ValidationException::withMessages(['estado' => 'Tome la tarea antes de confirmar el apartado.']);
            }
            $this->aplicarRevision($tarea, $productos);
            $tarea->load('productos');
            $cantidad = $this->cantidadConfirmable($tarea->productos);
            if (trim((string) $cumplimiento->ubicacion) === '') {
                throw ValidationException::withMessages(['ubicacion' => 'Indique dónde quedan las piezas apartadas.']);
            }
            $venceAt = PlazosApartadoFisico::cierreOperativo(
                now(), $this->config->zonaHoraria(), $this->config->cierreOperativoHora()
            );
            if (now()->greaterThanOrEqualTo($venceAt) || ! $tarea->fecha_limite || now()->greaterThanOrEqualTo($tarea->fecha_limite)) {
                throw ValidationException::withMessages(['estado' => 'El cierre de tienda ya pasó. Registre una nueva solicitud el próximo día.']);
            }

            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este apartado. Actualice la página e intente de nuevo.',
                ]);
            }

            MaquinaEstadosCumplimientoFisico::assertTransicion(
                $cumplimiento->estado,
                PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO
            );

            $cumplimiento->update([
                'estado' => PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO,
                'version' => $cumplimiento->version + 1,
                'cantidad' => $cantidad,
                'vence_at' => $venceAt,
            ]);

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_APARTADO_CONFIRMADO,
                $usuario,
                'confirmar-apartado:'.$tarea->id.':'.$cumplimiento->version,
                'Apartado confirmado. Sin pago ni transferencia.',
                ['inventario_sincronizado' => false]
            );

            $this->transicion->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                $usuario->id,
                'confirmar_apartado',
                'Apartado confirmado en PDV. Sin cobro en este módulo.',
                null,
                null,
                $usuario,
                true
            );

            $tarea->update([
                'atendida_por_id' => $usuario->id,
                'atendida_at' => now(),
            ]);

            $tarea->documentos()->where(fn ($q) => $q->where('inmutable', false)->orWhereNull('inmutable'))->update(['inmutable' => true]);

            $pedido = $tarea->pedido;
            if ($pedido) {
                $pedido->update([
                    'estatus_envio' => PedidoBma::ESTATUS_ENVIO_APARTADO_CONFIRMADO,
                    'pesaje_respondido_at' => null,
                    'consulta_cerrada_at' => null,
                    'consulta_actualizacion_pendiente' => false,
                    'cantidad_piezas' => $cantidad,
                ]);
                $estatusId = $pedido->estatus?->id;
                if ($estatusId) {
                    $this->historial->ejecutar(
                        $pedido->id,
                        $usuario->id,
                        $estatusId,
                        $estatusId,
                        'Apartado confirmado en PDV. El pedido no pasa a pago ni a auditoría.',
                        AccionesHistorialPedidoBma::APARTADO_CONFIRMADO
                    );
                }
            }

            $this->caratula->ejecutar($tarea->fresh(['modalidad', 'productos', 'pedido.cliente', 'pedido.estatus']), $usuario, true);
            if ($pedido) {
                $this->notificar->ejecutar($pedido->fresh(['cliente', 'vendedor']), 'pedido_apartado_confirmado',
                    "Apartado confirmado en PDV: {$cantidad} piezas en {$cumplimiento->ubicacion}.", [], $usuario->id, true);
            }

            return $cumplimiento->fresh();
        });
    }

    public function cantidadConfirmable(Collection $productos): int
    {
        if ($productos->isEmpty() || $productos->contains(fn ($p) => trim((string) $p->sku) === '')) {
            throw ValidationException::withMessages(['productos' => 'Registre los productos preparados antes de confirmar.']);
        }
        $cantidad = 0;
        foreach ($productos as $producto) {
            $encontrada = $producto->cantidad_encontrada;
            if ($encontrada === null || $encontrada < 0) {
                throw ValidationException::withMessages(['productos' => 'Indique la cantidad realmente preparada de cada producto.']);
            }
            if ($producto->estado_fisico === 'sin_existencia') {
                throw ValidationException::withMessages(['productos' => 'Reporte y resuelva el faltante con Ventas antes de confirmar.']);
            }
            if (! in_array($producto->estado_fisico, PedidoBmaRevisionProducto::ESTADOS, true)
                || (($encontrada === 0 || $producto->estado_fisico !== 'bueno') && trim((string) $producto->observacion) === '')) {
                throw ValidationException::withMessages(['productos' => 'Describa el detalle o retiro de la pieza.']);
            }
            $cantidad += (int) $encontrada;
        }
        if ($cantidad < 1) {
            throw ValidationException::withMessages(['productos' => 'No hay piezas apartadas. Reporte la falta de disponibilidad.']);
        }

        return $cantidad;
    }

    /**
     * @param  list<array{id: int, estado_fisico?: string, observacion?: string|null, cantidad_encontrada?: int}>  $productos
     */
    private function aplicarRevision(PedidoBmaTareaPreparacion $tarea, array $productos): void
    {
        foreach ($productos as $input) {
            $id = (int) ($input['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $producto = $tarea->productos()->where('id', $id)->first();
            if (! $producto) {
                continue;
            }
            $estado = (string) ($input['estado_fisico'] ?? $producto->estado_fisico);
            if (! in_array($estado, PedidoBmaRevisionProducto::ESTADOS, true)) {
                throw ValidationException::withMessages([
                    'estado_fisico' => 'Seleccione si la pieza está bien o con detalle.',
                ]);
            }
            $producto->update([
                'estado_fisico' => $estado,
                'observacion' => $input['observacion'] ?? $producto->observacion,
                'cantidad_encontrada' => array_key_exists('cantidad_encontrada', $input)
                    ? (int) $input['cantidad_encontrada']
                    : $producto->cantidad_encontrada,
            ]);
        }
    }
}
