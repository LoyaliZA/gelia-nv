<?php

namespace App\Support\Facturas;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class TamanoComprobanteFactura implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $esZip = $value->guessExtension() === 'zip';
        $maxKb = $esZip
            ? LimitesAdjuntosFactura::MAX_KB_ZIP_COMPROBANTE
            : LimitesAdjuntosFactura::MAX_KB_POR_ARCHIVO;

        if ($value->getSize() > $maxKb * 1024) {
            $fail($esZip
                ? 'Cada comprobante ZIP debe pesar máximo 10 MB.'
                : 'Cada comprobante JPG, JPEG, PNG, WebP o PDF debe pesar máximo 5 MB.');
        }
    }
}
