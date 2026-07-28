<?php

namespace App\Http\Requests\Facturas;

use App\Models\EnlaceDatosFiscales;
use App\Support\Facturas\ReglasCatalogosFiscales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDatosFiscalesPublicosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:6', 'max:64'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'regimen_fiscal' => [
                'nullable',
                'string',
                'max:10',
                Rule::exists('catalogo_regimen_fiscal', 'codigo')->where('activo', true),
            ],
            'correo_electronico' => ['nullable', 'email:filter', 'max:255'],
            'uso_factura' => [
                'nullable',
                'string',
                'max:10',
                Rule::exists('catalogo_uso_cfdi', 'codigo')->where('activo', true),
            ],
            'nombre_razon_social' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'regex:/^\d{1,10}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'regimen_fiscal.exists' => 'El régimen fiscal no es válido.',
            'uso_factura.exists' => 'El uso de CFDI no es válido.',
            'correo_electronico.email' => 'Ingrese un correo electrónico válido.',
            'telefono.regex' => 'El número telefónico solo admite dígitos (máximo 10).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merged = ReglasCatalogosFiscales::aplicarForzados($this->all());

        if (isset($merged['rfc'])) {
            $rfc = strtoupper(preg_replace('/[^A-ZÑ&0-9]/iu', '', (string) $merged['rfc']) ?? '');
            $merged['rfc'] = mb_substr($rfc, 0, 13);
        }

        if (isset($merged['correo_electronico'])) {
            $merged['correo_electronico'] = mb_strtolower(trim((string) $merged['correo_electronico']));
        }

        if (array_key_exists('telefono', $merged)) {
            $tel = preg_replace('/\D+/', '', (string) $merged['telefono']) ?? '';
            $merged['telefono'] = $tel === '' ? null : $tel;
        }

        $this->merge($merged);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $regimen = (string) $this->input('regimen_fiscal', '');
            $uso = (string) $this->input('uso_factura', '');
            $forzado = ReglasCatalogosFiscales::usoForzadoPorRegimen($regimen);
            if ($forzado !== null && $uso !== '' && $uso !== $forzado) {
                $v->errors()->add(
                    'uso_factura',
                    'Con régimen Sueldos y Salarios el uso de CFDI debe ser Sin efectos fiscales (S01).'
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function datosFiscales(): array
    {
        $out = [];
        foreach (EnlaceDatosFiscales::CAMPOS as $campo) {
            if ($this->filled($campo)) {
                $out[$campo] = $this->input($campo);
            }
        }

        return ReglasCatalogosFiscales::aplicarForzados($out);
    }
}
