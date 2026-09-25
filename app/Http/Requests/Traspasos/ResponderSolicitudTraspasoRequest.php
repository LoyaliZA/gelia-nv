<?php

namespace App\Http\Requests\Traspasos;

use App\Models\CatalogoEstadoSolicitud;
use App\Support\RevisionFisicaProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $estados = RevisionFisicaProducto::ESTADOS;

        $rules = [
            'catalogo_estado_solicitud_id' => ['required', 'exists:catalogo_estados_solicitud,id'],
            'motivo' => [$esError ? 'required' : 'nullable', 'string', 'max:2000'],
            'folio_traspaso' => [$esAprobacion ? 'required' : 'nullable', 'string', 'max:100'],
            'evidencia_respuesta' => [$esAprobacion ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];

        if ($esAprobacion) {
            $rules['revisiones'] = ['required', 'array', 'min:1'];
            $rules['revisiones.*.solicitud_traspaso_producto_id'] = ['required', 'integer'];
            $rules['revisiones.*.producto_id'] = ['nullable', 'integer'];
            $rules['revisiones.*.descripcion_producto'] = ['nullable', 'string', 'max:255'];
            $rules['revisiones.*.sku'] = ['nullable', 'string', 'max:64'];
            $rules['revisiones.*.estado_fisico'] = ['required', 'string', Rule::in($estados)];
            $rules['revisiones.*.comentario'] = ['nullable', 'string', 'max:2000'];
            $rules['revisiones.*.unica_pieza'] = ['nullable', 'boolean'];
            $rules['revisiones.*.mejor_ejemplar'] = ['nullable', 'boolean'];
            $rules['revisiones.*.evidencias'] = ['nullable', 'array'];
            $rules['revisiones.*.evidencias.*'] = ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $revisiones = $this->input('revisiones');
        if (! is_array($revisiones)) {
            return;
        }

        $normalizadas = [];
        foreach ($revisiones as $rev) {
            if (! is_array($rev)) {
                continue;
            }
            $normalizadas[] = [
                ...$rev,
                'unica_pieza' => filter_var($rev['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'mejor_ejemplar' => filter_var($rev['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        $this->merge(['revisiones' => $normalizadas]);
    }

    public function messages(): array
    {
        return [
            'folio_traspaso.required' => 'Debe indicar el folio del traspaso generado.',
            'evidencia_respuesta.required' => 'Debe adjuntar la captura del traspaso (puede pegar con Ctrl+V).',
            'motivo.required' => 'Debe indicar el motivo del error reportado.',
            'revisiones.required' => 'Debe registrar la revisión física de las piezas.',
            'revisiones.min' => 'Debe registrar la revisión física de las piezas.',
        ];
    }
}
