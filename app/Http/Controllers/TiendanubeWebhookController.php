<?php

namespace App\Http\Controllers;

use App\Jobs\Tiendanube\ProcessTiendanubeWebhook;
use App\Models\Tiendanube\TiendanubeWebhookDelivery;
use App\Services\Tiendanube\TiendanubeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class TiendanubeWebhookController extends Controller
{
    public function __invoke(Request $request, TiendanubeWebhookService $webhooks): Response|JsonResponse
    {
        $rawBody = $request->getContent();
        $receivedSignature = $request->header('x-linkedstore-hmac-sha256');

        if (! is_string($receivedSignature) || $receivedSignature === '') {
            return response()->json(['message' => 'Missing webhook signature.'], 401);
        }

        try {
            $secret = $webhooks->requireAppSecret();
        } catch (\Throwable) {
            return response()->json(['message' => 'Webhook secret not configured.'], 503);
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);
        if (! hash_equals($expectedSignature, $receivedSignature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid JSON payload.'], 400);
        }

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid JSON payload.'], 400);
        }

        $payloadHash = hash('sha256', $rawBody);
        $existing = TiendanubeWebhookDelivery::query()->where('payload_hash', $payloadHash)->first();
        if ($existing) {
            return response()->json(['ok' => true, 'duplicate' => true], 200);
        }

        $event = isset($payload['event']) && is_string($payload['event']) ? $payload['event'] : null;
        $resourceId = array_key_exists('id', $payload) ? (string) $payload['id'] : null;
        $storeId = isset($payload['store_id']) ? (int) $payload['store_id'] : null;

        $delivery = TiendanubeWebhookDelivery::query()->create([
            'store_id' => $storeId,
            'event' => $event,
            'resource_id' => $resourceId,
            'payload' => $payload,
            'payload_hash' => $payloadHash,
            'hmac_valid' => true,
            'status' => 'received',
        ]);

        ProcessTiendanubeWebhook::dispatch($delivery->id);

        return response()->json(['ok' => true], 200);
    }
}
