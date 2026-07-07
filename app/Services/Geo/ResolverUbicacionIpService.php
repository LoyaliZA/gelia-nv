<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResolverUbicacionIpService
{
    /**
     * @return array{ciudad: ?string, region: ?string, pais: ?string}
     */
    public function resolver(?string $ip): array
    {
        $vacio = ['ciudad' => null, 'region' => null, 'pais' => null];

        if ($ip === null || $ip === '' || $this->esIpPrivada($ip)) {
            return $vacio;
        }

        return Cache::remember("geo_ip:{$ip}", now()->addDay(), function () use ($ip, $vacio) {
            try {
                $response = Http::timeout(3)
                    ->get("http://ip-api.com/json/{$ip}", [
                        'fields' => 'status,city,regionName,country',
                    ]);

                if (!$response->successful()) {
                    return $vacio;
                }

                $data = $response->json();

                if (($data['status'] ?? '') !== 'success') {
                    return $vacio;
                }

                return [
                    'ciudad' => $data['city'] ?? null,
                    'region' => $data['regionName'] ?? null,
                    'pais'   => $data['country'] ?? null,
                ];
            } catch (\Throwable $e) {
                Log::debug('ResolverUbicacionIpService: ' . $e->getMessage());

                return $vacio;
            }
        });
    }

    public function formatearUbicacion(?string $ciudad, ?string $region, ?string $pais): ?string
    {
        $partes = array_filter([$ciudad, $region, $pais]);

        return $partes !== [] ? implode(', ', $partes) : null;
    }

    private function esIpPrivada(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
