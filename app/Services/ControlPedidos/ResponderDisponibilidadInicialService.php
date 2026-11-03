<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Respuesta inicial por excepciones. No aparta, no traslada y no confirma pago. */
class ResponderDisponibilidadInicialService
{
    public const DECISION_EXCEPCIONES = 'Falta la decisión del cliente sobre las piezas con excepción. Esas piezas no continúan como aceptadas.';

    public const DECISION_TIENDA = 'Revisión sin excepciones. Falta el apartado físico de lo aceptado. No hay pago ni entrega.';

    public const DECISION_CEDIS = 'Consulta de disponibilidad sin excepciones. No hay traslado, pago ni entrega.';

    public const MENSAJE_INCOMPLETA = 'La revisión no está concluida. Confirme el pedido completo con evidencia o indique las piezas con excepción.';

    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    /**
     * @param  array{confirmacion_explicita?: bool, excepciones?: list<array<string, mixed>>}  $datos
     * @param  list<UploadedFile>  $evidencias
     * @return array{estado: string, decision: string, idempotente: bool}
     */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos, array $evidencias, ?PedidoBmaTareaPreparacion $tarea = null): array
    {
        return DB::transaction(function () use ($pedido, $usuario, $datos, $evidencias, $tarea) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->loadMissing('estatus');
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $area = (string) ($json['area_revision'] ?? '');

            if ($tarea !== null) {
                $tarea = InstruccionEntrega::bloquearParaEjecutar($tarea);
                if ((int) $tarea->pedido_bma_id !== (int) $pedido->id) {
                    throw ValidationException::withMessages(['tarea' => 'La revisión no corresponde a este pedido.']);
                }
                if ((int) $tarea->asignada_a_id !== (int) $usuario->id
                    || $tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
                    throw ValidationException::withMessages(['tarea' => 'Tome la revisión antes de responder.']);
                }
                if ($area !== DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA) {
                    throw ValidationException::withMessages(['area_revision' => 'Esta tarea no es la revisión de tienda.']);
                }
            } elseif ($area !== DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS
                || $pedido->estatus_envio !== PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE) {
                throw ValidationException::withMessages(['area_revision' => 'Este pedido no está pendiente de consulta de disponibilidad.']);
            }

            $guardada = is_array($json['revision_disponibilidad'] ?? null) ? $json['revision_disponibilidad'] : null;
            if (is_array($guardada) && isset($guardada['estado'])) {
                return $this->resultado((string) $guardada['estado'], $area, true);
            }

            $excepciones = $this->excepciones($datos['excepciones'] ?? []);
            $confirmada = (bool) ($datos['confirmacion_explicita'] ?? false);
            $archivos = array_values(array_filter($evidencias, fn ($archivo) => $archivo instanceof UploadedFile));
            if ($excepciones !== [] && $confirmada) {
                throw ValidationException::withMessages([
                    'confirmacion_explicita' => 'La confirmación sin excepciones no admite piezas afectadas.',
                ]);
            }
            if ($excepciones === [] && ! $confirmada) {
                throw ValidationException::withMessages(['confirmacion_explicita' => self::MENSAJE_INCOMPLETA]);
            }
            if ($archivos === []) {
                throw ValidationException::withMessages(['evidencias' => 'Adjunte la evidencia de la revisión.']);
            }

            $estado = $excepciones === [] ? 'sin_excepciones' : 'excepciones_pendientes';
            foreach ($archivos as $orden => $archivo) {
                $ruta = $archivo->store('pedidos_bma/'.$pedido->id.'/disponibilidad', 'public');
                $pedido->documentos()->create([
                    'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize(),
                    'orden' => $orden + 1,
                    'comentario' => $estado === 'sin_excepciones' ? 'Evidencia del pedido completo' : 'Evidencia de la excepción',
                    'relacion_tipo' => PedidoBmaDocumento::RELACION_REVISION_GENERAL,
                    'activo' => true,
                ]);
            }

            foreach ($excepciones as $orden => $excepcion) {
                $pedido->revisionesProducto()->create([
                    'orden' => $orden + 1,
                    'descripcion_producto' => $excepcion['descripcion'],
                    'sku' => $excepcion['sku'],
                    'cantidad_solicitada' => $excepcion['cantidad'],
                    'cantidad_encontrada' => $excepcion['condicion'] === 'faltante' ? 0 : $excepcion['cantidad'],
                    'estado_fisico' => $excepcion['condicion'] === 'faltante'
                        ? PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA
                        : PedidoBmaRevisionProducto::ESTADO_REGULAR,
                    'comentario' => $excepcion['detalle'],
                    'resolucion' => null,
                ]);
            }

            $json['revision_disponibilidad'] = [
                'estado' => $estado,
                'confirmada' => $estado === 'sin_excepciones',
                'respondida_at' => now()->toIso8601String(),
                'respondida_por_id' => $usuario->id,
            ];
            $cambios = [
                'solicitud_preparacion_json' => $json,
                'tiene_observaciones_fisicas' => $estado === 'excepciones_pendientes',
            ];
            if ($estado === 'sin_excepciones' && $area === DestinoRevisionRegistrarPedidos::CONTROL_PEDIDOS) {
                $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO);
                if (! $estatusNuevo) {
                    throw new \RuntimeException('No se encontró el estatus de consulta respondida.');
                }
                MaquinaEstadosPedidoBma::assertTransicion(
                    $pedido->estatus?->fase_ciclo,
                    CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO
                );
                $cambios['catalogo_estatus_pedido_id'] = $estatusNuevo->id;
                $cambios['estatus_envio'] = PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO;
                $cambios['pesaje_respondido_at'] = now();
                $cambios['pesaje_respondido_por_id'] = $usuario->id;
            }
            $estatusAnterior = $pedido->estatus?->id;
            $pedido->update($cambios);
            $pedido->refresh();

            if ($tarea !== null) {
                $tarea->update([
                    'observaciones_respuesta' => $this->decision($estado, $area),
                ]);
            }

            $estatusNuevoId = (int) ($pedido->catalogo_estatus_pedido_id ?: $estatusAnterior);
            if ($estatusAnterior && $estatusNuevoId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusAnterior,
                    $estatusNuevoId,
                    $this->decision($estado, $area),
                    AccionesHistorialPedidoBma::RESPUESTA_DISPONIBILIDAD
                );
            }
            $this->notificar->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_disponibilidad_respondida',
                $this->decision($estado, $area),
                ['control_pedidos.crear'],
                $usuario->id,
                true
            );

            return $this->resultado($estado, $area, false);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $entrada
     * @return list<array{descripcion: string, cantidad: int, condicion: string, detalle: string, sku: ?string}>
     */
    private function excepciones(array $entrada): array
    {
        $salida = [];
        foreach ($entrada as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $descripcion = trim((string) ($fila['descripcion'] ?? ''));
            $detalle = trim((string) ($fila['detalle'] ?? ''));
            $condicion = (string) ($fila['condicion'] ?? '');
            $cantidad = (int) ($fila['cantidad'] ?? 0);
            if ($descripcion === '' || $detalle === '' || $cantidad < 1 || ! in_array($condicion, ['detalle', 'faltante'], true)) {
                throw ValidationException::withMessages([
                    'excepciones' => 'Cada excepción necesita la pieza, la cantidad afectada y el detalle o faltante.',
                ]);
            }
            $sku = trim((string) ($fila['sku'] ?? ''));
            $salida[] = [
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'condicion' => $condicion,
                'detalle' => $detalle,
                'sku' => $sku !== '' ? $sku : null,
            ];
        }

        return $salida;
    }

    /**
     * @return array{estado: string, decision: string, idempotente: bool}
     */
    private function resultado(string $estado, string $area, bool $idempotente): array
    {
        return [
            'estado' => $estado,
            'decision' => $this->decision($estado, $area),
            'idempotente' => $idempotente,
        ];
    }

    private function decision(string $estado, string $area): string
    {
        if ($estado === 'excepciones_pendientes') {
            return self::DECISION_EXCEPCIONES;
        }

        return $area === DestinoRevisionRegistrarPedidos::PREPARACION_TIENDA
            ? self::DECISION_TIENDA
            : self::DECISION_CEDIS;
    }
}
