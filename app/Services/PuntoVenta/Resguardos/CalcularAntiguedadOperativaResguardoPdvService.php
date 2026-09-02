<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelvePlazosCustodiaResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Carbon\Carbon;

class CalcularAntiguedadOperativaResguardoPdvService
{
    public function __construct(
        private readonly ResuelvePlazosCustodiaResguardoPdv $plazos,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function clasificacionesVacias(): array
    {
        return [
            AntiguedadOperativaResguardoPdv::REZAGADO => false,
            AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER => false,
            AntiguedadOperativaResguardoPdv::VENCIDO => false,
        ];
    }

    /**
     * @return array{
     *   clasificaciones: array<string, bool>,
     *   fecha_limite_custodia: string|null,
     *   fecha_limite_rezago: string|null,
     *   plazos_snapshot: array<string, mixed>|null
     * }
     */
    public function evaluar(ResguardoPdv $resguardo, ?Carbon $ahora = null): array
    {
        $config = $this->plazos->resolverParaSucursal($resguardo->sucursal_id);
        if ($config === null) {
            return [
                'clasificaciones' => $this->clasificacionesVacias(),
                'fecha_limite_custodia' => null,
                'fecha_limite_rezago' => null,
                'plazos_snapshot' => null,
            ];
        }

        $zona = $config['zona_horaria'];
        $ahora = ($ahora ?? now())->copy()->timezone($zona);

        $clasificaciones = $this->clasificacionesVacias();
        $fechaLimiteCustodia = null;
        $fechaLimiteRezago = null;

        if ($this->admiteRezago($resguardo)) {
            $anclaRezago = $resguardo->salida_cedis_at?->copy()->timezone($zona);
            if ($anclaRezago !== null) {
                $fechaLimiteRezago = $this->fechaLimiteDesdeAncla($anclaRezago, (int) $config['rezago_dias'], $config);
                if ($ahora->gt($fechaLimiteRezago)) {
                    $clasificaciones[AntiguedadOperativaResguardoPdv::REZAGADO] = true;
                }
            }
        }

        if ($this->admiteCustodia($resguardo)) {
            $anclaCustodia = $resguardo->recepcion_fisica_at?->copy()->timezone($zona);
            if ($anclaCustodia !== null) {
                $fechaLimiteCustodia = $this->fechaLimiteDesdeAncla(
                    $anclaCustodia,
                    (int) $config['custodia_dias'],
                    $config
                );

                if ($ahora->gt($fechaLimiteCustodia)) {
                    $clasificaciones[AntiguedadOperativaResguardoPdv::VENCIDO] = true;
                } elseif ($this->diasRestantesHasta($ahora, $fechaLimiteCustodia, $config)
                    <= (int) $config['aviso_previo_dias']) {
                    $clasificaciones[AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER] = true;
                }
            }
        }

        return [
            'clasificaciones' => $clasificaciones,
            'fecha_limite_custodia' => $fechaLimiteCustodia?->toIso8601String(),
            'fecha_limite_rezago' => $fechaLimiteRezago?->toIso8601String(),
            'plazos_snapshot' => $config,
        ];
    }

    public function coincideConFiltro(ResguardoPdv $resguardo, string $antiguedad, ?Carbon $ahora = null): bool
    {
        $evaluacion = $this->evaluar($resguardo, $ahora);

        return (bool) ($evaluacion['clasificaciones'][$antiguedad] ?? false);
    }

    public function debeExcluirDeVistaPrincipal(ResguardoPdv $resguardo, ?Carbon $ahora = null): bool
    {
        if ($resguardo->vencido_repuesto_at !== null) {
            return false;
        }

        $evaluacion = $this->evaluar($resguardo, $ahora);

        return (bool) $evaluacion['clasificaciones'][AntiguedadOperativaResguardoPdv::VENCIDO];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function fechaLimiteDesdeAncla(Carbon $ancla, int $diasPlazo, array $config): Carbon
    {
        $zona = (string) $config['zona_horaria'];
        $ancla = $ancla->copy()->timezone($zona);
        $dias = max(1, $diasPlazo);

        if (($config['tipo_dias'] ?? PlazosCustodiaResguardoPdvConfig::TIPO_DIAS_HABILES) === PlazosCustodiaResguardoPdvConfig::TIPO_DIAS_NATURALES) {
            return $ancla->copy()->addDays($dias)->endOfDay();
        }

        return $this->sumarDiasHabiles($ancla, $dias, $config)->endOfDay();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function diasRestantesHasta(Carbon $desde, Carbon $hasta, array $config): int
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        if ($desde->gte($hasta)) {
            return 0;
        }

        if (($config['tipo_dias'] ?? PlazosCustodiaResguardoPdvConfig::TIPO_DIAS_HABILES) === PlazosCustodiaResguardoPdvConfig::TIPO_DIAS_NATURALES) {
            return max(0, (int) $desde->diffInDays($hasta));
        }

        $cursor = $desde->copy();
        $restantes = 0;

        while ($cursor->lt($hasta)) {
            $cursor->addDay();
            if ($this->esDiaHabil($cursor, $config)) {
                $restantes++;
            }
        }

        return $restantes;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function sumarDiasHabiles(Carbon $desde, int $dias, array $config): Carbon
    {
        $cursor = $desde->copy();
        $restantes = $dias;

        while ($restantes > 0) {
            $cursor->addDay();
            if ($this->esDiaHabil($cursor, $config)) {
                $restantes--;
            }
        }

        return $cursor;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function esDiaHabil(Carbon $fecha, array $config): bool
    {
        return in_array((int) $fecha->isoWeekday(), $config['dias_habiles'], true);
    }

    private function admiteRezago(ResguardoPdv $resguardo): bool
    {
        return $resguardo->estado === ResguardoPdv::ESTADO_PENDIENTE_RECEPCION
            && $resguardo->recepcion_fisica_at === null
            && $resguardo->salida_cedis_at !== null;
    }

    private function admiteCustodia(ResguardoPdv $resguardo): bool
    {
        return $resguardo->estado === ResguardoPdv::ESTADO_EN_CUSTODIA
            && $resguardo->recepcion_fisica_at !== null
            && $resguardo->entrega_completada_at === null
            && $resguardo->devolucion_confirmada_at === null;
    }
}
