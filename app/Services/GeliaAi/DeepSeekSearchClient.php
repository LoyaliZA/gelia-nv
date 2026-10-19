<?php

namespace App\Services\GeliaAi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Search es un endpoint independiente: no sustituye ni selecciona el modelo del chat. */
class DeepSeekSearchClient
{
    private int $busquedas = 0;
    public function __construct(private DeepSeekClient $client) {}

    public function buscarProducto(string $nombre, string $marca): array
    {
        $nombre = trim(preg_replace('/^\s*[CDU]\s+|\b\d+(?:[.,]\d+)?\s*(?:ML|OZ)\b\.?/iu', ' ', $nombre) ?? $nombre);
        $identidad = trim($marca.' '.$nombre);
        $dominio = null;
        foreach ((array) config('gelia_ai.recomendaciones.fabricantes', []) as $fabricante) {
            foreach ($fabricante['aliases'] as $alias) {
                if (mb_stripos($identidad, $alias) !== false) {
                    $nombre = str_ireplace($fabricante['aliases'], '', $nombre);
                    $identidad = $fabricante['nombre'].' '.$nombre;
                    $dominio = $fabricante['dominio'];
                    break 2;
                }
            }
        }
        $identidad = trim(preg_replace('/[\p{C}\s]+/u', ' ', $identidad) ?? '');
        if ($dominio !== null) {
            $primaria = $this->buscar($identidad, $dominio);
            if (! $primaria['ok'] || $primaria['fuentes'] !== []) {
                return $primaria;
            }
        }

        return $this->buscar($identidad, null);
    }

    private function buscar(string $identidad, ?string $dominio): array
    {
        if (! config('gelia_ai.recomendaciones.search_enabled', true)) {
            return ['ok' => false, 'error_code' => 'busqueda_desactivada', 'error' => 'La búsqueda en internet está desactivada.'];
        }
        if ($this->client->apiToken() === '') {
            return ['ok' => false, 'error_code' => 'busqueda_sin_configurar', 'error' => 'Falta configurar la conexión para investigar productos.'];
        }
        if ($this->busquedas >= 3) {
            return ['ok' => false, 'error_code' => 'limite_busquedas', 'error' => 'Se alcanzó el límite de tres investigaciones web en esta consulta. Compara los perfiles obtenidos o pide investigar otro producto en el siguiente mensaje.'];
        }

        // Solo datos públicos del producto. Nunca se envía la pregunta, usuario, almacén, precios ni stock.
        $sufijo = ' perfume notes'.($dominio !== null ? ' site:'.$dominio : '');
        $query = mb_substr($identidad, 0, 128 - mb_strlen($sufijo)).$sufijo;
        $this->busquedas++;
        try {
            $response = Http::baseUrl(rtrim((string) config('gelia_ai.recomendaciones.search_base_url', 'https://api.deepseek.com'), '/'))
                ->withToken($this->client->apiToken())
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(max(1, min(10, (int) config('gelia_ai.recomendaciones.search_timeout_seconds', 8))))
                ->post('/v1/tools/search', ['query' => $query, 'count' => 3, 'need_content' => true]);
        } catch (ConnectionException) {
            return ['ok' => false, 'error_code' => 'busqueda_timeout', 'error' => 'La búsqueda no respondió a tiempo. Se conservan los datos del catálogo.'];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error_code' => 'busqueda_no_disponible', 'error' => 'No fue posible investigar el producto en internet. No hay información externa verificada.'];
        }
        $results = $response->json('results');
        if (! is_array($results)) {
            return ['ok' => false, 'error_code' => 'busqueda_respuesta_invalida', 'error' => 'El buscador no devolvió resultados utilizables.'];
        }

        $fuentes = [];
        foreach (array_slice($results, 0, 3) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = (string) ($row['url'] ?? '');
            if (mb_strlen($url) > 500 || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                continue;
            }
            $texto = trim((string) ($row['summary'] ?? '')) ?: trim((string) ($row['content'] ?? '')) ?: trim((string) ($row['snippet'] ?? ''));
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($dominio !== null && $host !== $dominio && ! str_ends_with($host, '.'.$dominio)) {
                continue;
            }
            // Una variante numérica debe aparecer en el título o URL, no solo en un menú de navegación.
            preg_match_all('/\b\d+[.,]\d+\b/u', $identidad, $versiones);
            $tituloUrl = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) ($row['title'] ?? '').' '.urldecode($url)));
            foreach ($versiones[0] as $version) {
                $version = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $version);
                if (! str_contains(' '.$tituloUrl.' ', ' '.$version.' ')) {
                    continue 2;
                }
            }
            if ($texto === '') {
                continue;
            }
            $fuentes[] = [
                'titulo' => mb_substr(strip_tags((string) ($row['title'] ?? 'Fuente del producto')), 0, 120),
                'url' => $url,
                'extracto' => mb_substr(strip_tags($texto), 0, 900),
                'tipo' => $dominio !== null ? 'fabricante' : 'referencia_externa',
            ];
            if (count($fuentes) === 2) {
                break;
            }
        }

        return ['ok' => true, 'fuentes' => $fuentes, 'investigado_en' => now()->toIso8601String()];
    }
}
