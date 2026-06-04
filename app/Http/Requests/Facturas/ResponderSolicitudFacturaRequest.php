<?php

namespace App\Http\Requests\Facturas;

use App\Models\CatalogoEstadoSolicitud;
use Illuminate\Foundation\Http\FormRequest;

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

        return [
            'catalogo_estado_solicitud_id' => ['required', 'exists:catalogo_estados_solicitud,id'],
            'motivo' => [$esError ? 'required' : 'nullable', 'string', 'max:2000'],
            'factura_pdf' => [$esAprobacion ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'factura_xml' => ['nullable', 'file', 'max:5120', 'mimes:xml,txt'],
            'evidencia_error' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'factura_pdf.required' => 'Debe adjuntar el PDF de la factura emitida al aprobar.',
            'motivo.required' => 'Debe indicar el motivo del error reportado.',
        ];
    }
}
