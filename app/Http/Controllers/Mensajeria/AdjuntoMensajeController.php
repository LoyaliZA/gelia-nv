<?php

namespace App\Http\Controllers\Mensajeria;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mensajeria\StoreAdjuntoRequest;
use App\Models\Conversacion;
use App\Models\MensajeAdjunto;
use App\Services\Mensajeria\SubirAdjuntoMensajeService;
use Illuminate\Http\JsonResponse;
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
            $request->validated('contenido')
        );

        return response()->json(['mensaje' => $mensaje], 201);
    }

    public function show(MensajeAdjunto $adjunto): StreamedResponse
    {
        $adjunto->load('mensaje.conversacion.participantes');

        $conversacion = $adjunto->mensaje->conversacion;
        $this->authorize('view', $conversacion);

        $disk = Storage::disk('public');

        if (!$disk->exists($adjunto->ruta)) {
            abort(404);
        }

        $downloadName = $adjunto->nombre_original ?? basename($adjunto->ruta);

        return $disk->response(
            $adjunto->ruta,
            $downloadName,
            [],
            'attachment'
        );
    }
}
