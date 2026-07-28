<?php

namespace App\Http\Requests\Facturas;

use App\Support\Facturas\ReglasCatalogosFiscales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReceptorFiscalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('facturas.gestionar_datos_fiscales');
    }

    public function rules(): array
    {
        return [
            'rfc' => ['nullable', 'string', 'max:13'],
            'codigo_postal' => ['nullable', 'regex:/^\d{5}$/'],
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
            'activo' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merged = ReglasCatalogosFiscales::aplicarForzados($this->all());

        if (isset($merged['rfc'])) {
            $merged['rfc'] = ReglasCatalogosFiscales::normalizarRfc($merged['rfc'] ?? null);
        }
        if (isset($merged['correo_electronico'])) {
            $merged['correo_electronico'] = mb_strtolower(trim((string) $merged['correo_electronico']));
        }
        if (array_key_exists('nombre_razon_social', $merged)) {
            $razon = ReglasCatalogosFiscales::normalizarRazonSocial($merged['nombre_razon_social'] ?? null);
            $merged['nombre_razon_social'] = $razon === '' ? null : $razon;
        }
        if (array_key_exists('telefono', $merged)) {
            $tel = preg_replace('/\D+/', '', (string) $merged['telefono']) ?? '';
            $merged['telefono'] = $tel === '' ? null : $tel;
        }
        if ($this->has('activo')) {
            $merged['activo'] = filter_var($this->input('activo'), FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge($merged);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $rfc = (string) $this->input('rfc', '');
            $razon = (string) $this->input('nombre_razon_social', '');
            if ($rfc === '' && $razon === '') {
                $v->errors()->add('nombre_razon_social', 'Indique RFC o razón social.');
            }
            if ($err = ReglasCatalogosFiscales::errorRfc($rfc)) {
                $v->errors()->add('rfc', $err);
            }
            $forzado = ReglasCatalogosFiscales::usoForzadoPorRegimen((string) $this->input('regimen_fiscal', ''));
            $uso = (string) $this->input('uso_factura', '');
            if ($forzado !== null && $uso !== '' && $uso !== $forzado) {
                $v->errors()->add('uso_factura', 'Con régimen 605 el uso debe ser S01.');
            }
        });
    }
}
