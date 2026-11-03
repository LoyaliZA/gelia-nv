<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\InstruccionEntrega;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** PDV prepara y envía a CEDIS. La recepción es otro paso. */
final class TrasladarCustodiaHaciaCedisService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicion,
        private CrearTraspasoDesdeTareaPreparacionService $traspaso,
        private ConfirmarSalidaTrasladoTiendaService $salida,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function enviar(PedidoBma $custodia, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.tienda.trasladar')) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien prepara en PDV confirma la salida hacia CEDIS.',
            ]);
        }

        return DB::transaction(function () use ($custodia, $usuario) {
            $custodia = PedidoBma::query()->lockForUpdate()->findOrFail($custodia->id);
            $json = is_array($custodia->solicitud_preparacion_json) ? $custodia->solicitud_preparacion_json : [];
            $retiro = is_array($json['retiro_recoleccion'] ?? null) ? $json['retiro_recoleccion'] : [];
            if (($retiro['estado'] ?? null) === 'en_traslado') {
                return $custodia->fresh();
            }
            if (($retiro['estado'] ?? null) !== 'pendiente_traslado') {
                throw ValidationException::withMessages([
                    'retiro' => 'Primero retire la custodia del pedido de envío. Eso no traslada las bolsas.',
                ]);
            }

            $tarea = InstruccionEntrega::abiertas($custodia->id)->first();
            if (! $tarea) {
                throw ValidationException::withMessages(['tarea' => 'No hay una instrucción vigente para preparar el traslado.']);
            }
            $tarea->load('productos');
            $tarea->update(['requiere_traslado_cedis' => true]);
            $this->preparar($tarea, $usuario);
            $this->traspaso->ejecutar($tarea->fresh(), $usuario);
            $this->salida->ejecutar($tarea->fresh(), $usuario, null);

            $json['retiro_recoleccion']['estado'] = 'en_traslado';
            $json['retiro_recoleccion']['integrado_cedis'] = false;
            $json['retiro_recoleccion']['traspaso_id'] = $tarea->fresh()->solicitud_traspaso_id;
            $json['retiro_recoleccion']['salida_at'] = now()->toIso8601String();
            $custodia->update(['solicitud_preparacion_json' => $json]);

            $principal = PedidoBma::query()->find($retiro['pedido_principal_id'] ?? $custodia->pedido_principal_id);
            if ($principal) {
                $principalJson = is_array($principal->solicitud_preparacion_json) ? $principal->solicitud_preparacion_json : [];
                $principalJson['complemento_pdv'] = [
                    'pedido_id' => $custodia->id,
                    'estado' => 'en_traslado',
                    'integrado' => false,
                ];
                $principal->update(['solicitud_preparacion_json' => $principalJson]);
            }

            $this->notificar->ejecutar(
                $custodia->fresh(['cliente', 'vendedor']),
                'pedido_custodia_hacia_cedis',
                'Las bolsas salieron de PDV hacia CEDIS. El envío principal espera la recepción.',
                ['control_pedidos.cedis'],
                $usuario->id,
                true
            );

            return $custodia->fresh();
        });
    }

    public function recibir(PedidoBma $custodia, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.cedis')) {
            throw ValidationException::withMessages([
                'permiso' => 'CEDIS confirma que las bolsas llegaron.',
            ]);
        }

        return DB::transaction(function () use ($custodia, $usuario) {
            $custodia = PedidoBma::query()->lockForUpdate()->findOrFail($custodia->id);
            $json = is_array($custodia->solicitud_preparacion_json) ? $custodia->solicitud_preparacion_json : [];
            $retiro = is_array($json['retiro_recoleccion'] ?? null) ? $json['retiro_recoleccion'] : [];
            if (($retiro['integrado_cedis'] ?? false) === true) {
                return $custodia->fresh();
            }
            if (($retiro['estado'] ?? null) !== 'en_traslado') {
                throw ValidationException::withMessages([
                    'traslado' => 'Las bolsas todavía no salieron de PDV. El envío principal sigue esperando.',
                ]);
            }

            $tarea = InstruccionEntrega::abiertas($custodia->id)->first();
            if (! $tarea || $tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO) {
                throw ValidationException::withMessages(['tarea' => 'No hay un traslado en camino hacia CEDIS.']);
            }

            $this->transicion->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
                $usuario->id,
                'recibir_complemento_cedis',
                'CEDIS recibió las bolsas retiradas de recolección.',
                null,
                null,
                $usuario,
                true
            );

            $json['retiro_recoleccion']['estado'] = 'recibido_cedis';
            $json['retiro_recoleccion']['integrado_cedis'] = true;
            $json['retiro_recoleccion']['recibido_at'] = now()->toIso8601String();
            $custodia->update(['solicitud_preparacion_json' => $json]);

            $principalId = (int) ($retiro['pedido_principal_id'] ?? $custodia->pedido_principal_id);
            $principal = $principalId > 0 ? PedidoBma::query()->lockForUpdate()->find($principalId) : null;
            if ($principal) {
                $principalJson = is_array($principal->solicitud_preparacion_json) ? $principal->solicitud_preparacion_json : [];
                $principalJson['complemento_pdv'] = [
                    'pedido_id' => $custodia->id,
                    'estado' => 'integrado',
                    'integrado' => true,
                    'recibido_at' => now()->toIso8601String(),
                ];
                $principal->update(['solicitud_preparacion_json' => $principalJson]);
            }

            return $custodia->fresh();
        });
    }

    private function preparar(PedidoBmaTareaPreparacion $tarea, User $usuario): void
    {
        if ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_PENDIENTE) {
            $tarea = $this->transicion->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
                $usuario->id,
                'preparar_traslado_pdv',
                'PDV prepara las bolsas para CEDIS.',
                null,
                null,
                $usuario,
                true
            );
        }
        if ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
            $this->transicion->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
                $usuario->id,
                'lista_traslado_pdv',
                'Bolsas listas para salir de PDV hacia CEDIS.',
                null,
                null,
                $usuario,
                true
            );
        }
    }
}
