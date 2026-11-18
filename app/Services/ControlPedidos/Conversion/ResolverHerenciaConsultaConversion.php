<?php

namespace App\Services\ControlPedidos\Conversion;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\ActualizacionConsulta;
use App\Support\ControlPedidos\AlmacenPdv;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use App\Support\ControlPedidos\Conversion\HerenciaConsultaConversion;

class ResolverHerenciaConsultaConversion
{
    public function clasificar(PedidoBma $pedido, ?int $almacenDestinoId = null): HerenciaConsultaConversion
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $revision = is_array($json['revision_disponibilidad'] ?? null) ? $json['revision_disponibilidad'] : [];
        $consultaMunicipal = is_array($json['consulta_municipal'] ?? null) ? $json['consulta_municipal'] : [];
        $enviada = filled($consultaMunicipal['enviada_at'] ?? null) || filled($revision['respondida_at'] ?? null);
        $origen = $this->consultaOrigen($pedido, $json, $revision, $consultaMunicipal);

        if ($revision === [] && ! $enviada) {
            return new HerenciaConsultaConversion(
                CodigosConversionModalidad::CASO_SIN_CONSULTA,
                $this->resumenConsulta($json, $revision, $consultaMunicipal),
                $this->resumenPagos($pedido),
                $this->resumenRemision($pedido),
                $this->resumenDocumentos($pedido),
                CodigosConversionModalidad::PROCEDENCIA_NUEVA,
                CodigosConversionModalidad::CONSULTA_ORIGEN_NINGUNA,
            );
        }

        $parcial = $this->esParcial($pedido, $json, $revision, $almacenDestinoId);
        $caso = $parcial
            ? CodigosConversionModalidad::CASO_PARCIAL
            : CodigosConversionModalidad::CASO_RESPONDIDA_ABIERTA;
        $procedencia = $parcial
            ? CodigosConversionModalidad::PROCEDENCIA_HEREDADA_PARCIAL
            : CodigosConversionModalidad::PROCEDENCIA_HEREDADA_COMPLETA;

