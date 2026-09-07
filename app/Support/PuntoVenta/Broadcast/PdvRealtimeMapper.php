<?php

namespace App\Support\PuntoVenta\Broadcast;

use App\Events\PuntoVenta\AtencionCerrada;
use App\Events\PuntoVenta\AtencionProrroga;
use App\Events\PuntoVenta\EntregaResguardoPdvCompletada;
use App\Events\PuntoVenta\IncidenciaResguardoPdvRegistrada;
use App\Events\PuntoVenta\JornadaAbierta;
use App\Events\PuntoVenta\JornadaAmpliada;
use App\Events\PuntoVenta\JornadaCerrada;
use App\Events\PuntoVenta\JornadaCierreHorario;
use App\Events\PuntoVenta\JornadaCierreManual;
use App\Events\PuntoVenta\PausaFinalizada;
use App\Events\PuntoVenta\PausaIniciada;
use App\Events\PuntoVenta\RecepcionEsperadaPdvCreada;
use App\Events\PuntoVenta\RecepcionFisicaPdvCompletada;
use App\Events\PuntoVenta\TurnoAsignado;
use App\Events\PuntoVenta\TurnoCreado;
use App\Events\PuntoVenta\TurnoReatencion;
use App\Events\PuntoVenta\TurnoTransferido;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Support\PuntoVenta\Broadcast\Payloads\PayloadOperacionPdvBroadcast;
use App\Support\PuntoVenta\Broadcast\Payloads\PayloadResguardoPdvBroadcast;
use App\Support\PuntoVenta\Broadcast\Payloads\PayloadTurnoPdvBroadcast;
use Illuminate\Broadcasting\Channel;

