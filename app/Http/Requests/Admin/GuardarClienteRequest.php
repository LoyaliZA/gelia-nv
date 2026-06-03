<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['vendedor_id', 'catalogo_tipo_cliente_id', 'lista_actual_id'] as $campo) {
            if ($this->has($campo) && $this->input($campo) === '') {
                $this->merge([$campo => null]);
            }
        }

        if ($this->has('numero_cliente')) {
            $this->merge(['numero_cliente' => trim((string) $this->input('numero_cliente'))]);
        }

        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim((string) $this->input('nombre'))]);
        }
    }

    public function rules(): array
    {
        $clienteId = $this->route('cliente')?->id;

        $reglasNumero = [
            'required',
            'string',
            'max:32',
            'regex:/^[A-Za-z0-9\-]+$/',
            'not_regex:/^[\p{L}\s]{12,}$/u',
        ];

        $permiteEmergencia = $this->boolean('correccion_emergencia')
            && $this->user()?->can('clientes.correccion_emergencia');

        if (! $permiteEmergencia) {
            $reglaUnicoNumero = Rule::unique('clientes', 'numero_cliente');
            if ($clienteId) {
                $reglaUnicoNumero = $reglaUnicoNumero->ignore($clienteId);
            }
            $reglasNumero[] = $reglaUnicoNumero;
        }

        return [
            'numero_cliente'           => $reglasNumero,
            'nombre'                   => [
                'required',
                'string',
                'max:255',
                'regex:/[\p{L}]/u',
                'not_regex:/^\d+(\.\d+)?$/',
            ],
            'vendedor_id'              => 'nullable|exists:users,id',
            'catalogo_tipo_cliente_id' => 'nullable|exists:catalogo_tipo_clientes,id',
            'monto_venta_actual'       => 'nullable|numeric|min:0',
            'lista_actual_id'          => 'nullable|exists:catalogo_listas_descuento,id',
            'lista_bloqueada'          => 'nullable|boolean',
            'es_inactivo'              => 'nullable|boolean',
            'rfc'                      => 'nullable|string|max:13',
            'codigo_postal'            => 'nullable|string|regex:/^\d{5}$/',
            'regimen_fiscal'           => 'nullable|string|max:255',
            'correo_electronico'       => 'nullable|email|max:255',
            'uso_factura'              => 'nullable|string|max:255',
            'nombre_razon_social'      => 'nullable|string|max:255',
            'correccion_emergencia'    => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_cliente.regex' => 'El número de cliente solo puede contener letras, números y guiones (sin espacios).',
            'numero_cliente.not_regex' => 'El número de cliente parece un nombre; verifique que no esté en el campo equivocado.',
            'numero_cliente.unique' => 'Ese número ya pertenece a otro cliente. Use corrección de emergencia si debe reasignarlo.',
            'nombre.regex' => 'El nombre debe incluir al menos una letra.',
            'nombre.not_regex' => 'El nombre no puede ser solo números; verifique que no sea el número de cliente.',
        ];
    }
}
