<?php

namespace App\Http\Requests\Facturas;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudFactura;
use App\Support\Facturas\CamposIncorrectosFactura;
use App\Support\Facturas\LimitesAdjuntosFactura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResponderSolicitudFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estadoNuevo = (int) $this->input('catalogo_estado_solicitud_id');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        if ($idIncorrecta !== null && $estadoNuevo === $idIncorrecta) {
            return $this->user()->can('facturas.reportar_error');
        }

        return $this->user()->can('facturas.responder');
    }

    public function rules(): array
    {
        $estadoNuevo = (int) $this->input('catalogo_estado_solicitud_id');
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        $esAprobacion = $idRespondida !== null && $estadoNuevo === $idRespondida;
        $esError = $idIncorrecta !== null && $estadoNuevo === $idIncorrecta;
        $maxKb = LimitesAdjuntosFactura::MAX_KB_POR_ARCHIVO;

        return [
            'catalogo_estado_solicitud_id' => ['required', 'exists:catalogo_estados_solicitud,id'],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'campos_incorrectos' => [$esError ? 'required' : 'nullable', 'array', 'min:1'],
            'campos_incorrectos.*' => ['string', Rule::in(CamposIncorrectosFactura::todos())],
            'generar_enlace_fiscal' => ['nullable', 'boolean'],
            'factura_pdfs' => [$esAprobacion ? 'required' : 'nullable', 'array', 'min:1', 'max:'.LimitesAdjuntosFactura::MAX_PDFS_EMITIDOS],
            'factura_pdfs.*' => ['file', 'mimes:pdf', 'max:'.$maxKb],
            'factura_xml' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:xml,txt'],
            'evidencia_error' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.$maxKb],
        ];
    }

    public function messages(): array
    {
        return [
            'factura_pdfs.required' => 'Debe adjuntar al menos un PDF de la factura emitida al aprobar.',
            'factura_pdfs.min' => 'Debe adjuntar al menos un PDF de la factura emitida al aprobar.',
            'motivo.required' => 'El motivo no puede estar vacío si se envía.',
            'campos_incorrectos.required' => 'Seleccione al menos un campo con error.',
            'campos_incorrectos.min' => 'Seleccione al menos un campo con error.',
        ];
    }
}
