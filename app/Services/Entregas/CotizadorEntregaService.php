<?php

namespace App\Services\Entregas;

use App\Models\CatalogoZonaEntrega;
use App\Models\CatalogoZonaEntregaOverride;
use App\Models\CatalogoZonaRestringida;
use App\Models\ConfiguracionEntrega;
use Exception;
use Illuminate\Support\Collection;

class CotizadorEntregaService
{
    public function cotizar(float $latCliente, float $lonCliente): array
    {
        $config = ConfiguracionEntrega::first();

        if (!$config) {
            throw new Exception('La configuración del sistema de entregas no ha sido inicializada.');
        }

        $zonasRestringidas = CatalogoZonaRestringida::where('activo', true)->get();

        foreach ($zonasRestringidas as $zr) {
            $poligonoRestringido = $zr->coordenadas_poligono['coordinates'][0] ?? [];

            if ($this->puntoEnPoligono($lonCliente, $latCliente, $poligonoRestringido)) {
                throw new Exception('Lo sentimos, no hay cobertura en esta zona por políticas de acceso y rutas federales.');
            }
        }

        $distanciaKm = $this->calcularDistanciaHaversine(
            (float) $config->latitud_origen,
            (float) $config->longitud_origen,
            $latCliente,
            $lonCliente
        );

        if ($distanciaKm > (float) $config->radio_tolerancia_km) {
            throw new Exception("El domicilio se encuentra fuera de nuestra área máxima de cobertura ({$config->radio_tolerancia_km} km).");
        }

        $zonas = CatalogoZonaEntrega::where('activo', true)->with(['horarios' => fn ($q) => $q->where('activo', true)->orderBy('hora_inicio')])->get();

        $override = $this->buscarAsignacionEspecial($lonCliente, $latCliente);

        if ($override) {
            $zonaReferencia = $override->zonaReferencia;

            if (!$zonaReferencia || !$zonaReferencia->activo) {
                throw new Exception('La zona de referencia para esta asignación especial no está disponible.');
            }

            return $this->construirRespuestaZona(
                $zonaReferencia,
                $distanciaKm,
                "Asignación especial: {$override->nombre} (horario {$zonaReferencia->nombre})",
                'asignacion_especial',
                $override->nombre
            );
        }

        foreach ($zonas as $zona) {
            $coordenadasPoligono = $zona->coordenadas_poligono['coordinates'][0] ?? [];

            if ($this->puntoEnPoligono($lonCliente, $latCliente, $coordenadasPoligono)) {
                return $this->construirRespuestaZona(
                    $zona,
                    $distanciaKm,
                    $zona->nombre,
                    'zona_base'
                );
            }
        }

        $zonaPeriferia = $this->resolverZonaPeriferia(
            $latCliente,
            $lonCliente,
            (float) $config->latitud_origen,
            (float) $config->longitud_origen,
            $zonas
        );

        if (!$zonaPeriferia) {
            throw new Exception('No fue posible determinar una zona de referencia para esta ubicación.');
        }

        $costoFinal = $this->calcularCostoPeriferia($config, $distanciaKm);

        return [
            'es_valido' => true,
            'zona_id' => null,
            'zona_referencia_id' => $zonaPeriferia->id,
            'nombre_zona' => "Periferia extendida (horario {$zonaPeriferia->nombre})",
            'costo_envio' => $costoFinal,
            'distancia_km' => round($distanciaKm, 2),
            'tipo_tarifa' => 'zona_extra',
            'horarios' => $this->formatearHorarios($zonaPeriferia),
        ];
    }

    /**
     * Asigna horario de periferia según sector geográfico desde el km 0 (mapa operativo).
     */
    private function resolverZonaPeriferia(
        float $lat,
        float $lon,
        float $latOrigen,
        float $lonOrigen,
        Collection $zonas
    ): ?CatalogoZonaEntrega {
        $rumbo = $this->calcularRumboGrados($latOrigen, $lonOrigen, $lat, $lon);
        $nombreZona = $this->nombreZonaPorSectorPeriferia($rumbo);

        if ($nombreZona) {
            $zona = $zonas->firstWhere('nombre', $nombreZona);
            if ($zona) {
                return $zona;
            }
        }

        return $this->encontrarZonaMasCercana($lat, $lon, $zonas);
    }

    private function nombreZonaPorSectorPeriferia(float $rumboGrados): ?string
    {
        $sectores = config('entregas.periferia_sectores', []);

        foreach ($sectores as $sector) {
            $min = (float) ($sector['min'] ?? 0);
            $max = (float) ($sector['max'] ?? 360);
            $zona = $sector['zona'] ?? null;

            if (!$zona) {
                continue;
            }

            if ($min <= $max) {
                if ($rumboGrados >= $min && $rumboGrados < $max) {
                    return $zona;
                }
            } elseif ($rumboGrados >= $min || $rumboGrados < $max) {
                return $zona;
            }
        }

        return null;
    }

