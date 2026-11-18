<?php

namespace App\Services\ControlPedidos\Conversion;

use App\Models\Almacen;
use App\Models\ClienteConsignatario;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Validation\ValidationException;

/**
 * Validación extraída del alta M-01. No relaja pagado/remisionado ni editabilidad.
 */
class ValidarCapturaMunicipal
{
    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *   paqueteria: CatalogoPaqueteriaPedido,
     *   leyenda: string,
     *   costo: string,
     *   monto: ?float,
     *   almacen_id: ?int,
     *   expediente: array<string, mixed>
     * }
     */
    public function ejecutar(PedidoBma $pedido, array $datos): array
    {
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

        $almacenId = null;
        if (array_key_exists('almacen_id', $datos)) {
            $almacenId = $datos['almacen_id'] ? (int) $datos['almacen_id'] : null;
            $almacen = $almacenId ? Almacen::query()->find($almacenId) : null;
            if ($almacenId && (! $almacen || ! $almacen->activo || ! $almacen->visible_en_pedidos)) {
                throw ValidationException::withMessages(['almacen_id' => 'Seleccione un almacén activo habilitado para pedidos.']);
            }
        }

        return [
            'paqueteria' => $paqueteria,
            'leyenda' => $leyenda,
            'costo' => $costo,
            'monto' => $costo === 'diferido' ? null : round((float) $monto, 2),
            'almacen_id' => $almacenId,
            'expediente' => $this->expediente($pedido, $datos),
        ];
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function expediente(PedidoBma $pedido, array $datos): array
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
