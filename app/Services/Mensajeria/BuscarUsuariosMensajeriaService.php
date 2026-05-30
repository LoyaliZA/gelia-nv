<?php

namespace App\Services\Mensajeria;

use App\Models\User;
use Illuminate\Support\Collection;

class BuscarUsuariosMensajeriaService
{
    public function ejecutar(User $usuarioActual, ?string $q = null, int $limit = 20): Collection
    {
        $query = User::query()
            ->where('id', '!=', $usuarioActual->id)
            ->orderBy('name');

        if ($q) {
            $term = '%' . trim($q) . '%';
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhere('username', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return $query->limit($limit)->get(['id', 'name', 'username', 'foto_perfil', 'email']);
    }
}
