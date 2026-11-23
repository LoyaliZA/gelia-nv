<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarSalidaTerminalResguardoPdvService;

class RegistrarSalidaTerminalResguardoPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_SALIDA_TERMINAL;
    }

    protected function sucursalIdRegistro(): ?int
    {
        $resguardo = $this->route('resguardo');

        return $resguardo instanceof ResguardoPdv ? (int) $resguardo->sucursal_id : null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('receptor'))) {
            $this->merge(['receptor' => trim($this->input('receptor'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            // La clave se guarda con prefijo en una columna de 64 caracteres.
            'idempotency_key' => ['required', 'string', 'max:55'],
            'receptor' => ['required', 'string', 'max:'.RegistrarSalidaTerminalResguardoPdvService::MAX_RECEPTOR],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receptor.required' => 'Indique el nombre de quien recibe el paquete para llevarlo a terminal.',
            'receptor.max' => 'El nombre de quien recibe admite hasta :max caracteres.',
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
