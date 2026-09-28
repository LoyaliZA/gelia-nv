<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Mobile\MobileDeviceRevocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MobileDeviceController extends Controller
{
    public function __construct(
        protected MobileDeviceRevocationService $revocacion
    ) {}

    public function index(User $user): JsonResponse
    {
        Gate::authorize('usuarios.gestionar');

        $dispositivos = $user->mobileDevices()
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (MobileDevice $device) => [
                'id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'nombre' => $device->nombre,
                'plataforma' => $device->plataforma,
                'app_version' => $device->app_version,
                'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
                'revocado_at' => optional($device->revocado_at)?->toIso8601String(),
            ]);

        return response()->json(['dispositivos' => $dispositivos]);
    }

    public function revocar(User $user, MobileDevice $mobileDevice): RedirectResponse
    {
        Gate::authorize('usuarios.gestionar');
        $this->asegurarDispositivoDelUsuario($user, $mobileDevice);

        $this->revocacion->revocar($mobileDevice);

        return back()->with('success', 'El dispositivo móvil fue revocado. Deberá rehabilitarse desde administración para volver a iniciar sesión.');
    }

    public function rehabilitar(User $user, MobileDevice $mobileDevice): RedirectResponse
    {
        Gate::authorize('usuarios.gestionar');
        $this->asegurarDispositivoDelUsuario($user, $mobileDevice);

        $this->revocacion->rehabilitar($mobileDevice);

        return back()->with('success', 'El dispositivo móvil fue rehabilitado. El colaborador puede iniciar sesión de nuevo desde la app.');
    }

    private function asegurarDispositivoDelUsuario(User $user, MobileDevice $mobileDevice): void
    {
        if ((int) $mobileDevice->user_id !== (int) $user->id) {
            abort(404);
        }
    }
}
