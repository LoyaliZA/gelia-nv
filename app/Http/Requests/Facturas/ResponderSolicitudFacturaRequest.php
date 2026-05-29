<?php

namespace App\Http\Requests\Facturas;

use Illuminate\Foundation\Http\FormRequest;

class ResponderSolicitudFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('facturas.responder');
    }

    public function rules(): array
    {
        $estadoNuevo = (int) $this->input('catalogo_estado_solicitud_id');
        $esAprobacion = $estadoNuevo === 2;
        $esError = $estadoNuevo === 4;

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
