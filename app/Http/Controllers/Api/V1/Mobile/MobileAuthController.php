<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Mobile\MobileLoginRequest;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Mobile\MobileAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAuthController extends Controller
{
    public function __construct(
        protected MobileAuthService $authService
    ) {}

    public function login(MobileLoginRequest $request): JsonResponse
    {
        $resultado = $this->authService->login($request->validated(), $request);

        if (! $resultado) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ], 401);
        }

        return response()->json($resultado);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user, $request);

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobile_device');

        return response()->json($this->authService->me($user, $device));
    }
}
