<?php

namespace App\Http\Requests\Activos;

use App\Models\User;
use App\Services\Activos\ListarActivosService;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class AsignarActivoRequest extends FormRequest
{
    public function authorize(ListarActivosService $activos): bool|Response
    {
        $usuario = $this->user();
        if (! $usuario->can('activos.asignar')) {
            return false;
        }

        return $activos->puedeVer($usuario, $this->route('activo'))
            ? Response::allow()
            : Response::deny('No tiene acceso a este activo.');
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'bail',
                'required',
                'integer',
                'exists:users,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $departamentoId = $this->route('activo')->departamento_id;
                    $pertenece = User::query()
                        ->whereKey($value)
                        ->whereHas('departamentos', fn ($query) => $query->whereKey($departamentoId))
                        ->exists();

                    if (! $pertenece) {
                        $fail('El colaborador debe pertenecer al departamento del activo.');
                    }
                },
            ],
            'notas' => 'nullable|string|max:1000',
            'condiciones_entrega' => 'nullable|string|max:1000',
        ];
    }
}
