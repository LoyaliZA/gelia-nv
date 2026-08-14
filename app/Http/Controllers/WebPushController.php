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

    /**
     * Debug ingest: cliente (móvil/escritorio remoto) no alcanza localhost del agente.
     */
    public function clientDebug(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'hypothesisId' => 'nullable|string|max:16',
            'location' => 'nullable|string|max:120',
            'message' => 'nullable|string|max:240',
            'data' => 'nullable|array',
            'runId' => 'nullable|string|max:40',
        ]);

        // #region agent log
        $line = json_encode([
            'sessionId' => '80055b',
            'runId' => $payload['runId'] ?? 'desktop-push',
            'hypothesisId' => $payload['hypothesisId'] ?? '?',
            'location' => $payload['location'] ?? 'client',
            'message' => $payload['message'] ?? '',
            'data' => array_merge($payload['data'] ?? [], [
                'user_id' => Auth::id(),
                'ua' => substr((string) $request->userAgent(), 0, 120),
            ]),
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE);
        @file_put_contents(base_path('.cursor/debug-80055b.log'), $line . "\n", FILE_APPEND | LOCK_EX);
        // #endregion

        return response()->json(['ok' => true]);
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
