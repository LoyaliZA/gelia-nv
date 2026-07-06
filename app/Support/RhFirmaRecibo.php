<?php

namespace App\Support;

class RhFirmaRecibo
{
    public static function base64DesdeDataUrl(?string $data): ?string
    {
        if (empty($data)) {
            return null;
        }

        if (str_contains($data, 'base64,')) {
            $partes = explode('base64,', $data, 2);

            return ! empty($partes[1]) ? $partes[1] : null;
        }

        return $data;
    }
}
