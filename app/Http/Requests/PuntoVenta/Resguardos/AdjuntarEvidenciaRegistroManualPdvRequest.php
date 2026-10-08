<?php

namespace App\Http\Requests\PuntoVenta\Resguardos;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;

class AdjuntarEvidenciaRegistroManualPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL;
    }

    public function authorize(): bool
    {
        $actor = $this->user();
        if (! app(RegistroManualResguardoPdvConfig::class)->estaActivoPara($actor instanceof User ? $actor : null)) {
            return false;
        }

        return parent::authorize();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'archivo_ticket' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'foto_paquete' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->file('archivo_ticket') === null && $this->file('foto_paquete') === null) {
                $validator->errors()->add(
                    'archivo_ticket',
                    'Adjunte el ticket, la foto del paquete o ambos.'
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadOperacion(): array
    {
        return [
            'idempotency_key' => (string) $this->validated('idempotency_key'),
            'archivo_ticket' => $this->file('archivo_ticket'),
            'foto_paquete' => $this->file('foto_paquete'),
        ];
    }
}
