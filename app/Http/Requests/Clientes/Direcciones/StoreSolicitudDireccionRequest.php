<?php

namespace App\Http\Requests\Clientes\Direcciones;

use App\Models\SolicitudDireccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSolicitudDireccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string', 'max:128'],
            'numero_cliente' => ['nullable', 'string', 'max:50'],
            'nombre_declarado' => ['required', 'string', 'max:255'],
            'telefono_declarado' => ['required', 'string', 'max:30'],
            'correo_declarado' => ['nullable', 'email', 'max:255'],
            'accion_solicitada' => ['required', Rule::in([
                SolicitudDireccion::ACCION_PRIMERA,
                SolicitudDireccion::ACCION_ADICIONAL,
                SolicitudDireccion::ACCION_ACTUALIZAR,
            ])],
            'direccion_seleccionada_id' => ['nullable', 'integer', 'exists:cliente_direcciones,id'],
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['nullable', 'string', 'max:30'],
            'calle' => ['required', 'string', 'max:255'],
            'numero_exterior' => ['nullable', 'string', 'max:30'],
            'numero_interior' => ['nullable', 'string', 'max:30'],
            'colonia' => ['required', 'string', 'max:255'],
            'codigo_postal' => ['required', 'string', 'regex:/^\d{5}$/'],
            'municipio' => ['required', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'string', 'max:255'],
            'pais' => ['nullable', 'string', 'max:255'],
            'referencias' => ['nullable', 'string', 'max:2000'],
            'indicaciones_entrega' => ['nullable', 'string', 'max:2000'],
            'etiqueta' => ['nullable', 'string', 'max:100'],
            'tipo_direccion' => ['nullable', 'string', 'max:50'],
            'anexa_remision' => ['nullable', 'boolean'],
            'archivo_remision' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'comentario' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
