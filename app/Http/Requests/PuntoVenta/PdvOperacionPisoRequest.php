<?php

namespace App\Http\Requests\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class PdvOperacionPisoRequest extends FormRequest
{
    abstract protected function permisoAccion(): string;

    protected function sucursalIdRegistro(): ?int
    {
        return null;
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        return app(ResuelveAlcancePdv::class)->permiteMutacionPiso(
            $user,
            $this->permisoAccion(),
            $this->sucursalIdRegistro()
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
