<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Support\PuntoVenta\Resguardos\IncidenciaResguardoPdv;
use Illuminate\Foundation\Http\FormRequest;

class ResolverIncidenciaResguardoPdvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $incidencia = $this->route('incidenciaResguardo');
        if (! $incidencia instanceof ResguardoPdvIncidencia) {
            return false;
        }

        $permiso = IncidenciaResguardoPdv::permisoResolucion($incidencia);
        if ($permiso === null) {
            return false;
        }

        $resguardo = $this->route('resguardo');

        return app(ResuelveAlcancePdv::class)->permiteMutacionPiso(
            $user,
            $permiso,
            $resguardo instanceof ResguardoPdv ? (int) $resguardo->sucursal_id : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'incidencia_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'motivo_resolucion' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadOperacion(): array
    {
        return $this->validated();
    }
}
