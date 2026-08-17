<?php

namespace App\Http\Controllers;

use App\Services\WebPush\EnviarWebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebPushController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        $key = config('webpush.vapid.public_key');

        return response()->json([
            'enabled' => (bool) $key && config('webpush.enabled', true),
            'public_key' => $key,
        ]);
    }

    public function subscribe(Request $request, EnviarWebPushService $service): JsonResponse
    {
        $datos = $request->validate([
            'endpoint' => 'required|string|max:512',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'content_encoding' => 'nullable|string|max:32',
        ]);

        $datos['user_agent'] = $request->userAgent();
        $sub = $service->registrarSuscripcion(Auth::user(), $datos);

        return response()->json([
            'ok' => true,
            'id' => $sub->id,
        ]);
    }

    public function unsubscribe(Request $request, EnviarWebPushService $service): JsonResponse
    {
        $endpoint = $request->input('endpoint');
        $eliminadas = $service->eliminarSuscripcion(Auth::user(), $endpoint);

        return response()->json([
            'ok' => true,
            'deleted' => $eliminadas,
        ]);
    }
}
