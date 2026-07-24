<?php

namespace App\Http\Requests\Tiendanube;

use Illuminate\Foundation\Http\FormRequest;

class StoreTiendanubeProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.productos.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'published' => ['sometimes', 'boolean'],
            'free_shipping' => ['sometimes', 'boolean'],
            'requires_shipping' => ['sometimes', 'boolean'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'tags' => ['nullable', 'string', 'max:2000'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'min:1'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'promotional_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable'],
            'image_urls' => ['nullable', 'array', 'max:9'],
            'image_urls.*' => ['url', 'max:2048'],
        ];
    }
}
