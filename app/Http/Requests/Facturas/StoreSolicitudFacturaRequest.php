<?php

namespace App\Http\Requests\Facturas;

use App\Services\Facturas\ImportarDatosFiscalesService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSolicitudFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('facturas.crear');
    }

    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'min:3', 'max:255'],
            'numero_cliente' => ['nullable', 'string', 'max:255'],
            'observaciones_vendedor' => ['nullable', 'string', 'max:2000'],
            'archivo_fiscal' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'vouchers' => ['required', 'array', 'min:1', 'max:5'],
            'vouchers.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'vouchers.required' => 'Debe adjuntar al menos un comprobante de pago (voucher).',
            'vouchers.min' => 'Debe adjuntar al menos un comprobante de pago (voucher).',
        ];
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
        });
    }
}
