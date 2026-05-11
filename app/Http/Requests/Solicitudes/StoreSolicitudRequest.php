<?php

namespace App\Http\Requests\Solicitudes;

use App\Models\Cliente;
use App\Models\CatalogoProceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSolicitudRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta petición.
     */
    public function authorize(): bool
    {
        // Solo los usuarios con el permiso explícito pueden transmitir solicitudes
        return $this->user() && $this->user()->can('solicitudes.crear'); 
    }

    /**
     * Reglas de validación básicas de los campos.
     */
    public function rules(): array
    {
        return [
            'numero_cliente' => ['nullable', 'string', 'max:255'],
            'nombre_cliente' => ['nullable', 'string', 'max:255'],
            'catalogo_proceso_id' => ['required', 'exists:catalogo_procesos,id'],
            'catalogo_tipo_cliente_id' => ['nullable', 'exists:catalogo_tipo_clientes,id'], // <-- NUEVO
            'monto_cotizado' => ['required', 'numeric', 'min:0'],
            'observaciones_vendedor' => ['nullable', 'string'],
            'evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'], // <-- NUEVO (Máx 5MB)
        ];
    }

    /**
     * Lógica de validación compleja (Seguro contra Heredados).
     * Se ejecuta después de las reglas básicas.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $numeroCliente = $this->input('numero_cliente');
                $procesoId = $this->input('catalogo_proceso_id');

                if (!$numeroCliente || !$procesoId) return;

                $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();
                $proceso = CatalogoProceso::find($procesoId);

                if ($cliente && $proceso && $cliente->es_heredado) {
                    // Validamos explícitamente usando la palabra clave de reactivación normal
                    if ($proceso->nombre === 'ASIGNAR CLIENTE REACTIVADO' || $proceso->nombre === 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA') {
                        $validator->errors()->add(
                            'catalogo_proceso_id',
                            'ALERTA: Este es un cliente heredado. Por favor, selecciona el proceso específico para clientes heredados.'
                        );
                    }
                }
            }
        ];
    }
}