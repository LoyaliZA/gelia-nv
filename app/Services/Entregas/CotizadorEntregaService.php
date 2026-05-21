<?php

namespace App\Services\Entregas;

use App\Models\ConfiguracionEntrega;
use App\Models\CatalogoZonaEntrega;
use Exception;

class CotizadorEntregaService
{
    /**
     * Motor principal de cotización de envíos.
     * Evalúa la ubicación del cliente contra las zonas delimitadas y la distancia máxima.
     * * @param float $latCliente Latitud del punto de entrega.
     * @param float $lonCliente Longitud del punto de entrega.
     * @return array Detalles de la cotización o lanza excepción si está fuera de cobertura.
     */
    public function cotizar(float $latCliente, float $lonCliente): array
    {
        $config = ConfiguracionEntrega::first();

        if (!$config) {
            throw new Exception("La configuración del sistema de entregas no ha sido inicializada.");
        }

        // 1. Validar la distancia máxima permitida (Seguro del Jefe)
        $distanciaKm = $this->calcularDistanciaHaversine(
            $config->latitud_origen,
            $config->longitud_origen,
            $latCliente,
            $lonCliente
        );

        if ($distanciaKm > $config->radio_tolerancia_km) {
            throw new Exception("El domicilio se encuentra fuera de nuestra área máxima de cobertura ({$config->radio_tolerancia_km} km).");
        }

        // 2. Verificar si el cliente pertenece a una zona predefinida (Polígonos)
        $zonas = CatalogoZonaEntrega::where('activo', true)->get();

        foreach ($zonas as $zona) {
            // Se asume estructura estándar de GeoJSON: {"type": "Polygon", "coordinates": [[[lon, lat], [lon, lat], ...]]}
            $coordenadasPoligono = $zona->coordenadas_poligono['coordinates'][0] ?? [];

            if ($this->puntoEnPoligono($lonCliente, $latCliente, $coordenadasPoligono)) {
                return [
                    'es_valido' => true,
                    'zona_id' => $zona->id,
                    'nombre_zona' => $zona->nombre,
                    'costo_envio' => $zona->costo_base,
                    'distancia_km' => round($distanciaKm, 2),
                    'tipo_tarifa' => 'zona_base'
                ];
            }
        }

        // 3. Cálculo Híbrido: Está dentro de los 12km de tolerancia, pero no en un polígono (Zona Extra)
        $costoFinal = $this->calcularCostoPeriferia($config, $distanciaKm);

        return [
            'es_valido' => true,
            'zona_id' => null,
            'nombre_zona' => 'Zona Extendida (Periferia)',
            'costo_envio' => $costoFinal,
            'distancia_km' => round($distanciaKm, 2),
            'tipo_tarifa' => 'zona_extra'
        ];
    }

    /**
     * Calcula el costo de la tarifa en la zona extendida evaluando las banderas comerciales.
     */
    private function calcularCostoPeriferia(ConfiguracionEntrega $config, float $distanciaKm): float
    {
        // Intervención preparada para uso de API de Google en el futuro
        if ($config->usar_api_distancia) {
            // Aquí se inyectaría la petición al servicio externo (Distance Matrix API).
            // De momento priorizamos el cálculo interno.
        }

        // Si la gerencia activa el cobro por km extra, multiplicamos. Si no, aplicamos tarifa plana.
        if ($config->cobro_extra_por_km) {
            return $distanciaKm * $config->tarifa_envio_extra;
        }

        return (float) $config->tarifa_envio_extra;
    }

    /**
     * Calcula la distancia en línea recta entre dos coordenadas geográficas.
     */
    private function calcularDistanciaHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radioTierra = 6371; // Radio medio de la Tierra en kilómetros

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * asin(sqrt($a));

        return $radioTierra * $c;
    }

    /**
     * Algoritmo de Ray-Casting para determinar si una coordenada está dentro de un polígono.
     * * @param float $x Longitud a evaluar (Eje X)
     * @param float $y Latitud a evaluar (Eje Y)
     * @param array $poligono Arreglo de vértices del polígono [[lon, lat], [lon, lat]]
     * @return bool True si el punto está dentro, False en caso contrario.
     */
    private function puntoEnPoligono(float $x, float $y, array $poligono): bool
    {
        $intersecciones = 0;
        $vertices = count($poligono);
        $j = $vertices - 1; // El último vértice se conecta con el primero

        for ($i = 0; $i < $vertices; $i++) {
            $xi = $poligono[$i][0]; // Longitud del vértice actual
            $yi = $poligono[$i][1]; // Latitud del vértice actual
            $xj = $poligono[$j][0]; // Longitud del vértice anterior
            $yj = $poligono[$j][1]; // Latitud del vértice anterior

            // Comprueba si el rayo horizontal lanzado desde el punto interseca el segmento de línea
            $intersecta = (($yi > $y) != ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);
            
            if ($intersecta) {
                $intersecciones++;
            }
            
            $j = $i;
        }

        // Si las intersecciones son impares, el punto se encuentra dentro del área
        return ($intersecciones % 2 != 0);
    }
}