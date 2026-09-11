<?php

namespace App\Http\Controllers;

use App\Services\Tiendanube\TiendanubeWebhookInboxService;
use App\Services\Tiendanube\TiendanubeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class TiendanubeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TiendanubeWebhookService $webhooks,
        TiendanubeWebhookInboxService $inbox
    ): Response|JsonResponse {
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

        if (isset($payload['event']) && ! is_string($payload['event'])) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        if (array_key_exists('store_id', $payload) && ! is_numeric($payload['store_id'])) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        try {
            $delivery = $inbox->persistReceived($payload, hash('sha256', $rawBody));
        } catch (\Throwable) {
            return response()->json(['message' => 'Unable to persist webhook delivery.'], 503);
        }

        $inbox->tryDispatch($delivery);

        return response()->json(['ok' => true], 200);
    }
}
