<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Support\Collection;

final class SerializadorBultosEmpaqueCedisPdv
{
    /**
     * @return list<array{
     *     numero: int,
     *     foto_bulto: array{url: string, nombre_original: string|null}|null,
     *     foto_ticket: array{url: string, nombre_original: string|null}|null
     * }>
     */
    public static function desdePedido(?PedidoBma $pedido): array
    {
        if (! $pedido instanceof PedidoBma) {
            return [];
        }

        $pedido->loadMissing(['bultosEmpaque.documentos']);

        return $pedido->bultosEmpaque
            ->sortBy('numero')
            ->values()
            ->map(fn ($bulto) => self::serializarBulto($bulto->numero, $bulto->documentos))
            ->all();
    }

    /**
     * @return array{
     *     numero: int,
     *     foto_bulto: array{url: string, nombre_original: string|null}|null,
     *     foto_ticket: array{url: string, nombre_original: string|null}|null
     * }
     */
    private static function serializarBulto(int $numero, Collection $documentos): array
    {
        $fotoBulto = $documentos->first(
            fn ($doc) => $doc->tipo === PedidoBmaDocumento::TIPO_EVIDENCIA_BULTO_EMPAQUE
        );
        $fotoTicket = $documentos->first(
            fn ($doc) => $doc->tipo === PedidoBmaDocumento::TIPO_EVIDENCIA_TICKET_BULTO_EMPAQUE
        );

        return [
            'numero' => $numero,
            'foto_bulto' => self::serializarDocumento($fotoBulto),
            'foto_ticket' => self::serializarDocumento($fotoTicket),
        ];
    }

    /**
     * @return array{url: string, nombre_original: string|null}|null
     */
    private static function serializarDocumento(?PedidoBmaDocumento $documento): ?array
    {
        if (! $documento instanceof PedidoBmaDocumento || ! $documento->ruta_archivo) {
            return null;
        }

        return [
            'url' => $documento->url,
            'nombre_original' => $documento->nombre_original,
        ];
    }
}
