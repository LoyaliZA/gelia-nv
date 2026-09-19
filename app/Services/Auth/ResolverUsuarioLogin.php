<?php

namespace App\Services\Auth;

use App\Models\User;

class ResolverUsuarioLogin
{
    public function resolver(?string $login): ?User
    {
        $login = is_string($login) ? trim($login) : '';

        if ($login === '') {
            return null;
        }

        return User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->orWhere('name', $login)
            ->first();
    }
}
