<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GuardarFirmaReciboNominaService
{
    public function ejecutar(RhColaborador $colaborador, Carbon $fechaInicio, Carbon $fechaFin, string $dataUrl): string
    {
        if (! preg_match('/^data:image\/png;base64,/', $dataUrl)) {
            throw new \InvalidArgumentException('Formato de firma inválido.');
        }

        $contenido = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
        $nombre = sprintf(
            'rh/firmas/nomina_%s_%s_%s_%s.png',
            $colaborador->id,
            $fechaInicio->format('Ymd'),
            $fechaFin->format('Ymd'),
            Str::random(8),
        );

        Storage::disk('public')->put($nombre, $contenido);

        return $nombre;
    }
}