final class PdvRealtimeMapper
{
    /**
     * @return list<class-string>
     */
    public static function eventosSoportados(): array
    {
        return [
            RecepcionEsperadaPdvCreada::class,
            RecepcionFisicaPdvCompletada::class,
            IncidenciaResguardoPdvRegistrada::class,
            EntregaResguardoPdvCompletada::class,
            TurnoCreado::class,
            TurnoAsignado::class,
            TurnoReatencion::class,
            TurnoTransferido::class,
            AtencionCerrada::class,
            AtencionProrroga::class,
            JornadaAbierta::class,
            JornadaCerrada::class,
            JornadaCierreManual::class,
            JornadaCierreHorario::class,
            JornadaAmpliada::class,
            PausaIniciada::class,
            PausaFinalizada::class,
        ];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    public function transmisiones(object $event): array
    {
        return match ($event::class) {
            RecepcionEsperadaPdvCreada::class => $this->recepcionEsperada($event),
            RecepcionFisicaPdvCompletada::class => $this->resguardoSucursal(
                $event->sucursalId,
                PayloadResguardoPdvBroadcast::eventIdDesdeEvento($event->evento),
                $event->evento->tipo_evento,
                $event->resguardo->version,
                PayloadResguardoPdvBroadcast::desdeEvento($event->resguardo, $event->evento),
                $event->evento->ocurrido_at,
            ),
            IncidenciaResguardoPdvRegistrada::class => $this->resguardoSucursal(
                $event->sucursalId,
                PayloadResguardoPdvBroadcast::eventIdDesdeEvento($event->evento),
                $event->evento->tipo_evento,
                $event->resguardo->version,
                PayloadResguardoPdvBroadcast::desdeEvento($event->resguardo, $event->evento),
                $event->evento->ocurrido_at,
            ),
            EntregaResguardoPdvCompletada::class => $this->resguardoSucursal(
                $event->sucursalId,
                PayloadResguardoPdvBroadcast::eventIdDesdeEvento($event->evento),
                $event->evento->tipo_evento,
                $event->resguardo->version,
                PayloadResguardoPdvBroadcast::desdeEvento($event->resguardo, $event->evento),
                $event->evento->ocurrido_at,
            ),
            TurnoCreado::class => $this->turnoSoloSucursal($event),
            TurnoAsignado::class => $this->turnoLlamado($event, TurnoPdvEvento::TIPO_ASIGNADO),
            TurnoReatencion::class => $this->turnoLlamado($event, TurnoPdvEvento::TIPO_REATENCION),
            TurnoTransferido::class => $this->turnoTransferido($event),
            AtencionCerrada::class => $this->turnoAtencionCerrada($event),
            AtencionProrroga::class => $this->turnoProrroga($event),
            JornadaAbierta::class => $this->jornadaAbierta($event),
            JornadaCerrada::class => $this->jornadaCerrada($event),
            JornadaCierreManual::class => $this->cierreManual($event),
            JornadaCierreHorario::class => $this->cierreHorario($event),
            JornadaAmpliada::class => $this->jornadaAmpliada($event),
            PausaIniciada::class => $this->pausa($event, 'pausa.iniciada'),
            PausaFinalizada::class => $this->pausa($event, 'pausa.finalizada'),
            default => [],
        };
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function recepcionEsperada(RecepcionEsperadaPdvCreada $event): array
    {
        return $this->resguardoSucursal(
            $event->sucursalId,
            PayloadResguardoPdvBroadcast::eventIdDesdeHandoff($event->resguardo->id, $event->pedidoBmaId),
            'resguardo.recepcion_esperada_creada',
            $event->resguardo->version,
            PayloadResguardoPdvBroadcast::desdeResguardo($event->resguardo, 'resguardo.recepcion_esperada_creada'),
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function resguardoSucursal(
        int $sucursalId,
        string $eventId,
        string $tipo,
        int $version,
        array $datos,
        mixed $ocurridoAt = null,
    ): array {
        return [[
            'channels' => [CanalesPdv::sucursal($sucursalId)],
            'envelope' => PdvRealtimeEnvelope::crear(
                $eventId,
                $tipo,
                'resguardos',
                'sucursal',
                $sucursalId,
                $version,
                $datos,
                $ocurridoAt,
            ),
        ]];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function turnoSoloSucursal(TurnoCreado $event): array
    {
        return [[
            'channels' => [CanalesPdv::sucursal($event->sucursalId)],
            'envelope' => PdvRealtimeEnvelope::crear(
                PayloadTurnoPdvBroadcast::eventIdDesdeEvento($event->evento),
                TurnoPdvEvento::TIPO_ALTA,
                'turnos',
                'sucursal',
                $event->sucursalId,
                $event->turno->version,
                PayloadTurnoPdvBroadcast::sucursal($event->turno),
                $event->evento->ocurrido_at,
            ),
        ]];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function turnoLlamado(TurnoAsignado|TurnoReatencion $event, string $tipo): array
    {
        $eventId = PayloadTurnoPdvBroadcast::eventIdDesdeEvento($event->evento);
        $atencion = $this->atencionConPersona($event->atencion);

        return [
            [
                'channels' => [CanalesPdv::sucursal($event->sucursalId)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    $tipo,
                    'turnos',
                    'sucursal',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::sucursal($event->turno, $atencion),
                    $event->evento->ocurrido_at,
                ),
            ],
            [
                'channels' => [CanalesPdv::usuario((int) $atencion->user_id)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    $tipo,
                    'turnos',
                    'usuario',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::usuario($event->turno, $atencion),
                    $event->evento->ocurrido_at,
                ),
            ],
            [
                'channels' => [CanalesPdv::turnosPublico($event->sucursalId)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    $tipo,
                    'turnos',
                    'publico',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::publico($event->turno, $atencion),
                    $event->evento->ocurrido_at,
                ),
            ],
        ];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function turnoTransferido(TurnoTransferido $event): array
    {
        $eventId = PayloadTurnoPdvBroadcast::eventIdDesdeEvento($event->evento);
        $atencionNueva = $this->atencionConPersona($event->atencionNueva);

        return [
            [
                'channels' => [CanalesPdv::sucursal($event->sucursalId)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    TurnoPdvEvento::TIPO_TRANSFERIDO,
                    'turnos',
                    'sucursal',
                    $event->sucursalId,
                    $event->turno->version,
                    array_merge(
                        PayloadTurnoPdvBroadcast::sucursal($event->turno, $atencionNueva),
                        ['atencion_anterior_id' => $event->atencionAnterior->id],
                    ),
                    $event->evento->ocurrido_at,
                ),
            ],
            [
                'channels' => [CanalesPdv::usuario((int) $atencionNueva->user_id)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    TurnoPdvEvento::TIPO_TRANSFERIDO,
                    'turnos',
                    'usuario',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::usuario($event->turno, $atencionNueva),
                    $event->evento->ocurrido_at,
                ),
            ],
            [
                'channels' => [CanalesPdv::turnosPublico($event->sucursalId)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    TurnoPdvEvento::TIPO_TRANSFERIDO,
                    'turnos',
                    'publico',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::publico($event->turno, $atencionNueva),
                    $event->evento->ocurrido_at,
                ),
            ],
        ];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function turnoAtencionCerrada(AtencionCerrada $event): array
    {
        $eventId = PayloadTurnoPdvBroadcast::eventIdDesdeEvento($event->evento);
        $atencion = $this->atencionConPersona($event->atencion);
        $transmisiones = [
            [
                'channels' => [CanalesPdv::sucursal($event->sucursalId)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    TurnoPdvEvento::TIPO_ATENCION_CERRADA,
                    'turnos',
                    'sucursal',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::sucursal($event->turno, $atencion),
                    $event->evento->ocurrido_at,
                ),
            ],
            [
                'channels' => [CanalesPdv::turnosPublico($event->sucursalId)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    TurnoPdvEvento::TIPO_ATENCION_CERRADA,
                    'turnos',
                    'publico',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::publico($event->turno),
                    $event->evento->ocurrido_at,
                ),
            ],
        ];

        if ($atencion->user_id !== null) {
            $transmisiones[] = [
                'channels' => [CanalesPdv::usuario((int) $atencion->user_id)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    TurnoPdvEvento::TIPO_ATENCION_CERRADA,
                    'turnos',
                    'usuario',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::usuario($event->turno, $atencion),
                    $event->evento->ocurrido_at,
                ),
            ];
        }

        return $transmisiones;
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function turnoProrroga(AtencionProrroga $event): array
    {
        $eventId = PayloadTurnoPdvBroadcast::eventIdDesdeEvento($event->evento);
        $atencion = $this->atencionConPersona($event->atencion);

        return [
            [
                'channels' => [CanalesPdv::sucursal($event->sucursalId)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    TurnoPdvEvento::TIPO_PRORROGA,
                    'turnos',
                    'sucursal',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::sucursal($event->turno, $atencion),
                    $event->evento->ocurrido_at,
                ),
            ],
            [
                'channels' => [CanalesPdv::usuario((int) $atencion->user_id)],
                'envelope' => PdvRealtimeEnvelope::crear(
                    $eventId,
                    TurnoPdvEvento::TIPO_PRORROGA,
                    'turnos',
                    'usuario',
                    $event->sucursalId,
                    $event->turno->version,
                    PayloadTurnoPdvBroadcast::usuario($event->turno, $atencion),
                    $event->evento->ocurrido_at,
                ),
            ],
        ];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function jornadaAbierta(JornadaAbierta $event): array
    {
        return [[
            'channels' => [CanalesPdv::sucursal($event->sucursalId)],
            'envelope' => PdvRealtimeEnvelope::crear(
                PayloadOperacionPdvBroadcast::eventIdJornada('jornada.abierta', $event->jornada->id),
                'jornada.abierta',
                'operacion',
                'sucursal',
                $event->sucursalId,
                $event->jornada->version,
                [
                    'jornada' => PayloadOperacionPdvBroadcast::jornada($event->jornada),
                    'intervalo' => PayloadOperacionPdvBroadcast::intervalo($event->intervalo),
                ],
            ),
        ]];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function jornadaCerrada(JornadaCerrada $event): array
    {
        return [[
            'channels' => [CanalesPdv::sucursal($event->sucursalId)],
            'envelope' => PdvRealtimeEnvelope::crear(
                PayloadOperacionPdvBroadcast::eventIdJornada('jornada.cerrada', $event->jornada->id),
                'jornada.cerrada',
                'operacion',
                'sucursal',
                $event->sucursalId,
                $event->jornada->version,
                [
                    'jornada' => PayloadOperacionPdvBroadcast::jornada($event->jornada),
                    'alcance' => $event->alcance,
                ],
            ),
        ]];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function cierreManual(JornadaCierreManual $event): array
    {
        return [[
            'channels' => [CanalesPdv::sucursal($event->sucursalId)],
            'envelope' => PdvRealtimeEnvelope::crear(
                PayloadOperacionPdvBroadcast::eventIdSucursalDia('jornada.cierre_manual', $event->sucursalDia->id),
                'jornada.cierre_manual',
                'operacion',
                'sucursal',
                $event->sucursalId,
                $event->sucursalDia->version,
                PayloadOperacionPdvBroadcast::sucursalDia($event->sucursalDia),
            ),
        ]];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function cierreHorario(JornadaCierreHorario $event): array
    {
        return [[
            'channels' => [CanalesPdv::sucursal($event->sucursalId)],
            'envelope' => PdvRealtimeEnvelope::crear(
                PayloadOperacionPdvBroadcast::eventIdDesdeEvento($event->evento),
                $event->evento->tipo_evento,
                'operacion',
                'sucursal',
                $event->sucursalId,
                $event->sucursalDia->version,
                [
                    'sucursal_dia' => PayloadOperacionPdvBroadcast::sucursalDia($event->sucursalDia),
                ],
                $event->evento->ocurrido_at,
            ),
        ]];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function jornadaAmpliada(JornadaAmpliada $event): array
    {
        return [[
            'channels' => [CanalesPdv::sucursal($event->sucursalId)],
            'envelope' => PdvRealtimeEnvelope::crear(
                PayloadOperacionPdvBroadcast::eventIdSucursalDia('jornada.ampliada', $event->sucursalDia->id),
                'jornada.ampliada',
                'operacion',
                'sucursal',
                $event->sucursalId,
                $event->sucursalDia->version,
                PayloadOperacionPdvBroadcast::sucursalDia($event->sucursalDia),
            ),
        ]];
    }

    /**
     * @return list<array{channels: list<Channel>, envelope: array<string, mixed>}>
     */
    private function pausa(PausaIniciada|PausaFinalizada $event, string $tipo): array
    {
        return [[
            'channels' => [CanalesPdv::sucursal($event->sucursalId)],
            'envelope' => PdvRealtimeEnvelope::crear(
                PayloadOperacionPdvBroadcast::eventIdJornada($tipo, $event->jornada->id).':intervalo:'.$event->intervalo->id,
                $tipo,
                'operacion',
                'sucursal',
                $event->sucursalId,
                $event->intervalo->version,
                [
                    'jornada' => PayloadOperacionPdvBroadcast::jornada($event->jornada),
                    'intervalo' => PayloadOperacionPdvBroadcast::intervalo($event->intervalo),
                ],
            ),
        ]];
    }

    private function atencionConPersona(\App\Models\PuntoVenta\TurnoPdvAtencion $atencion): \App\Models\PuntoVenta\TurnoPdvAtencion
    {
        if (! $atencion->relationLoaded('user')) {
            $atencion->load('user');
        }

        return $atencion;
    }
}
