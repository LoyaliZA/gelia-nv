<?php

namespace App\Http\Requests\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Illuminate\Validation\Rule;

class CerrarAtencionTurnoPdvRequest extends TurnoPdvMutacionRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION;
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $alcance = app(ResuelveAlcancePdv::class);
        $sucursalId = $this->sucursalIdRegistro();

        return $alcance->permiteMutacionPiso($user, PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION, $sucursalId)
            || $alcance->permiteMutacionPiso($user, PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR, $sucursalId);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->reglasComunes(), [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'motivo' => ['required', 'string', Rule::in(MotivosCierreAtencionTurnoPdv::valoresOperador())],
            'motivo_detalle' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @return array{
     *     version: int,
     *     idempotency_key: string,
     *     motivo: string,
     *     motivo_detalle: string|null
     * }
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();

        return [
            'version' => (int) $datos['version'],
            'idempotency_key' => (string) $datos['idempotency_key'],
            'motivo' => (string) $datos['motivo'],
            'motivo_detalle' => isset($datos['motivo_detalle']) ? (string) $datos['motivo_detalle'] : null,
        ];
    }
}
