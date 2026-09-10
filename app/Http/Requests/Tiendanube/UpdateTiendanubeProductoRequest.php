<?php

namespace App\Http\Requests\Tiendanube;

use App\Models\Tiendanube\TiendanubeProductoVariante;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTiendanubeProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiendanube.productos.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:255'],
            'published' => ['sometimes', 'boolean'],
            'free_shipping' => ['sometimes', 'boolean'],
            'requires_shipping' => ['sometimes', 'boolean'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'tags' => ['nullable', 'string', 'max:2000'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['integer', 'min:1', Rule::exists('tiendanube_categorias', 'id')],
            'replace_categories' => ['sometimes', 'boolean'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'promotional_price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable'],
            'stock_unlimited' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $productId = (int) $this->route('id');
            $variantId = $this->variantIdInformado();

            if ($variantId !== null) {
                $pertenece = TiendanubeProductoVariante::query()
                    ->where('producto_id', $productId)
                    ->where('id', $variantId)
                    ->exists();
                if (! $pertenece) {
                    $validator->errors()->add('variant_id', 'La variante no pertenece a este producto.');

                    return;
                }
            }

            if (! $this->traeCamposDeVariante()) {
                return;
            }

            $localIds = TiendanubeProductoVariante::query()
                ->where('producto_id', $productId)
                ->orderBy('id')
                ->pluck('id');
            $count = $localIds->count();

            if ($count === 0) {
                $validator->errors()->add('variant_id', 'El producto no tiene variantes editables. Sincroniza el catálogo e inténtalo de nuevo.');

                return;
            }

            if ($count > 1 && $variantId === null) {
                $validator->errors()->add(
                    'variant_id',
                    'Selecciona una variante para guardar cambios de SKU, precio, promoción, costo o stock.'
                );
            }
        });
    }

    private function traeCamposDeVariante(): bool
    {
        foreach (['sku', 'price', 'promotional_price', 'cost', 'stock', 'stock_unlimited'] as $campo) {
            if ($this->exists($campo)) {
                return true;
            }
        }

        return false;
    }

    private function variantIdInformado(): ?int
    {
        if (! $this->exists('variant_id') || $this->input('variant_id') === null || $this->input('variant_id') === '') {
            return null;
        }

        return (int) $this->input('variant_id');
    }
}
