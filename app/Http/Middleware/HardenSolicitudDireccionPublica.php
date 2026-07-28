<?php

namespace App\Http\Middleware;

use App\Support\FormPublicUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class HardenSolicitudDireccionPublica
{
    public function handle(Request $request, Closure $next): Response
    {
        // AppServiceProvider fuerza APP_URL → @vite emite type=module absolutos a gelianv.
        // En el host del form eso dispara CORS. Servir assets same-origin (form ya tiene /build/).
        $formBase = rtrim(FormPublicUrl::base(), '/');
        $formHost = FormPublicUrl::host();
        if ($formBase !== '' && $formHost !== null && strcasecmp($request->getHost(), $formHost) === 0) {
            URL::forceRootUrl($formBase);
            $scheme = parse_url($formBase, PHP_URL_SCHEME);
            if (is_string($scheme) && $scheme !== '') {
                URL::forceScheme($scheme);
            }
        }

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $scriptSrc = ["'self'", "'unsafe-inline'"];
        $styleSrc = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $fontSrc = ["'self'", 'data:', 'https://fonts.gstatic.com'];
        $connectSrc = ["'self'"];
        $imgSrc = ["'self'", 'data:', 'blob:'];

        // Cloudflare Web Analytics inyecta beacon en el edge; no lo controla la app.
        $scriptSrc[] = 'https://static.cloudflareinsights.com';
        $connectSrc[] = 'https://static.cloudflareinsights.com';

        // APP_URL residual (CSS/font legacy o assets si forceRootUrl falla).
        foreach ($this->appAssetOrigins() as $origin) {
            $scriptSrc[] = $origin;
            $styleSrc[] = $origin;
            $fontSrc[] = $origin;
            $imgSrc[] = $origin;
            $connectSrc[] = $origin;
        }

        // Sail/Vite HMR + Reverb: solo en local/debug (el form público no inicializa Echo).
        if (app()->isLocal() || (bool) config('app.debug')) {
            foreach ($this->viteOrigins() as $origin) {
                $scriptSrc[] = $origin;
                $styleSrc[] = $origin;
                $connectSrc[] = $origin;
                $connectSrc[] = preg_replace('#^http://#', 'ws://', $origin) ?? $origin;
                $connectSrc[] = preg_replace('#^https://#', 'wss://', $origin) ?? $origin;
                $imgSrc[] = $origin;
            }

            foreach ($this->reverbOrigins() as $origin) {
                $connectSrc[] = $origin;
            }
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', array_unique($scriptSrc)),
            'style-src '.implode(' ', array_unique($styleSrc)),
            'img-src '.implode(' ', array_unique($imgSrc)),
            'font-src '.implode(' ', array_unique($fontSrc)),
            'connect-src '.implode(' ', array_unique($connectSrc)),
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
        ]);
    }

    /**
     * Orígenes de build Vite/APP_URL cuando el form se sirve en otro host.
     *
     * @return list<string>
     */
    private function appAssetOrigins(): array
    {
        $origins = [];
        foreach ([(string) config('app.url'), (string) env('ASSET_URL', '')] as $url) {
            $origin = $this->originFromUrl($url);
            if ($origin !== null) {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    private function originFromUrl(string $url): ?string
    {
        $url = rtrim($url, '/');
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    /**
     * @return list<string>
     */
    private function viteOrigins(): array
    {
        $port = (string) config('app.vite_port', '5173');
        $origins = [];

        // Mismo origen que @vite (public/hot): gana sobre el puerto configurado.
        $hotPath = public_path('hot');
        $hotRaw = is_readable($hotPath) ? trim((string) file_get_contents($hotPath)) : null;
        if (is_string($hotRaw) && $hotRaw !== '') {
            $hotOrigin = $this->originFromUrl($hotRaw);
            if ($hotOrigin !== null) {
                $origins[] = $hotOrigin;
            }
            $hotPort = parse_url($hotRaw, PHP_URL_PORT);
            if ($hotPort) {
                $port = (string) $hotPort;
            }
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
            $origins[] = "{$scheme}://{$appHost}:{$port}";
        }

        $origins[] = "http://127.0.0.1:{$port}";
        $origins[] = "http://localhost:{$port}";

        return array_values(array_unique($origins));
    }

    /**
     * Orígenes Echo/Reverb (ws/wss) usados por pusher-js en local.
     *
     * @return list<string>
     */
    private function reverbOrigins(): array
    {
        $host = (string) (env('VITE_REVERB_HOST') ?: env('REVERB_HOST') ?: '');
        $port = (string) (env('VITE_REVERB_PORT') ?: env('REVERB_PORT') ?: '8080');
        $scheme = strtolower((string) (env('VITE_REVERB_SCHEME') ?: env('REVERB_SCHEME') ?: 'http'));

        if ($host === '') {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $host = is_string($appHost) && $appHost !== '' ? $appHost : '127.0.0.1';
        }

        $wsScheme = $scheme === 'https' ? 'wss' : 'ws';
        $httpScheme = $scheme === 'https' ? 'https' : 'http';

        // Echo a veces fuerza wss aunque VITE_REVERB_SCHEME=http; permitir ambos.
        return array_values(array_unique([
            "{$wsScheme}://{$host}:{$port}",
            "{$httpScheme}://{$host}:{$port}",
            "wss://{$host}:{$port}",
            "ws://{$host}:{$port}",
            "https://{$host}:{$port}",
            "http://{$host}:{$port}",
            "wss://127.0.0.1:{$port}",
            "ws://127.0.0.1:{$port}",
            "http://127.0.0.1:{$port}",
            "wss://localhost:{$port}",
            "ws://localhost:{$port}",
            "http://localhost:{$port}",
        ]));
    }
}
