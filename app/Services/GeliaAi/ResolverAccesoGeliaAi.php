<?php

namespace App\Services\GeliaAi;

use App\Models\User;

class ResolverAccesoGeliaAi
{
    public const MODO_GENERAL = 'general';

    public const MODO_USUARIOS = 'usuarios';

    public const MODO_SUPER_ADMIN = 'super_admin';

    public function modo(): string
    {
        $modo = strtolower(trim((string) config('gelia_ai.acceso_modo', self::MODO_SUPER_ADMIN)));

        return in_array($modo, [self::MODO_GENERAL, self::MODO_USUARIOS, self::MODO_SUPER_ADMIN], true)
            ? $modo
            : self::MODO_SUPER_ADMIN;
    }

    /** @return list<int> */
    public function userIdsPermitidos(): array
    {
        $raw = config('gelia_ai.acceso_user_ids', '[]');

        if (is_array($raw)) {
            return array_values(array_map('intval', $raw));
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_map('intval', $decoded));
    }

    public function puedeUsar(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return match ($this->modo()) {
            self::MODO_GENERAL => true,
            self::MODO_USUARIOS => in_array((int) $user->id, $this->userIdsPermitidos(), true),
            default => false,
        };
    }

    public function puedeGestionarAcceso(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('Super Admin') || $user->can('gelia_ai.gestionar_acceso');
    }
}
