<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReabrirApartadoRecogeHoyService
{
    public function __construct(
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario, string $motivo, int $version, ?UploadedFile $archivo = null): void
    {
        abort_unless($usuario->can('control_pedidos.preparacion.solicitar'), 403);
        DB::transaction(function () use ($tarea, $usuario, $motivo, $version, $archivo) {
            $pedidoBloqueado = PedidoBma::query()->lockForUpdate()->findOrFail($tarea->pedido_bma_id);
            if ($pedidoBloqueado->tareaPreparacionVigente()->whereKeyNot($tarea->id)->exists()) {
                throw ValidationException::withMessages(['estado' => 'Ya existe otra solicitud de preparación vigente.']);
            }

            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
            $tarea->load(['pedido', 'modalidad']);
            abort_unless(VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $tarea->pedido), 403);
            $codigo = $tarea->modalidad?->codigo;
            $esRecogeHoy = $codigo === CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA;
            $esTransferencia = $codigo === CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA;
            if ((! $esRecogeHoy && ! $esTransferencia) || (int) $tarea->version !== $version) {
                throw ValidationException::withMessages(['estado' => 'El apartado cambió o no admite reapertura. Actualice la página.']);
            }
            if ($esRecogeHoy && ! in_array($tarea->estado, [PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA, PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA], true)) {
                throw ValidationException::withMessages(['estado' => 'El apartado cambió o no admite reapertura. Actualice la página.']);
            }
            if ($esRecogeHoy && (! $tarea->fecha_limite || now()->greaterThanOrEqualTo($tarea->fecha_limite))) {
                throw ValidationException::withMessages(['estado' => 'El apartado corresponde a un día anterior o ya venció. Registre una nueva solicitud.']);
            }
            if ($esTransferencia && ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA
                || $tarea->requiere_traslado_cedis
                || $tarea->pedido->consultaCerrada())) {
                throw ValidationException::withMessages(['estado' => 'Esta transferencia no admite un anexo de piezas.']);
            }
            if (trim($motivo) === '' || ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA && ! $archivo)) {
                throw ValidationException::withMessages(['archivo' => 'Describa el cambio y adjunte el complemento del pedido.']);
            }
            $apartado = PedidoBmaCumplimientoFisico::query()->where('pedido_bma_tarea_preparacion_id', $tarea->id)->lockForUpdate()->firstOrFail();
            $estadosApartado = $esTransferencia
                ? [PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR, PedidoBmaCumplimientoFisico::ESTADO_SEPARADA]
                : [PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR, PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO];
            if (! in_array($apartado->estado, $estadosApartado, true)
                || ($esTransferencia && ($apartado->pago_confirmado_at || $apartado->salida_autorizada_at))) {
                throw ValidationException::withMessages(['estado' => 'La mercancía ya está en otro proceso.']);
            }
            if ($archivo) {
                $ruta = $archivo->store('pedidos_bma/'.$tarea->pedido_bma_id.'/anexos', 'public');
                $tarea->pedido->documentos()->create([
                    'tipo' => PedidoBmaDocumento::TIPO_ANEXO_PIEZAS, 'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(), 'mime_type' => $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize(), 'activo' => true, 'comentario' => trim($motivo),
                ]);
            }
            $anterior = $tarea->estado;
            $tarea->update(['estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE, 'version' => $tarea->version + 1,
                'asignada_a_id' => null, 'atendida_at' => null, 'atendida_por_id' => null,
                'observaciones_solicitud' => trim($motivo)]);
            $tarea->historial()->create(['estado_anterior' => $anterior, 'estado_nuevo' => $tarea->estado,
                'usuario_id' => $usuario->id, 'accion' => 'reabrir_apartado', 'comentario' => trim($motivo)]);
            $apartado->update(['estado' => $apartado->cantidad > 0 ? PedidoBmaCumplimientoFisico::ESTADO_SEPARADA : PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
                'version' => $apartado->version + 1]);
            $this->eventos->ejecutar($apartado, PedidoBmaCumplimientoEvento::TIPO_DETALLE_REPORTADO, $usuario,
                'reabrir:'.$tarea->id.':'.$tarea->version, trim($motivo), ['piezas_conservadas' => true]);
            $datosPedido = [
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
                'consulta_actualizacion_pendiente' => false,
            ];
            if ($esTransferencia) {
                $datosPedido['pesaje_respondido_at'] = null;
                $datosPedido['pesaje_respondido_por_id'] = null;
            }
            $tarea->pedido->update($datosPedido);
            $this->notificar->ejecutar($tarea->pedido->fresh(['cliente', 'vendedor']), 'pedido_preparacion_tienda_corregida',
                $esTransferencia
                    ? 'Se solicitó un anexo de piezas: '.trim($motivo)
                    : 'Ventas reabrió el apartado: '.trim($motivo),
                ['control_pedidos.tienda.ver'], $usuario->id, false,
                ['url' => '/control-pedidos/tienda/tareas/'.$tarea->id]);
        });
    }
}
