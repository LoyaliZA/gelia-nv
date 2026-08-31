<?php

namespace App\Http\Requests\Reportes;

use Illuminate\Foundation\Http\FormRequest;

class ReportarErrorAdminReportePagosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reportes.pagos_pedidos.reportar_error_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'comentario' => ['required', 'string', 'min:10', 'max:2000'],
            'evidencia' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }

    public function messages(): array
    {
        return [
            'comentario.min' => 'Describa el error con al menos 10 caracteres.',
            'evidencia.required' => 'Debe adjuntar evidencia del error detectado.',
        ];
    }
}
