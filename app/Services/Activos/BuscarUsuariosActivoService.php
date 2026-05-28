<?php

namespace App\Services\Activos;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BuscarUsuariosActivoService
{
    public function ejecutar(?string $q = null, ?int $departamentoId = null, int $limite = 20): array
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'username'])
            ->with(['departamentos:id,nombre', 'areas:id,nombre'])
            ->orderBy('name');

        if ($q) {
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%");
            });
        }

        if ($departamentoId) {
            $query->whereHas('departamentos', fn (Builder $sub) => $sub->where('departamentos.id', $departamentoId));
        }

        return $query->limit($limite)->get()->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'departamentos' => $user->departamentos->pluck('nombre'),
                'areas' => $user->areas->pluck('nombre'),
            ];
        })->all();
    }
}
