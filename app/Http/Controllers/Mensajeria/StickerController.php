<?php

namespace App\Http\Controllers\Mensajeria;

use App\Http\Controllers\Controller;
use App\Models\Conversacion;
use App\Models\MensajeriaSticker;
use App\Services\Mensajeria\CrearStickerService;
use App\Services\Mensajeria\EnviarStickerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StickerController extends Controller
{
    public function index(): JsonResponse
    {
        $stickers = MensajeriaSticker::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->get()
            ->map(fn (MensajeriaSticker $sticker) => $this->formato($sticker));

        return response()->json(['stickers' => $stickers]);
    }

    public function store(Request $request, CrearStickerService $service): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $sticker = $service->ejecutar(Auth::user(), $request->file('archivo'));

        return response()->json(['sticker' => $this->formato($sticker)], 201);
    }

    public function destroy(MensajeriaSticker $sticker): JsonResponse
    {
        if ((int) $sticker->user_id !== (int) Auth::id()) {
            abort(403);
        }

        Storage::disk('public')->delete($sticker->ruta);
        $sticker->delete();

        return response()->json(['ok' => true]);
    }

    public function enviar(
        Request $request,
        Conversacion $conversacion,
        EnviarStickerService $service
    ): JsonResponse {
        $this->authorize('sendMessage', $conversacion);

        $datos = $request->validate([
            'sticker_id' => ['required', 'integer'],
            'reply_to_id' => ['nullable', 'integer'],
        ]);

        $mensaje = $service->ejecutar(
            $conversacion,
            Auth::user(),
            (int) $datos['sticker_id'],
            isset($datos['reply_to_id']) ? (int) $datos['reply_to_id'] : null,
        );

        return response()->json(['mensaje' => $mensaje], 201);
    }

    private function formato(MensajeriaSticker $sticker): array
    {
        return [
            'id' => $sticker->id,
            'url' => $sticker->url(),
            'mime' => $sticker->mime,
            'tamano' => $sticker->tamano,
        ];
    }
}
