<?php

namespace App\Http\Middleware;

use App\Models\MobileDevice;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken || ! str_starts_with((string) $token->name, 'mobile:')) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        if (! PersonalAccessToken::query()->whereKey($token->id)->exists()) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $deviceUuid = substr((string) $token->name, strlen('mobile:'));
        $device = MobileDevice::query()
            ->where('user_id', $user->id)
            ->where('device_uuid', $deviceUuid)
            ->whereNull('revocado_at')
            ->first();

        if (! $device) {
            return response()->json(['message' => 'Dispositivo revocado.'], 401);
        }

        $device->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set('mobile_device', $device);

        return $next($request);
    }
}
