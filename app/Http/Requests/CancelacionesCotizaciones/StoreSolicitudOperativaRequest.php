<?php

namespace App\Http\Requests\CancelacionesCotizaciones;

use App\Models\CatalogoProceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSolicitudOperativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('cancelaciones_cotizaciones.crear');
    }

    public function rules(): array
    {
        $proceso = $this->resolverProceso();

        $reglas = [
            'numero_cliente' => ['required', 'string', 'max:255'],
            'nombre_cliente' => ['nullable', 'string', 'max:255'],
            'catalogo_proceso_id' => ['required', 'exists:catalogo_procesos,id'],
            'observaciones_vendedor' => ['nullable', 'string'],
            'monto_cotizado' => ['nullable', 'numeric', 'min:0'],
            'numero_remision' => ['nullable', 'string', 'max:255'],
            'numero_pedido' => ['nullable', 'string', 'max:255'],
            'fecha_operacion' => ['nullable', 'date'],
            'motivo_operacion' => ['nullable', 'string'],
            'catalogo_banco_id' => ['nullable', 'exists:catalogo_bancos,id'],
            'solicitar_cotizacion' => ['nullable', 'boolean'],
        ];

        if ($proceso?->esOperativo()) {
            $reglas = array_merge($reglas, $this->reglasOperativas($proceso));
        }

        return $reglas;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $proceso = $this->resolverProceso();

                if (!$proceso) {
                    return;
                }

                if (!$proceso->esOperativo()) {
                    $validator->errors()->add(
                        'catalogo_proceso_id',
                        'Este tipo de solicitud debe crearse desde el módulo de Solicitudes.'
                    );
                }
            },
        ];
    }

    private function reglasOperativas(?CatalogoProceso $proceso): array
    {
        $nombre = strtoupper($proceso?->nombre ?? '');

        $reglas = [];

        if (str_contains($nombre, 'COTIZACIÓN') || str_contains($nombre, 'COTIZACION')) {
            $reglas['numero_pedido'] = ['required', 'string', 'max:255'];
        } elseif (str_contains($nombre, 'REMISIÓN') || str_contains($nombre, 'REMISION')) {
            $reglas['numero_remision'] = ['required', 'string', 'max:255'];
            $reglas['fecha_operacion'] = ['required', 'date'];
            $reglas['motivo_operacion'] = ['required', 'string', 'min:5'];
            $reglas['catalogo_banco_id'] = ['required', 'exists:catalogo_bancos,id'];
        } elseif (str_contains($nombre, 'PEDIDO')) {
            $reglas['numero_pedido'] = ['required', 'string', 'max:255'];
            if (str_contains($nombre, 'CANCEL')) {
                $reglas['motivo_operacion'] = ['required', 'string', 'min:5'];
            }
        }

        return $reglas;
    }

    private function resolverProceso(): ?CatalogoProceso
    {
        $procesoId = $this->input('catalogo_proceso_id');
        if (!$procesoId) {
            return null;
        }

        return CatalogoProceso::find($procesoId);
    }
}
