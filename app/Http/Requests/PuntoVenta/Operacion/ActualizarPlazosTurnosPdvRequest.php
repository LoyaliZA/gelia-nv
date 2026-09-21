<?php

namespace App\Http\Requests\PuntoVenta\Operacion;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Services\PuntoVenta\PuntoVentaModulo;

class ActualizarPlazosTurnosPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'espera_inicial_minutos' => ['required', 'integer', 'min:1', 'max:240'],
            'prorroga_minutos' => ['required', 'integer', 'min:1', 'max:480'],
            'ventana_reatencion_minutos' => ['required', 'integer', 'min:1', 'max:1440'],
            'aviso_tolerancia_espera_minutos' => ['required', 'integer', 'min:1', 'max:60'],
            'aviso_tolerancia_prorroga_minutos' => ['required', 'integer', 'min:1', 'max:60'],
            'inicio_atencion_automatico' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos: int,
     *   aviso_tolerancia_prorroga_minutos: int,
     *   inicio_atencion_automatico: bool
     * }
     */
    public function payloadOperacion(): array
    {
        $datos = $this->validated();

        return [
            'espera_inicial_minutos' => (int) $datos['espera_inicial_minutos'],
            'prorroga_minutos' => (int) $datos['prorroga_minutos'],
            'ventana_reatencion_minutos' => (int) $datos['ventana_reatencion_minutos'],
            'aviso_tolerancia_espera_minutos' => (int) $datos['aviso_tolerancia_espera_minutos'],
            'aviso_tolerancia_prorroga_minutos' => (int) $datos['aviso_tolerancia_prorroga_minutos'],
            'inicio_atencion_automatico' => (bool) $datos['inicio_atencion_automatico'],
        ];
    }
}
