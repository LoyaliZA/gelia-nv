<?php

namespace App\Http\Requests\Traspasos;

use App\Models\CatalogoEstadoSolicitud;
use Illuminate\Foundation\Http\FormRequest;

class ResponderSolicitudTraspasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $estadoNuevo = (int) $this->input('catalogo_estado_solicitud_id');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        if ($idIncorrecta !== null && $estadoNuevo === $idIncorrecta) {
            return $this->user()->can('traspasos.reportar_error');
        }

        return $this->user()->can('traspasos.responder');
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
            'folio_traspaso' => [$esAprobacion ? 'required' : 'nullable', 'string', 'max:100'],
            'evidencia_respuesta' => [$esAprobacion ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'folio_traspaso.required' => 'Debe indicar el folio del traspaso generado.',
            'evidencia_respuesta.required' => 'Debe adjuntar la captura del traspaso (puede pegar con Ctrl+V).',
            'motivo.required' => 'Debe indicar el motivo del error reportado.',
        ];
    }
}
