<?php

namespace App\Http\Controllers\Mensajeria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mensajeria\StoreAdjuntoRequest;
use App\Models\Conversacion;
use App\Models\MensajeAdjunto;
use App\Services\Mensajeria\SubirAdjuntoMensajeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdjuntoMensajeController extends Controller
{
    public function store(
        StoreAdjuntoRequest $request,
        Conversacion $conversacion,
        SubirAdjuntoMensajeService $service
    ): JsonResponse {
        $this->authorize('sendMessage', $conversacion);

        $mensaje = $service->ejecutar(
            $conversacion,
            Auth::user(),
            $request->file('archivo'),
            $request->validated('tipo'),
            $request->validated('contenido'),
            $request->validated('reply_to_id')
        );

        return response()->json(['mensaje' => $mensaje], 201);
    }

    public function show(Request $request, MensajeAdjunto $adjunto): StreamedResponse
    {
        $adjunto->load('mensaje.conversacion.participantes');

        $conversacion = $adjunto->mensaje->conversacion;
        $this->authorize('view', $conversacion);

        $disk = Storage::disk('public');

        if (!$disk->exists($adjunto->ruta)) {
            abort(404);
        }

        $downloadName = $adjunto->nombre_original ?? basename($adjunto->ruta);
        $inline = $request->boolean('inline')
            || $request->query('inline') === '1'
            || $request->query('disposition') === 'inline';
        $disposition = $inline ? 'inline' : 'attachment';

        $headers = [
            'Content-Type' => $adjunto->mime ?: $disk->mimeType($adjunto->ruta),
            'Content-Disposition' => sprintf(
                '%s; filename="%s"',
                $disposition,
                str_replace('"', '\\"', $downloadName)
            ),
        ];

        return $disk->response(
            $adjunto->ruta,
            $downloadName,
            $headers,
            $disposition
        );
    }
}
