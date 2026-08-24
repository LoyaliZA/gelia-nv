<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBmaTareaSesionEvidenciaFoto;
use App\Services\ControlPedidos\SesionEvidenciaTareaPreparacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PedidoBmaEvidenciaTiendaPublicaController extends Controller
{
    public function show(Request $request, string $codigo, SesionEvidenciaTareaPreparacionService $service): Response
    {
        try {
            $sesion = $service->reclamar($codigo, $request->ip() ?? '', (string) $request->userAgent());
        } catch (ValidationException $e) {
            return Inertia::render('ControlPedidos/Tienda/EvidenciaPublica/Show', [
                'codigo' => $codigo,
                'error' => $e->errors()['codigo'][0] ?? 'El enlace no es válido.',
            ])->toResponse($request);
        }

        $payload = $service->payloadPublico($sesion);

        return Inertia::render('ControlPedidos/Tienda/EvidenciaPublica/Show', array_merge($payload, [
            'codigo' => $sesion->codigo_publico,
            'error' => null,
        ]))->toResponse($request);
    }

    public function estado(Request $request, string $codigo, SesionEvidenciaTareaPreparacionService $service): JsonResponse
    {
        $sesion = $service->reclamar($codigo, $request->ip() ?? '', (string) $request->userAgent());

        return response()->json($service->payloadPublico($sesion));
    }

    public function subir(Request $request, string $codigo, SesionEvidenciaTareaPreparacionService $service): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $sesion = $service->porCodigo($codigo);
        $service->reclamar($codigo, $request->ip() ?? '', (string) $request->userAgent());
        $foto = $service->subirFoto($sesion, $request->file('foto'));

        return response()->json(['foto_id' => $foto->id]);
    }

    public function foto(string $codigo, PedidoBmaTareaSesionEvidenciaFoto $foto, SesionEvidenciaTareaPreparacionService $service)
    {
        $sesion = $service->porCodigo($codigo);
        if ((int) $foto->pedido_bma_tarea_sesion_evidencia_id !== (int) $sesion->id) {
            abort(404);
        }
        $path = storage_path('app/public/'.$foto->ruta);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => $foto->mime_type ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
