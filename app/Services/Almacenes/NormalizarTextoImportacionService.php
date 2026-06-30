<?php

namespace App\Services\Almacenes;

class NormalizarTextoImportacionService
{
    public function texto(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $limpio = mb_strtoupper(trim($valor));

        return $limpio === '' ? null : $limpio;
    }

    public function codigoBarras(?string $valor, string $sku): string
    {
        $normalizado = $this->texto($valor);

        return $normalizado ?? $sku;
    }
}
