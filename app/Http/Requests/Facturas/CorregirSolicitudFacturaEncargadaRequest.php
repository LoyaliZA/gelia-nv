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

class CorregirSolicitudFacturaEncargadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('facturas.responder') && ! $this->user()->can('facturas.reportar_error')) {
            return false;
        }

        /** @var SolicitudFactura $factura */
        $factura = $this->route('factura');
        $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');

        return $idPendiente !== null
            && (int) $factura->catalogo_estado_solicitud_id === $idPendiente;
    }

    public function rules(): array
    {
        $maxKb = LimitesAdjuntosFactura::MAX_KB_POR_ARCHIVO;

        return [
            'motivo' => ['nullable', 'string', 'max:2000'],
            'razon_social' => ['nullable', 'string', 'min:3', 'max:255'],
            'datos_fiscales' => ['nullable', 'array'],
            'datos_fiscales.*' => ['nullable', 'string', 'max:255'],
            'archivo_fiscal' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:'.$maxKb],
            'campos_corregidos' => ['nullable', 'array'],
            'campos_corregidos.*' => ['string', Rule::in(CamposIncorrectosFactura::todos())],
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

            $fiscales = is_array($this->input('datos_fiscales')) ? $this->input('datos_fiscales') : [];
            $tieneAlgo = $this->filled('razon_social')
                || $this->hasFile('archivo_fiscal')
                || collect($fiscales)->filter(fn ($val) => trim((string) $val) !== '')->isNotEmpty();

            if (! $tieneAlgo) {
                $v->errors()->add('correccion', 'Indique al menos un dato a corregir.');
            }
        });
    }
}
