<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Mobile\MobileProfileUpdateRequest;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Mobile\MobileAuthService;
use App\Services\Mobile\MobileProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileProfileController extends Controller
{
    public function __construct(
        protected MobileAuthService $authService,
        protected MobileProfileService $profileService
    ) {}

    public function update(MobileProfileUpdateRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var MobileDevice $device */
        $device = $request->attributes->get('mobile_device');

        $validated = $request->validated();

        if (array_key_exists('tema_visual', $validated)) {
            $this->profileService->actualizarTemaVisual($user, $validated['tema_visual']);
        }

        if ($request->hasFile('foto_perfil') || $request->boolean('remove_foto')) {
            $this->profileService->actualizarFotoPerfil(
                $user,
                $request->file('foto_perfil'),
                $request->boolean('remove_foto')
            );
            $user->refresh();
        }

        return response()->json($this->authService->me($user, $device));
    }
}
