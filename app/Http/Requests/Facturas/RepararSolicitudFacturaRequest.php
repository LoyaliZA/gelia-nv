<?php

namespace App\Http\Requests\Facturas;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudFactura;
use App\Services\Facturas\ImportarDatosFiscalesService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RepararSolicitudFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->user()->can('facturas.crear')) {
            return false;
        }

        /** @var SolicitudFactura $factura */
        $factura = $this->route('factura');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');

        return $idIncorrecta !== null
            && (int) $factura->catalogo_estado_solicitud_id === $idIncorrecta
            && (int) $factura->vendedor_id === (int) $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'min:3', 'max:255'],
            'numero_cliente' => ['nullable', 'string', 'max:255'],
            'observaciones_vendedor' => ['nullable', 'string', 'max:2000'],
            'archivo_fiscal' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'eliminar_archivo_fiscal' => ['nullable', 'boolean'],
            'vouchers_conservar' => ['nullable', 'array', 'max:5'],
            'vouchers_conservar.*' => ['integer'],
            'vouchers' => ['nullable', 'array', 'max:5'],
            'vouchers.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'vouchers.min' => 'Debe adjuntar al menos un comprobante de pago (voucher) si va a reemplazar los actuales.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var SolicitudFactura $factura */
            $factura = $this->route('factura');
            $factura->load('vouchers');

            $conservarIds = collect($this->input('vouchers_conservar', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            $idsValidos = $factura->vouchers->pluck('id');
            $invalidos = $conservarIds->diff($idsValidos);
            if ($invalidos->isNotEmpty()) {
                $v->errors()->add('vouchers_conservar', 'Uno o más vouchers seleccionados no pertenecen a esta solicitud.');
            }

            $nuevosVouchers = $this->file('vouchers', []);
            $totalVouchers = $conservarIds->count() + count($nuevosVouchers);

            if ($totalVouchers < 1) {
                $v->errors()->add('vouchers', 'Debe conservar o adjuntar al menos un comprobante de pago (voucher).');
            }

            if ($totalVouchers > 5) {
                $v->errors()->add('vouchers', 'Máximo 5 comprobantes de pago por solicitud.');
            }

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
