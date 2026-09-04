<?php

namespace App\Http\Requests\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstablecerSucursalActivaPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && app(ResuelveAlcancePdv::class)->idsSucursalesOperables($user)->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();
        $operables = app(ResuelveAlcancePdv::class)->idsSucursalesOperables($user)->all();

        return [
            'sucursal_id' => ['required', 'integer', Rule::in($operables)],
        ];
    }
}
