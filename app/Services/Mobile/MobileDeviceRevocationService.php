<?php

namespace App\Services\Mobile;

use App\Models\MobileDevice;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class MobileDeviceRevocationService
{
    public function dispositivoRevocado(User $user, string $deviceUuid): bool
    {
        $device = MobileDevice::query()
            ->where('user_id', $user->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        return $device !== null && $device->revocado_at !== null;
    }

    public function revocar(MobileDevice $device): void
    {
        $device->forceFill(['revocado_at' => now()])->save();
        $this->eliminarTokensDelDispositivo($device);
    }

    public function rehabilitar(MobileDevice $device): void
    {
        $device->forceFill(['revocado_at' => null])->save();
    }

    public function revocarTodosParaUsuario(User $user): void
    {
        MobileDevice::query()
            ->where('user_id', $user->id)
            ->each(fn (MobileDevice $device) => $this->revocar($device));

        PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->delete();
    }

    private function eliminarTokensDelDispositivo(MobileDevice $device): void
    {
        PersonalAccessToken::query()
            ->where('tokenable_type', $device->user()->getModel()->getMorphClass())
            ->where('tokenable_id', $device->user_id)
            ->where('name', 'mobile:'.$device->device_uuid)
            ->delete();
    }
}
