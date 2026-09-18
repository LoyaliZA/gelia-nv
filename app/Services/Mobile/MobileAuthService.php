<?php

namespace App\Services\Mobile;

use App\Models\ConfiguracionUsuario;
use App\Models\MobileDevice;
use App\Models\MobileUserSyncState;
use App\Models\User;
use App\Services\Auditoria\RegistrarAuditoriaAccesoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class MobileAuthService
{
    public function __construct(
        protected MobileScopeVersionService $scopeVersion,
        protected RegistrarAuditoriaAccesoService $auditoriaAcceso
    ) {}

    /**
     * @param  array{login: string, password: string, device_uuid: string, device_name?: string, platform?: string, app_version?: string}  $datos
     * @return array<string, mixed>|null
     */
    public function login(array $datos, Request $request): ?array
    {
        $user = User::query()
            ->where('email', $datos['login'])
            ->orWhere('username', $datos['login'])
            ->orWhere('name', $datos['login'])
            ->first();

        if (! $user || ! Hash::check($datos['password'], $user->password)) {
            return null;
        }

        $device = MobileDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_uuid' => $datos['device_uuid'],
            ],
            [
                'nombre' => $datos['device_name'] ?? null,
                'plataforma' => $datos['platform'] ?? null,
                'app_version' => $datos['app_version'] ?? null,
                'last_seen_at' => now(),
                'revocado_at' => null,
            ]
        );

        $tokenName = $this->nombreToken($device);
        $user->tokens()->where('name', $tokenName)->delete();

        $expira = now()->addDays((int) config('mobile.token_expiration_days', 30));
        $tokenResult = $user->createToken($tokenName, ['mobile'], $expira);

        $scopeVersion = $this->scopeVersion->compute($user);

        MobileUserSyncState::query()->updateOrCreate(
            ['mobile_device_id' => $device->id],
            ['scope_version' => $scopeVersion]
        );

        $this->auditoriaAcceso->registrarLogin($user, $request, 'mobile:'.$device->device_uuid);

        return [
            'access_token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expira->toIso8601String(),
            'scope_version' => $scopeVersion,
            'user' => $this->payloadUsuario($user),
            'permissions' => $user->getPermissionNames()->values()->all(),
            'tema_visual' => $this->resolverTemaVisual($user),
            'device' => $this->payloadDispositivo($device),
        ];
    }

    public function logout(User $user, Request $request): void
    {
        $device = $request->attributes->get('mobile_device');
        $sessionId = $device instanceof MobileDevice
            ? 'mobile:'.$device->device_uuid
            : 'mobile:unknown';

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            $token = PersonalAccessToken::findToken($bearer);
            if ($token) {
                $token->delete();
            }
        }

        if ($device instanceof MobileDevice) {
            PersonalAccessToken::query()
                ->where('tokenable_id', $user->id)
                ->where('name', $this->nombreToken($device))
                ->delete();
        }

        $this->auditoriaAcceso->registrarCierre($sessionId, 'logout');
    }

    /**
     * @return array<string, mixed>
     */
    public function me(User $user, MobileDevice $device): array
    {
        return [
            'user' => $this->payloadUsuario($user),
            'permissions' => $user->getPermissionNames()->values()->all(),
            'scope_version' => $this->scopeVersion->compute($user),
            'tema_visual' => $this->resolverTemaVisual($user),
            'device' => $this->payloadDispositivo($device),
        ];
    }

    public function nombreToken(MobileDevice $device): string
    {
        return 'mobile:'.$device->device_uuid;
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadUsuario(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadDispositivo(MobileDevice $device): array
    {
        return [
            'id' => $device->id,
            'device_uuid' => $device->device_uuid,
            'nombre' => $device->nombre,
            'plataforma' => $device->plataforma,
            'app_version' => $device->app_version,
            'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolverTemaVisual(User $user): array
    {
        $configuracion = ConfiguracionUsuario::query()
            ->where('user_id', $user->id)
            ->value('tema_visual');

        if (empty($configuracion)) {
            return [];
        }

        if (is_string($configuracion)) {
            return json_decode($configuracion, true) ?: [];
        }

        return is_array($configuracion) ? $configuracion : [];
    }
}
