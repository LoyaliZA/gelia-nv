<?php

namespace App\Http\Requests\Rh;

use App\Models\RhColaborador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateHorasExtraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rh.horas_extra.editar');
    }

    public function rules(): array
    {
        return [
            'rh_colaborador_id' => 'required|exists:rh_colaboradores,id',
            'fecha_turno' => 'required|date',
            'hora_entrada' => 'required|date_format:H:i',
            'hora_salida' => 'required|date_format:H:i',
            'salida_dia_siguiente' => 'nullable|boolean',
            'motivo' => 'required|string|min:10|max:2000',
            'supervisor_user_id' => 'required|exists:users,id',
            'fecha_programada_pago' => 'nullable|date|after_or_equal:fecha_turno',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $registro = $this->route('horasExtra');
            if ($registro && $registro->estado_pago !== 'pendiente') {
                $validator->errors()->add('estado_pago', 'Solo se pueden editar registros con pago pendiente.');
            }

            $colaboradorId = $this->input('rh_colaborador_id');
            $supervisorId = $this->input('supervisor_user_id');

            if ($colaboradorId && $supervisorId) {
                $userId = RhColaborador::where('id', $colaboradorId)->value('user_id');
                if ($userId && (int) $userId === (int) $supervisorId) {
                    $validator->errors()->add('supervisor_user_id', 'El supervisor no puede ser el mismo colaborador vinculado.');
                }
            }
        });
    }
}
