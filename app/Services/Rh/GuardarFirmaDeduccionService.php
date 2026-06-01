<?php

namespace App\Services\Rh;

use App\Models\RhDeduccion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GuardarFirmaDeduccionService
{
    public function ejecutar(RhDeduccion $deduccion, string $tipo, string $dataUrl): string
    {
        if (!preg_match('/^data:image\/png;base64,/', $dataUrl)) {
            throw new \InvalidArgumentException('Formato de firma inválido.');
        }

        $contenido = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
        $nombre = sprintf('rh/firmas/%s_%s_%s.png', $deduccion->uuid, $tipo, Str::random(8));

        Storage::disk('public')->put($nombre, $contenido);

        return $nombre;
    }
}
