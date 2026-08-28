<?php

namespace App\Http\Requests\Facturas;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;
use App\Services\Facturas\ImportarDatosFiscalesService;
use App\Support\Facturas\CamposIncorrectosFactura;
use App\Support\Facturas\LimitesAdjuntosFactura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RepararSolicitudFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('facturas.crear')) {
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
        $maxKb = LimitesAdjuntosFactura::MAX_KB_POR_ARCHIVO;

        return [
            'razon_social' => ['required', 'string', 'min:3', 'max:255'],
            'numero_cliente' => ['nullable', 'string', 'max:255'],
            'observaciones_vendedor' => ['nullable', 'string', 'max:2000'],
            'datos_fiscales' => ['nullable', 'array'],
            'datos_fiscales.*' => ['nullable', 'string', 'max:255'],
            'generar_enlace_fiscal' => ['nullable', 'boolean'],
            'campos_fiscales' => ['nullable', 'array'],
            'campos_fiscales.*' => ['string', Rule::in(EnlaceDatosFiscales::CAMPOS)],
            'archivo_fiscal' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:'.$maxKb],
            'eliminar_archivo_fiscal' => ['nullable', 'boolean'],
            'vouchers_conservar' => ['nullable', 'array', 'max:'.LimitesAdjuntosFactura::MAX_VOUCHERS],
            'vouchers_conservar.*' => ['integer'],
            'vouchers' => ['nullable', 'array', 'max:'.LimitesAdjuntosFactura::MAX_VOUCHERS],
            'vouchers.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.$maxKb],
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

            if ($totalVouchers > LimitesAdjuntosFactura::MAX_VOUCHERS) {
                $v->errors()->add('vouchers', 'Máximo '.LimitesAdjuntosFactura::MAX_VOUCHERS.' comprobantes de pago por solicitud.');
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
