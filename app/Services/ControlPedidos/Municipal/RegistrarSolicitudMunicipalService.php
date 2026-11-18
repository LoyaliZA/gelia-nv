<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\Almacen;
use App\Models\ClienteConsignatario;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarSolicitudMunicipalService
{
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages(['pedido' => 'No puede modificar esta solicitud.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (! $pedido->esEditablePorVendedora() || $pedido->cancelado_at || $pedido->pedidoPagadoORemisionado()) {
                throw ValidationException::withMessages(['pedido' => 'Este pedido ya no admite cambios en la solicitud.']);
            }
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            if (($json['modalidad_entrega'] ?? null) !== 'municipal') {
                throw ValidationException::withMessages(['modalidad' => 'Este pedido no es un envío municipal.']);
            }
            if (ConsultaMunicipal::estado($pedido) === ConsultaMunicipal::CERRADA && ($json['modalidad_entrega'] ?? null) === 'municipal') {
                throw ValidationException::withMessages(['consulta' => 'La consulta ya está cerrada.']);
            }

            if (! empty($json['consulta_municipal']['enviada_at'])) {
                throw ValidationException::withMessages(['consulta' => 'La solicitud ya fue enviada. Resuelva la consulta antes de cambiar sus datos.']);
            }

            $paqueteria = CatalogoPaqueteriaPedido::query()->find((int) ($datos['catalogo_paqueteria_id'] ?? 0));
            if (! $paqueteria || ! $paqueteria->habilitadaParaRegistroMunicipal()) {
                throw ValidationException::withMessages(['catalogo_paqueteria_id' => 'Seleccione un transporte habilitado para envío a municipio.']);
            }
            $leyenda = strtoupper(trim((string) ($datos['leyenda_terminal'] ?? 'PAGADO')));
            if (! in_array($leyenda, ['PAGADO', 'POR_COBRAR'], true)) {
                throw ValidationException::withMessages(['leyenda_terminal' => 'La leyenda de la terminal no es válida.']);
            }
            if ($leyenda === 'POR_COBRAR' && ! $paqueteria->permite_por_cobrar) {
                throw ValidationException::withMessages(['leyenda_terminal' => 'Este transporte no permite envío por cobrar.']);
            }

            $costo = ($datos['costo_transporte'] ?? '') === 'diferido' ? 'diferido' : 'conocido';
            if ($costo === 'diferido' && ! $paqueteria->permiteCostoDiferido()) {
                throw ValidationException::withMessages(['costo_transporte' => 'Esta paquetería no admite costo diferido.']);
            }
            $monto = $datos['costo_envio'] ?? null;
            if ($costo === 'conocido' && (! is_numeric($monto) || round((float) $monto, 2) <= 0)) {
                throw ValidationException::withMessages(['costo_envio' => 'Indique el costo de transporte, mayor que cero.']);
            }

            if (array_key_exists('almacen_id', $datos)) {
                $almacenId = $datos['almacen_id'] ? (int) $datos['almacen_id'] : null;
                $almacen = $almacenId ? Almacen::query()->find($almacenId) : null;
                if ($almacenId && (! $almacen || ! $almacen->activo || ! $almacen->visible_en_pedidos)) {
                    throw ValidationException::withMessages(['almacen_id' => 'Seleccione un almacén activo habilitado para pedidos.']);
                }
                // Intención de procedencia; enviar la consulta sigue siendo un hecho separado.
                $json['almacen_revision_id'] = $almacenId;
            }

            $expediente = $this->expediente($pedido, $datos);
            $json['modalidad_entrega'] = 'municipal';
            $json['codigo_modalidad'] = CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO;
            $json['expediente'] = $expediente;
            $json['finanzas_municipales'] = [
                'costo_transporte' => $costo,
                'leyenda_terminal' => $leyenda,
                'aviso_costo_posterior_at' => $costo === 'diferido' ? now()->toIso8601String() : null,
            ];
            $tipo = $costo === 'diferido'
                ? CatalogoTipoOperacionEnvio::porCodigo(CatalogoTipoOperacionEnvio::CODIGO_MUNICIPIO_DIFERIDO)
                : null;
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'catalogo_paqueteria_id' => $paqueteria->id,
                'costo_envio' => $costo === 'diferido' ? null : round((float) $monto, 2),
                'total_a_cobrar' => PedidoBma::calcularTotal((float) $pedido->total_mercancia, $costo === 'diferido' ? 0 : round((float) $monto, 2), (bool) $pedido->aplica_seguro, (float) $pedido->costo_seguro, (float) $pedido->saldo_a_favor),
                'tipo_operacion_envio_id' => $tipo?->id ?? CatalogoTipoOperacionEnvio::idNormal(),
                'estatus_envio' => $costo === 'diferido' ? PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION : $pedido->estatus_envio,
            ]);

            return $pedido->fresh();
        });
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    private function expediente(PedidoBma $pedido, array $datos): array
    {
        $consignatario = ClienteConsignatario::query()->lockForUpdate()->find((int) ($datos['consignatario_id'] ?? 0));
        if (! $consignatario || (int) $consignatario->cliente_id !== (int) $pedido->cliente_id || ! $consignatario->activo) {
            throw ValidationException::withMessages(['consignatario_id' => 'Seleccione un consignatario vigente de este cliente.']);
        }
        $anterior = data_get($pedido->solicitud_preparacion_json, 'expediente');
        if (is_array($anterior) && (int) ($anterior['consignatario_id'] ?? 0) === (int) $consignatario->id) {
            return $anterior;
        }
        $version = $consignatario->versionVigente;
        if (! $version || ! $version->vigente) {
            throw ValidationException::withMessages(['consignatario_id' => 'El consignatario no tiene datos vigentes.']);
        }
        $documento = $consignatario->documentoVigente;
        if ($documento && (! $documento->vigente || $documento->deleted_at)) {
            $documento = null;
        }

        return [
            'consignatario_id' => $consignatario->id,
            'version_datos_id' => $version->id,
            'documento_id' => $documento?->id,
            'nombre' => $version->nombre,
            'telefono' => $version->telefono,
        ];
    }
}
