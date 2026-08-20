<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBmaSesionEvidenciaFoto;
use App\Services\ControlPedidos\SesionEvidenciaCedisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class PedidoBmaEvidenciaPublicaController extends Controller
{
    public function show(Request $request, string $codigo, SesionEvidenciaCedisService $service): Response
    {
        try {
            $sesion = $this->abrirSesion($request, $codigo, $service);
        } catch (ValidationException $e) {
            return Inertia::render('ControlPedidos/Cedis/EvidenciaPublica/Show', [
                'codigo' => $codigo,
                'error' => $e->errors()['codigo'][0] ?? 'El enlace no es válido.',
            ])->toResponse($request);
        }

        return $this->pagina($request, $sesion, $service)
            ->toResponse($request)
            ->withCookie($this->cookieReclamo($request, $sesion, $service));
    }

    public function estado(Request $request, string $codigo, SesionEvidenciaCedisService $service): JsonResponse
    {
        $sesion = $this->abrirSesion($request, $codigo, $service);

        return response()->json($service->payloadPublico($sesion))
            ->withCookie($this->cookieReclamo($request, $sesion, $service));
    }

    public function subir(Request $request, string $codigo, SesionEvidenciaCedisService $service): JsonResponse
    {
        $sesion = $this->abrirSesion($request, $codigo, $service);
        $datos = $request->validate([
            'foto' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'objetivo_tipo' => ['required', 'in:producto,caja'],
            'objetivo_uuid' => ['required', 'string', 'max:64'],
            'indice_caja' => ['nullable', 'integer', 'min:0'],
        ]);

        $foto = $service->subirFoto(
            $sesion,
            $request->file('foto'),
            $datos['objetivo_tipo'],
            $datos['objetivo_uuid'],
            isset($datos['indice_caja']) ? (int) $datos['indice_caja'] : null,
            $request->ip() ?? '',
            (string) $request->userAgent()
        );

        return response()->json([
            'foto' => $service->fotoPublica($sesion, $foto),
        ])->withCookie($this->cookieReclamo($request, $sesion, $service));
    }

    public function foto(Request $request, string $codigo, PedidoBmaSesionEvidenciaFoto $foto, SesionEvidenciaCedisService $service)
    {
        $sesion = $this->abrirSesion($request, $codigo, $service);
        if ((int) $foto->sesion_id !== (int) $sesion->id) {
            abort(404);
        }
        $path = storage_path('app/public/'.$foto->ruta_archivo);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => $foto->mime_type ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function abrirSesion(Request $request, string $codigo, SesionEvidenciaCedisService $service)
    {
        $sesion = $service->porCodigo($codigo);
        $cookie = $request->cookie($service->claimCookieName($sesion));
        $service->assertReclamo($sesion, $cookie);

        if ($sesion->estado === \App\Models\ControlPedidos\PedidoBmaSesionEvidencia::ESTADO_PENDIENTE) {
            $sesion = $service->reclamar($codigo, $request->ip() ?? '', (string) $request->userAgent());
        }

        return $sesion;
    }

    private function pagina(Request $request, $sesion, SesionEvidenciaCedisService $service): InertiaResponse
    {
        $payload = $service->payloadPublico($sesion);

        return Inertia::render('ControlPedidos/Cedis/EvidenciaPublica/Show', array_merge($payload, [
            'codigo' => $sesion->codigo_publico,
            'error' => null,
        ]));
    }

    private function cookieReclamo(Request $request, $sesion, SesionEvidenciaCedisService $service): Cookie
    {
        return cookie(
            $service->claimCookieName($sesion),
            $service->claimCookieValor($sesion),
            \App\Models\ControlPedidos\PedidoBmaSesionEvidencia::TTL_MINUTOS,
            '/cedis-evidencia',
            null,
            $request->secure(),
            true,
            false,
            'lax'
        );
    }
}
