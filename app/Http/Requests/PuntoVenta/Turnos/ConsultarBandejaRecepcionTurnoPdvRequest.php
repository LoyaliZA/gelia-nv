<?php

namespace App\Http\Requests\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarBandejaRecepcionTurnoPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        return app(ResuelveAlcancePdv::class)->permiteConsultaPiso(
            $user,
            PuntoVentaModulo::PERMISO_TURNOS_VER
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
