<?php

namespace App\Support;

use Illuminate\Support\Str;

class RhReciboNombreArchivo
{
    public static function sanitizar(string $nombre): string
    {
        $slug = Str::slug($nombre, '_');

        return $slug !== '' ? Str::limit($slug, 80, '') : 'colaborador';
    }

    public static function nomina(string $nombreColaborador, ?string $folio = null): string
    {
        $base = self::sanitizar($nombreColaborador);
        $folioPart = $folio ? '_'.Str::slug($folio, '_') : '';

        return "Recibo_Nomina_{$base}{$folioPart}.pdf";
    }

    public static function incidencia(string $nombreColaborador, string $folioDeduccion): string
    {
        $base = self::sanitizar($nombreColaborador);
        $folio = Str::slug($folioDeduccion, '_');

        return "Recibo_{$base}_{$folio}.pdf";
    }

    public static function periodoIncidencias(string $nombreColaborador, ?string $folio = null): string
    {
        $base = self::sanitizar($nombreColaborador);
        $folioPart = $folio ? '_'.Str::slug($folio, '_') : '';

        return "Recibo_Periodo_{$base}{$folioPart}.pdf";
    }
}
