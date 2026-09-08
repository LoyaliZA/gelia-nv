<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\EquipoAsistenciaDiaPdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\EstadoVendedorOperacionPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Carbon\CarbonInterface;

class ResolverEstadoVendedorOperacionPdvService
{
    public function __construct(
        private readonly OperacionPdvConfig $config,
    ) {}

    public function resolver(
        int $sucursalId,
        ?JornadaPdv $jornada,
        ?IntervaloOperativoPdv $intervalo,
        bool $tieneAtencionAbierta,
        ?EquipoAsistenciaDiaPdv $asistencia,
        ?CarbonInterface $ahora = null,
    ): EstadoVendedorOperacionPdv {
        if ($asistencia instanceof EquipoAsistenciaDiaPdv && $asistencia->estaMarcadoNoLlego()) {
            return EstadoVendedorOperacionPdv::NoLlego;
        }

        if ($jornada instanceof JornadaPdv
            && $jornada->estado === EstadoJornadaPdv::CerradaConAtencion) {
            return EstadoVendedorOperacionPdv::CierrePendiente;
        }

        if ($tieneAtencionAbierta) {
            return EstadoVendedorOperacionPdv::Atendiendo;
        }

        if ($jornada instanceof JornadaPdv) {
            if ($jornada->estado === EstadoJornadaPdv::Abierta) {
                if ($intervalo?->tipo === TipoIntervaloOperativoPdv::EnPausa) {
                    return EstadoVendedorOperacionPdv::EnRetencion;
                }

                return EstadoVendedorOperacionPdv::Disponible;
            }

            if ($jornada->estado === EstadoJornadaPdv::Cerrada
                && $this->esJornadaDelDiaOperativo($jornada, $sucursalId, $ahora)) {
                return EstadoVendedorOperacionPdv::JornadaCerrada;
            }
        }

        return EstadoVendedorOperacionPdv::NoActivado;
    }

    /**
     * @return list<string>
     */
    public function accionesDisponibles(EstadoVendedorOperacionPdv $estado): array
    {
        return match ($estado) {
            EstadoVendedorOperacionPdv::NoActivado => ['activar', 'no_llego'],
            EstadoVendedorOperacionPdv::NoLlego => ['activar'],
            EstadoVendedorOperacionPdv::Disponible => ['desactivar', 'pausa_iniciar', 'cerrar_jornada'],
            EstadoVendedorOperacionPdv::Atendiendo => ['cerrar_jornada'],
            EstadoVendedorOperacionPdv::EnRetencion => ['pausa_finalizar', 'desactivar', 'cerrar_jornada'],
            EstadoVendedorOperacionPdv::CierrePendiente => ['cancelar_cierre_pendiente'],
            EstadoVendedorOperacionPdv::JornadaCerrada => ['reactivar'],
        };
    }

    /**
     * @return array{etiqueta: string, referencia_at: string, modo: string}|null
     */
    public function serializarCronometro(
        EstadoVendedorOperacionPdv $estado,
        ?JornadaPdv $jornada,
        ?IntervaloOperativoPdv $intervalo,
    ): ?array {
        if ($estado === EstadoVendedorOperacionPdv::EnRetencion
            && $intervalo?->inicio_at !== null) {
            return [
                'etiqueta' => 'Tiempo en pausa',
                'referencia_at' => $intervalo->inicio_at->toIso8601String(),
                'modo' => 'transcurrido',
            ];
        }

        if ($estado === EstadoVendedorOperacionPdv::Disponible
            && $intervalo?->inicio_at !== null
            && $intervalo->tipo === TipoIntervaloOperativoPdv::Disponible) {
            return [
                'etiqueta' => 'Tiempo disponible',
                'referencia_at' => $intervalo->inicio_at->toIso8601String(),
                'modo' => 'transcurrido',
            ];
        }

        if ($estado === EstadoVendedorOperacionPdv::Disponible
            && $jornada?->apertura_at !== null) {
            return [
                'etiqueta' => 'Jornada abierta',
                'referencia_at' => $jornada->apertura_at->toIso8601String(),
                'modo' => 'transcurrido',
            ];
        }

        if ($estado === EstadoVendedorOperacionPdv::CierrePendiente
            && $jornada?->cierre_at !== null) {
            return [
                'etiqueta' => 'Jornada cerrada',
                'referencia_at' => $jornada->cierre_at->toIso8601String(),
                'modo' => 'transcurrido',
            ];
        }

        return null;
    }

    private function esJornadaDelDiaOperativo(
        JornadaPdv $jornada,
        int $sucursalId,
        ?CarbonInterface $ahora,
    ): bool {
        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);
        $aperturaLocal = $jornada->apertura_at?->copy()
            ->timezone($this->config->zonaHorariaOperativa($sucursalId))
            ->toDateString();

        return $aperturaLocal === $fechaOperativa;
    }
}