        return new HerenciaConsultaConversion(
            $caso,
            $this->resumenConsulta($json, $revision, $consultaMunicipal),
            $this->resumenPagos($pedido),
            $this->resumenRemision($pedido),
            $this->resumenDocumentos($pedido),
            $procedencia,
            $origen,
            $parcial,
        );
    }

    /**
     * Escribe metadatos municipales. Nunca hace unset de revision_disponibilidad.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function aplicar(array $json, HerenciaConsultaConversion $herencia, int $usuarioId, ?int $almacenId): array
    {
        $previo = is_array($json['consulta_municipal'] ?? null) ? $json['consulta_municipal'] : [];
        $revision = is_array($json['revision_disponibilidad'] ?? null) ? $json['revision_disponibilidad'] : [];
        $enviadaAt = $previo['enviada_at'] ?? ($revision['respondida_at'] ?? null);
        $consulta = [
            'procedencia' => $herencia->procedencia,
            'consulta_origen' => $herencia->consulta_origen,
            'almacen_id' => $almacenId ?? ($previo['almacen_id'] ?? ($json['almacen_revision_id'] ?? null)),
            'heredada_en' => now()->toIso8601String(),
            'heredada_por_id' => $usuarioId,
        ];
        if (isset($previo['area'])) {
            $consulta['area'] = $previo['area'];
        } elseif ($herencia->consulta_origen === CodigosConversionModalidad::CONSULTA_ORIGEN_TIENDA) {
            $consulta['area'] = 'tienda';
        } elseif ($herencia->consulta_origen === CodigosConversionModalidad::CONSULTA_ORIGEN_CEDIS) {
            $consulta['area'] = 'cedis';
        }

        if ($herencia->caso === CodigosConversionModalidad::CASO_SIN_CONSULTA) {
            $consulta['estado'] = ConsultaMunicipal::PENDIENTE;
            $json['consulta_municipal'] = $consulta;

            return $json;
        }

        if ($herencia->caso === CodigosConversionModalidad::CASO_PARCIAL) {
            $consulta['estado'] = ConsultaMunicipal::EXCEPCIONES;
            $consulta['requiere_revision_complementaria'] = true;
            if (filled($enviadaAt)) {
                $consulta['enviada_at'] = $enviadaAt;
                $consulta['enviada_por_id'] = $previo['enviada_por_id'] ?? ($revision['respondida_por_id'] ?? $usuarioId);
            }
            if (is_array($json['revision_disponibilidad'] ?? null)) {
                $json['revision_disponibilidad']['estado'] = 'excepciones_pendientes';
            }
            $json['consulta_municipal'] = $consulta;

            return ConsultaMunicipal::aplicarEstado($json, 'excepciones_pendientes');
        }

        if (filled($enviadaAt)) {
            $consulta['enviada_at'] = $enviadaAt;
            $consulta['enviada_por_id'] = $previo['enviada_por_id'] ?? ($revision['respondida_por_id'] ?? $usuarioId);
        }
        $json['consulta_municipal'] = $consulta;
        $estadoRevision = $revision['estado'] ?? 'sin_excepciones';

        return ConsultaMunicipal::aplicarEstado($json, is_string($estadoRevision) ? $estadoRevision : 'sin_excepciones');
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $revision
     */
    private function esParcial(PedidoBma $pedido, array $json, array $revision, ?int $almacenDestinoId): bool
    {
        $estado = $revision['estado'] ?? null;
        if ($estado !== 'sin_excepciones') {
            return true;
        }
        if (($revision['vigente'] ?? true) === false || filled($revision['sustituida_at'] ?? null)) {
            return true;
        }
        if (ActualizacionConsulta::pendiente($json) || $pedido->tieneAjusteTiendaPendiente()) {
            return true;
        }
        foreach ($json['retiro_piezas'] ?? [] as $retiro) {
            if (is_array($retiro) && ($retiro['estado'] ?? '') === 'pendiente') {
                return true;
            }
        }
        $almacenActual = $json['consulta_municipal']['almacen_id'] ?? ($json['almacen_revision_id'] ?? null);
        if ($almacenDestinoId && $almacenActual && (int) $almacenDestinoId !== (int) $almacenActual) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $revision
     * @param  array<string, mixed>  $consultaMunicipal
     */
    private function consultaOrigen(PedidoBma $pedido, array $json, array $revision, array $consultaMunicipal): string
    {
        if (($consultaMunicipal['consulta_origen'] ?? null) === CodigosConversionModalidad::CONSULTA_ORIGEN_TIENDA
            || ($consultaMunicipal['area'] ?? null) === 'tienda'
            || ($json['area_revision'] ?? null) === 'preparacion_tienda') {
            return CodigosConversionModalidad::CONSULTA_ORIGEN_TIENDA;
        }
        $almacenId = $consultaMunicipal['almacen_id'] ?? ($json['almacen_revision_id'] ?? null);
        if ($almacenId) {
            $almacen = Almacen::query()->with('tipoAlmacen')->find((int) $almacenId);
            if ($almacen && AlmacenPdv::esAlmacen($almacen)) {
                return CodigosConversionModalidad::CONSULTA_ORIGEN_TIENDA;
            }
            if ($almacen) {
                return CodigosConversionModalidad::CONSULTA_ORIGEN_CEDIS;
            }
        }
        if ($revision !== [] || $pedido->esConsultaDisponibilidadCedis()) {
            return CodigosConversionModalidad::CONSULTA_ORIGEN_CEDIS;
        }
        if ($pedido->esConsultaDisponibilidadTienda()) {
            return CodigosConversionModalidad::CONSULTA_ORIGEN_TIENDA;
        }

        return CodigosConversionModalidad::CONSULTA_ORIGEN_NINGUNA;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $revision
     * @param  array<string, mixed>  $consultaMunicipal
     * @return array<string, mixed>
     */
    private function resumenConsulta(array $json, array $revision, array $consultaMunicipal): array
    {
        return [
            'estado_revision' => $revision['estado'] ?? null,
            'respondida_at' => $revision['respondida_at'] ?? null,
            'enviada_at' => $consultaMunicipal['enviada_at'] ?? null,
            'almacen_id' => $consultaMunicipal['almacen_id'] ?? ($json['almacen_revision_id'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function resumenPagos(PedidoBma $pedido): array
    {
        return [
            'pagado_o_remisionado' => $pedido->pedidoPagadoORemisionado(),
            'tiene_pago_validado' => $pedido->tienePagoValidado(),
        ];
    }

    /** @return array<string, mixed> */
    private function resumenRemision(PedidoBma $pedido): array
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];

        return [
            'numero_remision' => $pedido->numero_remision,
            'folio_remision' => $pedido->folio_remision,
            'emitida_at' => $json['remision_tienda']['emitida_at'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function resumenDocumentos(PedidoBma $pedido): array
    {
        return [
            'tiene_soporte' => $pedido->documentos()
                ->whereIn('tipo', ['pdf_pedido', 'cotizacion'])
                ->where('activo', true)
                ->exists(),
        ];
    }
}
