<?php

namespace App\Support\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use Illuminate\Validation\ValidationException;

/** Seguimiento municipal. El detalle de la respuesta vive en revision_disponibilidad. */
final class ConsultaMunicipal
{
    public const PENDIENTE = 'pendiente';

    public const RESPONDIDA = 'respondida';

    public const EXCEPCIONES = 'excepciones_pendientes';

    public const CERRADA = 'cerrada';

    public static function es(PedidoBma $pedido): bool
    {
        $json = self::json($pedido);

        return ($json['modalidad_entrega'] ?? null) === 'municipal';
    }

    /** @return array<string, mixed> */
    public static function seguimiento(PedidoBma $pedido): array
    {
        $datos = self::json($pedido)['consulta_municipal'] ?? [];

        return is_array($datos) ? $datos : [];
    }

    /** Proyección de lectura: el origen es el almacén consultado, no un movimiento físico. */
    public static function hidratarAlmacen(PedidoBma $pedido): void
    {
        if (! self::es($pedido)) {
            return;
        }
        $id = self::seguimiento($pedido)['almacen_id'] ?? null;
        if ($id && (int) $pedido->almacen?->id !== (int) $id) {
            $pedido->setRelation('almacen', Almacen::query()->find($id));
        }
    }

    public static function estado(PedidoBma $pedido): string
    {
        $seguimiento = self::seguimiento($pedido);
        if (! empty($seguimiento['cerrada_at']) || ($seguimiento['estado'] ?? null) === self::CERRADA) {
            return self::CERRADA;
        }
        $revision = self::revision($pedido);
        $estadoRevision = $revision['estado'] ?? null;
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        if (($revision['vigente'] ?? true) === false || filled($revision['sustituida_at'] ?? null)
            || ActualizacionConsulta::pendiente($json)) {
            return self::EXCEPCIONES;
        }
        if ($estadoRevision === 'excepciones_pendientes') {
            return self::EXCEPCIONES;
        }
        if ($estadoRevision === 'sin_excepciones') {
            return self::RESPONDIDA;
        }

        return (string) ($seguimiento['estado'] ?? self::PENDIENTE);
    }

    public static function exigirRespuesta(PedidoBma $pedido): void
    {
        if (! self::es($pedido)) {
            return;
        }
        $estado = self::revision($pedido)['estado'] ?? null;
        if (! in_array($estado, ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'], true)) {
            throw ValidationException::withMessages([
                'ajuste' => 'El ajuste se solicita cuando ya hay una respuesta de las piezas.',
            ]);
        }
    }

    /**
     * Una modificación municipal reabre la decisión comercial. No cierra la consulta ni pide pesaje.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public static function abrirPorAjuste(array $json): array
    {
        if (($json['modalidad_entrega'] ?? null) !== 'municipal') {
            return $json;
        }
        $seguimiento = is_array($json['consulta_municipal'] ?? null) ? $json['consulta_municipal'] : [];
        unset($seguimiento['cerrada_at'], $seguimiento['cerrada_por_id']);
        $seguimiento['estado'] = self::EXCEPCIONES;
        $json['consulta_municipal'] = $seguimiento;

        return $json;
    }

    public static function motivoBloqueo(PedidoBma $pedido): ?string
    {
        if (! self::es($pedido)) {
            return null;
        }
        $revision = self::revision($pedido);
        if (ActualizacionConsulta::pendiente(is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [])) {
            return 'Hay un anexo pendiente de respuesta. El retiro solicitado no exige reconfirmación.';
        }
        if (($revision['vigente'] ?? true) === false || filled($revision['sustituida_at'] ?? null)) {
            return 'La respuesta vigente fue sustituida. No se cierra ni se cubre con la versión anterior.';
        }
        $estado = $revision['estado'] ?? null;
        if (! in_array($estado, ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'], true)) {
            return 'La consulta sigue abierta: falta la respuesta de disponibilidad.';
        }
        if ($pedido->revisionesProducto()->where('estado_fisico', PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA)->whereNull('resolucion')->exists()) {
            return 'Hay piezas sin existencias: retírelas o solicite su sustitución antes de cerrar la consulta.';
        }
        if ($pedido->revisionesProducto()->whereIn('estado_fisico', ['regular', 'malo', 'danado'])->whereNull('resolucion')->exists()) {
            return 'Confirme el estado de las piezas con detalle, retírelas o solicite su sustitución antes de cerrar la consulta.';
        }
        if (($revision['aceptacion_vigente'] ?? true) === false && ActualizacionConsulta::pendiente(is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [])) {
            return 'Hay un anexo pendiente de respuesta. El retiro solicitado no exige reconfirmación.';
        }

        return null;
    }

    /**
     * Alinea el estado visible con la revisión. No copia excepciones.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public static function aplicarEstado(array $json, string $estadoRevision): array
    {
        $seguimiento = is_array($json['consulta_municipal'] ?? null) ? $json['consulta_municipal'] : [];
        if (! empty($seguimiento['cerrada_at'])) {
            $seguimiento['estado'] = self::CERRADA;
            $json['consulta_municipal'] = $seguimiento;

            return $json;
        }
        $seguimiento['estado'] = $estadoRevision === 'sin_excepciones' ? self::RESPONDIDA : self::EXCEPCIONES;
        $json['consulta_municipal'] = $seguimiento;

        return $json;
    }

    /** @param array<string, mixed> $json @return array<string, mixed> */
    public static function cerrar(array $json, int $usuarioId): array
    {
        $seguimiento = is_array($json['consulta_municipal'] ?? null) ? $json['consulta_municipal'] : [];
        $seguimiento['estado'] = self::CERRADA;
        $seguimiento['cerrada_at'] = now()->toIso8601String();
        $seguimiento['cerrada_por_id'] = $usuarioId;
        $json['consulta_municipal'] = $seguimiento;

        return $json;
    }

    /** @return array<string, mixed> */
    private static function json(PedidoBma $pedido): array
    {
        return is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
    }

    /** @return array<string, mixed> */
    private static function revision(PedidoBma $pedido): array
    {
        $revision = self::json($pedido)['revision_disponibilidad'] ?? [];

        return is_array($revision) ? $revision : [];
    }
}
