<?php

namespace App\Http\Requests\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\FormRequest;

class ConsultaOperacionGeneralPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $alcance = app(ResuelveAlcancePdv::class);

        return $alcance->permiteConsultaPiso($user, PuntoVentaModulo::PERMISO_TURNOS_VER)
            || $alcance->permiteConsultaPiso($user, PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
