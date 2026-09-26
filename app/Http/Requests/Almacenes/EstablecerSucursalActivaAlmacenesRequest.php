<?php

namespace App\Http\Requests\Almacenes;

use App\Services\Almacenes\AlcanceAlmacenesService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstablecerSucursalActivaAlmacenesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return app(AlcanceAlmacenesService::class)->idsSucursalesOperables($user)->isNotEmpty();
    }

    public function rules(): array
    {
        $user = $this->user();
        $operables = app(AlcanceAlmacenesService::class)->idsSucursalesOperables($user)->all();

        return [
            'sucursal_id' => ['required', 'integer', Rule::in($operables)],
        ];
    }
}
