<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** El pedido relacionado no queda integrado hasta que quien prepara lo confirma. */
final class IntegrarAnexoFisicoTiendaService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicion,
    ) {}

    public function ejecutar(PedidoBma $anexo, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.tienda.responder')) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien prepara confirma la integración física del anexo.',
            ]);
        }

        return DB::transaction(function () use ($anexo, $usuario) {
            $anexo = PedidoBma::query()->lockForUpdate()->findOrFail($anexo->id);
            $json = is_array($anexo->solicitud_preparacion_json) ? $anexo->solicitud_preparacion_json : [];
            if (($json['anexo_integrado'] ?? false) === true) {
                return $anexo->fresh();
            }
            if (! is_array($json['cobertura_tienda'] ?? null)) {
                throw ValidationException::withMessages(['cobertura' => 'El anexo necesita su propia cobertura.']);
            }
            if (! filled($anexo->numero_remision)) {
                throw ValidationException::withMessages(['remision' => 'El anexo necesita su propia remisión.']);
            }
            $credito = ($json['credito_wizerp']['resultado'] ?? null) === 'aprobado';
            if ($anexo->pago_validado_at === null && ! $credito) {
                throw ValidationException::withMessages(['pago' => 'El anexo necesita su pago o un crédito aprobado.']);
            }

            $principal = $anexo->pedido_principal_id
                ? PedidoBma::query()->lockForUpdate()->find($anexo->pedido_principal_id)
                : null;
            $remisionPrincipal = $principal?->numero_remision;
            $tarea = $this->preparar($anexo, $usuario, $json);

            $lugar = $principal && $principal->estatus_sucursal === EstatusSucursalPedidoBma::EN_CUSTODIA
                ? 'custodia'
                : 'preparacion';
            $json['anexo_integrado'] = true;
            $json['anexo_integracion'] = [
                'estado' => 'integrado',
                'lugar' => $lugar,
                'tarea_id' => $tarea->id,
                'confirmado_por_id' => $usuario->id,
                'confirmado_at' => now()->toIso8601String(),
            ];
            $anexo->update(['solicitud_preparacion_json' => $json]);

            if ($principal) {
                $principalJson = is_array($principal->solicitud_preparacion_json) ? $principal->solicitud_preparacion_json : [];
                $principalJson['anexo_integrado'] = true;
                $principal->update(['solicitud_preparacion_json' => $principalJson]);
                $principal->refresh();
                if ($principal->numero_remision !== $remisionPrincipal) {
                    $principal->update(['numero_remision' => $remisionPrincipal]);
                }
            }

            return $anexo->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function preparar(PedidoBma $anexo, User $usuario, array $json): PedidoBmaTareaPreparacion
    {
        $tarea = InstruccionEntrega::abiertas($anexo->id)->first();
        if (! $tarea) {
            $codigo = match ($json['cobertura_tienda']['tipo'] ?? null) {
                'credito' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO,
                'apartado' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA,
                default => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA,
            };
            $modalidad = CatalogoModalidadPreparacionPedido::query()->where('codigo', $codigo)->firstOrFail();
            $tarea = PedidoBmaTareaPreparacion::query()->create([
                'pedido_bma_id' => $anexo->id,
                'catalogo_modalidad_preparacion_id' => $modalidad->id,
                'almacen_id' => $anexo->almacen_id,
                'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
                'solicitada_por_id' => $usuario->id,
                'version' => 1,
            ]);
            $piezas = array_values(array_filter(
                is_array($json['piezas_anexo'] ?? null) ? $json['piezas_anexo'] : [],
                fn ($pieza) => is_array($pieza) && trim((string) ($pieza['sku'] ?? '')) !== ''
            ));
            foreach ($piezas as $orden => $pieza) {
                $tarea->productos()->create([
                    'sku' => (string) $pieza['sku'],
                    'descripcion_snapshot' => (string) ($pieza['descripcion'] ?? 'Anexo'),
                    'cantidad_solicitada' => (int) ($pieza['cantidad'] ?? 1),
                    'cantidad_encontrada' => (int) ($pieza['cantidad'] ?? 1),
                    'orden' => $orden + 1,
                ]);
            }
        }

        if ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_PENDIENTE) {
            $tarea = $this->transicion->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
                $usuario->id,
                'preparar_anexo',
                'Preparación del anexo.',
                null,
                null,
                $usuario,
                true
            );
        }
        if ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
            $tarea = $this->transicion->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                $usuario->id,
                'anexo_preparado',
                'Anexo preparado para integrarlo.',
                null,
                null,
                $usuario,
                true
            );
        }

        return $tarea;
    }
}
