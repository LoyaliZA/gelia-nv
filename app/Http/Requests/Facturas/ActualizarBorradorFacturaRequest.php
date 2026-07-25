<?php

namespace App\Http\Requests\Facturas;

use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;
use App\Services\Facturas\ImportarDatosFiscalesService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ActualizarBorradorFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('facturas.crear');
    }

    public function rules(): array
    {
        $terceroConForm = $this->input('destinatario_tipo') === SolicitudFactura::DESTINATARIO_TERCERO
            && filter_var($this->input('pedir_formulario'), FILTER_VALIDATE_BOOLEAN);

        return [
            'destinatario_tipo' => ['nullable', Rule::in([SolicitudFactura::DESTINATARIO_CLIENTE, SolicitudFactura::DESTINATARIO_TERCERO])],
            'razon_social' => [$terceroConForm ? 'nullable' : 'required', 'string', 'min:3', 'max:255'],
            'numero_cliente' => [
                $this->input('destinatario_tipo') === SolicitudFactura::DESTINATARIO_TERCERO ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'observaciones_vendedor' => ['nullable', 'string', 'max:2000'],
            'archivo_fiscal' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'eliminar_archivo_fiscal' => ['nullable', 'boolean'],
            'pedir_formulario' => ['nullable', 'boolean'],
            'enviar_ahora' => ['nullable', 'boolean'],
            'accion_formulario' => ['nullable', Rule::in([EnlaceDatosFiscales::ACCION_PRIMERA, EnlaceDatosFiscales::ACCION_ACTUALIZAR])],
            'campos_fiscales' => ['nullable', 'array'],
            'campos_fiscales.*' => ['string', Rule::in(EnlaceDatosFiscales::CAMPOS)],
            'vouchers' => ['nullable', 'array', 'max:5'],
            'vouchers.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'vouchers_conservar' => ['nullable', 'array'],
            'vouchers_conservar.*' => ['integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['pedir_formulario', 'enviar_ahora', 'eliminar_archivo_fiscal'] as $campo) {
            if ($this->has($campo)) {
                $this->merge([
                    $campo => filter_var($this->input($campo), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        if (is_string($this->input('campos_fiscales'))) {
            $decoded = json_decode($this->input('campos_fiscales'), true);
            if (is_array($decoded)) {
                $this->merge(['campos_fiscales' => $decoded]);
            }
        }

        $terceroConForm = $this->input('destinatario_tipo') === SolicitudFactura::DESTINATARIO_TERCERO
            && filter_var($this->input('pedir_formulario'), FILTER_VALIDATE_BOOLEAN)
            && trim((string) $this->input('razon_social', '')) === '';

        if ($terceroConForm) {
            $this->merge(['razon_social' => 'Pendiente de formulario']);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->hasFile('archivo_fiscal')) {
                try {
                    app(ImportarDatosFiscalesService::class)->validar($this->file('archivo_fiscal'));
                } catch (\Illuminate\Validation\ValidationException $e) {
                    foreach ($e->errors() as $campo => $mensajes) {
                        foreach ($mensajes as $mensaje) {
                            $v->errors()->add($campo, $mensaje);
                        }
                    }
                }
            }

            if (filter_var($this->input('pedir_formulario'), FILTER_VALIDATE_BOOLEAN)) {
                $campos = $this->input('campos_fiscales', []);
                if (! is_array($campos) || $campos === []) {
                    $v->errors()->add('campos_fiscales', 'Seleccione al menos un campo fiscal.');
                }
            }
        });
    }
}
