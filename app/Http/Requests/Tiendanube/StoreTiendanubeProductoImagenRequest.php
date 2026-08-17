<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class StoreTiendanubeProductoImagenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.productos.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'src' => ['nullable', 'url', 'max:2048', 'required_without:file'],
            'file' => ['nullable', 'file', 'required_without:src', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp'],
            'position' => ['nullable', 'integer', 'min:1'],
            'reemplazar' => ['nullable', 'boolean'],
            'convertir_webp' => ['nullable', 'boolean'],
            'modo_1280' => ['nullable', 'string', 'in:none,fit,square'],
        ];
    }
}
