<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Mobile\MobileSyncBootstrapService;
use App\Services\Mobile\MobileSyncChangesService;
use App\Services\Mobile\MobileSyncException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSyncController extends Controller
{
    public function __construct(
        protected MobileSyncBootstrapService $bootstrap,
        protected MobileSyncChangesService $changes
    ) {}

    public function storeBootstrap(Request $request): JsonResponse
    {
        try {
            return response()->json($this->bootstrap->crear($this->user($request), $this->device($request)), 201);
        } catch (MobileSyncException $e) {
            return response()->json($e->payload, $e->status);
        }
    }

    public function showBootstrap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'snapshot_id' => ['required', 'uuid'],
            'after_cliente_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            return response()->json($this->bootstrap->pagina(
                $this->user($request),
                $validated['snapshot_id'],
                (int) ($validated['after_cliente_id'] ?? 0),
                (int) ($validated['limit'] ?? config('mobile.bootstrap_page_size', 100))
            ));
        } catch (MobileSyncException $e) {
            return response()->json($e->payload, $e->status);
        }
    }

    public function completeBootstrap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'snapshot_id' => ['required', 'uuid'],
            'items_count' => ['required', 'integer', 'min:0'],
            'max_cliente_id' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            return response()->json($this->bootstrap->completar(
                $this->user($request),
                $this->device($request),
                $validated['snapshot_id'],
                (int) $validated['items_count'],
                isset($validated['max_cliente_id']) ? (int) $validated['max_cliente_id'] : null
            ));
        } catch (MobileSyncException $e) {
            return response()->json($e->payload, $e->status);
        }
    }

    public function changes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json($this->changes->changes(
                $this->user($request),
                $this->device($request),
                (int) ($validated['cursor'] ?? 0),
                (int) ($validated['limit'] ?? config('mobile.changes_page_size', 200))
            ));
        } catch (MobileSyncException $e) {
            return response()->json($e->payload, $e->status);
        }
    }

    public function changesHead(Request $request): JsonResponse
    {
        return response()->json($this->changes->head($this->user($request)));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function device(Request $request): MobileDevice
    {
        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobile_device');

        return $device;
    }
}
