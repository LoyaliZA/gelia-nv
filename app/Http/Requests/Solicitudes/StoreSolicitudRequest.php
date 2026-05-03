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
        // Por el momento lo dejamos en true. 
        // Más adelante lo protegeremos con Spatie: return $this->user()->hasRole('Vendedor');
        return true; 
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
            'monto_cotizado' => ['required', 'numeric', 'min:0'],
            'observaciones_vendedor' => ['nullable', 'string'],
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

                if (!$numeroCliente || !$procesoId) {
                    return;
                }

                $cliente = Cliente::where('numero_cliente', $numeroCliente)->first();
                $proceso = CatalogoProceso::find($procesoId);

                if ($cliente && $proceso) {
                    $esProcesoNormal = in_array($proceso->nombre, [
                        'ASIGNAR CLIENTE REACTIVADO',
                        'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA'
                    ]);

                    // Validar la regla de negocio
                    if ($cliente->es_heredado && $esProcesoNormal) {
                        $validator->errors()->add(
                            'catalogo_proceso_id',
                            'Alerta: Este es un cliente heredado. No puede seleccionar una reactivación normal, debe seleccionar la opción correspondiente a Heredados.'
                        );
                    }
                }
            }
        ];
    }
}