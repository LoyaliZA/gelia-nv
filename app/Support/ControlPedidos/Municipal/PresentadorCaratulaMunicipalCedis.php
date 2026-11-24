<?php

namespace App\Support\ControlPedidos\Municipal;

use App\Models\ClienteConsignatario;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Support\ControlPedidos\ConsultaMunicipal;

/**
 * Datos de consignatario para la pestaña Destino de CEDIS y para decidir si hay identificación vigente.
 * No expone la ruta del archivo: el PDF la incrusta en el servidor.
 */
final class PresentadorCaratulaMunicipalCedis
{
    /** @return array{nombre: ?string, telefono: ?string, leyenda: string, tiene_consignatario: bool, tiene_identificacion: bool}|null */
    public static function para(PedidoBma $pedido): ?array
    {
        if (! ConsultaMunicipal::es($pedido)) {
            return null;
        }

        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $expediente = is_array($json['expediente'] ?? null) ? $json['expediente'] : [];
        $nombre = trim((string) ($expediente['nombre'] ?? ''));
        $telefono = trim((string) ($expediente['telefono'] ?? ''));
        $consignatario = self::consignatario($pedido, $expediente);
        $version = $consignatario?->versionVigente;
        $vigente = $consignatario !== null
            && $consignatario->activo
            && (int) $consignatario->cliente_id === (int) $pedido->cliente_id
            && $version !== null
            && $version->vigente;
        if ($vigente) {
            if ($nombre === '') {
                $nombre = trim((string) $version->nombre);
            }
            if ($telefono === '') {
                $telefono = trim((string) $version->telefono);
            }
        }

        return [
            'nombre' => $nombre !== '' ? $nombre : null,
            'telefono' => $telefono !== '' ? $telefono : null,
            'leyenda' => self::leyenda($pedido, $json),
            'tiene_consignatario' => $vigente,
            'tiene_identificacion' => $vigente && $consignatario->documentoVigente !== null,
        ];
    }

    /** @param array<string, mixed> $expediente */
    public static function consignatario(PedidoBma $pedido, array $expediente): ?ClienteConsignatario
    {
        $id = (int) ($expediente['consignatario_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return ClienteConsignatario::query()->with(['versionVigente', 'documentoVigente'])->find($id);
    }

    /** @param array<string, mixed> $json */
    public static function leyenda(PedidoBma $pedido, array $json): string
    {
        $leyenda = $json['finanzas_municipales']['leyenda_terminal'] ?? null;
        if ($leyenda === null) {
            $leyenda = $pedido->envio_por_cobrar ? PedidoBmaCaratula::COBRO_POR_COBRAR : PedidoBmaCaratula::COBRO_PAGADO;
        }

        return $leyenda === PedidoBmaCaratula::COBRO_POR_COBRAR
            ? PedidoBmaCaratula::COBRO_POR_COBRAR
            : PedidoBmaCaratula::COBRO_PAGADO;
    }
}