    /**
     * Rumbo en grados (0° = Norte, 90° = Este), sentido horario.
     */
    private function calcularRumboGrados(float $latOrigen, float $lonOrigen, float $lat, float $lon): float
    {
        $lat1 = deg2rad($latOrigen);
        $lat2 = deg2rad($lat);
        $dLon = deg2rad($lon - $lonOrigen);

        $y = sin($dLon) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);
        $rumbo = rad2deg(atan2($y, $x));

        return fmod($rumbo + 360, 360);
    }

    private function buscarAsignacionEspecial(float $lon, float $lat): ?CatalogoZonaEntregaOverride
    {
        $asignaciones = CatalogoZonaEntregaOverride::where('activo', true)
            ->with('zonaReferencia')
            ->orderBy('prioridad')
            ->get();

        foreach ($asignaciones as $asignacion) {
            $poligono = $asignacion->coordenadas_poligono['coordinates'][0] ?? [];

            if ($this->puntoEnPoligono($lon, $lat, $poligono)) {
                return $asignacion;
            }
        }

        return null;
    }

    private function encontrarZonaMasCercana(float $lat, float $lon, Collection $zonas): ?CatalogoZonaEntrega
    {
        $zonaSeleccionada = null;
        $distanciaMinima = PHP_FLOAT_MAX;

        foreach ($zonas as $zona) {
            $poligono = $zona->coordenadas_poligono['coordinates'][0] ?? [];
            if (count($poligono) < 3) {
                continue;
            }

            [$centroLon, $centroLat] = $this->centroidePoligono($poligono);
            $distancia = $this->calcularDistanciaHaversine($lat, $lon, $centroLat, $centroLon);

            if ($distancia < $distanciaMinima) {
                $distanciaMinima = $distancia;
                $zonaSeleccionada = $zona;
            }
        }

        return $zonaSeleccionada;
    }

    private function construirRespuestaZona(
        CatalogoZonaEntrega $zona,
        float $distanciaKm,
        string $nombreMostrado,
        string $tipoTarifa,
        ?string $etiquetaEspecial = null
    ): array {
        return [
            'es_valido' => true,
            'zona_id' => $zona->id,
            'zona_referencia_id' => $zona->id,
            'nombre_zona' => $nombreMostrado,
            'etiqueta_especial' => $etiquetaEspecial,
            'costo_envio' => (float) $zona->costo_base,
            'distancia_km' => round($distanciaKm, 2),
            'tipo_tarifa' => $tipoTarifa,
            'horarios' => $this->formatearHorarios($zona),
        ];
    }

    private function formatearHorarios(CatalogoZonaEntrega $zona): array
    {
        $horarios = $zona->relationLoaded('horarios')
            ? $zona->horarios
            : $zona->horarios()->where('activo', true)->orderBy('hora_inicio')->get();

        return $horarios->map(function ($horario) {
            return [
                'hora_inicio' => (string) $horario->hora_inicio,
                'hora_fin' => (string) $horario->hora_fin,
            ];
        })->values()->all();
    }

    private function centroidePoligono(array $poligono): array
    {
        $sumLon = 0.0;
        $sumLat = 0.0;
        $vertices = count($poligono);

        if ($vertices === 0) {
            return [0.0, 0.0];
        }

        foreach ($poligono as $punto) {
            $sumLon += (float) $punto[0];
            $sumLat += (float) $punto[1];
        }

        return [$sumLon / $vertices, $sumLat / $vertices];
    }

    private function calcularCostoPeriferia(ConfiguracionEntrega $config, float $distanciaKm): float
    {
        if ($config->usar_api_distancia) {
            // Reservado para Distance Matrix API.
        }

        if ($config->cobro_extra_por_km) {
            return $distanciaKm * (float) $config->tarifa_envio_extra;
        }

        return (float) $config->tarifa_envio_extra;
    }

    private function calcularDistanciaHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radioTierra = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * asin(sqrt($a));

        return $radioTierra * $c;
    }

    private function puntoEnPoligono(float $x, float $y, array $poligono): bool
    {
        if (count($poligono) < 3) {
            return false;
        }

        $intersecciones = 0;
        $vertices = count($poligono);
        $j = $vertices - 1;

        for ($i = 0; $i < $vertices; $i++) {
            $xi = $poligono[$i][0];
            $yi = $poligono[$i][1];
            $xj = $poligono[$j][0];
            $yj = $poligono[$j][1];

            $intersecta = (($yi > $y) != ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);

            if ($intersecta) {
                $intersecciones++;
            }

            $j = $i;
        }

        return ($intersecciones % 2) != 0;
    }
}
