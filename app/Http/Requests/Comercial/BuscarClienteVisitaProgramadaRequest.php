<?php

namespace App\Http\Requests\Comercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuscarClienteVisitaProgramadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('visitas_programadas.gestionar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $modo = $this->input('modo');
        $minimo = $modo === 'numero' ? 1 : 2;

        return [
            'modo' => ['required', Rule::in(['numero', 'nombre'])],
            'q' => ['required', 'string', 'min:'.$minimo, 'max:120'],
        ];
    }
}
