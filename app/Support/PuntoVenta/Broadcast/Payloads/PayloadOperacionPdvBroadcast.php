<?php

namespace App\Support\PuntoVenta\Broadcast\Payloads;

use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;

final class PayloadOperacionPdvBroadcast
{
    /**
     * @return array<string, mixed>
     */
    public static function jornada(JornadaPdv $jornada): array
    {
        $estado = $jornada->estado instanceof EstadoJornadaPdv
            ? $jornada->estado->value
            : (string) $jornada->estado;

        return [
            'jornada_id' => $jornada->id,
            'user_id' => $jornada->user_id,
            'estado' => $estado,
            'apertura_at' => $jornada->apertura_at?->toIso8601String(),
            'cierre_at' => $jornada->cierre_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function intervalo(IntervaloOperativoPdv $intervalo, bool $incluirDetalleMotivo = false): array
    {
        $tipo = $intervalo->tipo instanceof TipoIntervaloOperativoPdv
            ? $intervalo->tipo->value
            : (string) $intervalo->tipo;

        $payload = [
            'intervalo_id' => $intervalo->id,
            'jornada_id' => $intervalo->jornada_id,
            'user_id' => $intervalo->user_id,
            'tipo' => $tipo,
            'inicio_at' => $intervalo->inicio_at?->toIso8601String(),
            'fin_at' => $intervalo->fin_at?->toIso8601String(),
        ];

        if ($tipo === TipoIntervaloOperativoPdv::EnPausa->value) {
            $payload['pausa_motivo'] = $intervalo->etiquetaMotivoPausa();
            if ($incluirDetalleMotivo) {
                $payload['pausa_motivo_completo'] = $intervalo->textoMotivoPausaCompleto();
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function sucursalDia(SucursalDiaOperacionPdv $dia): array
    {
        return [
            'sucursal_dia_id' => $dia->id,
            'fecha_operativa' => $dia->fecha_operativa?->toDateString(),
            'hora_cierre' => $dia->hora_cierre,
            'acepta_altas' => (bool) $dia->acepta_altas,
            'ampliacion_hasta_at' => $dia->ampliacion_hasta_at?->toIso8601String(),
        ];
    }

    public static function eventIdDesdeEvento(OperacionPdvEvento $evento): string
    {
        return 'operacion:'.$evento->tipo_evento.':'.$evento->id;
    }

    public static function eventIdJornada(string $tipo, int $jornadaId): string
    {
        return 'operacion:'.$tipo.':jornada:'.$jornadaId;
    }

    public static function eventIdSucursalDia(string $tipo, int $sucursalDiaId): string
    {
        return 'operacion:'.$tipo.':sucursal-dia:'.$sucursalDiaId;
    }
}
