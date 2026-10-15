<?php

namespace App\Support\PuntoVenta\Medicion;

final class CatalogoEventosMedicionPdv
{
    public const VERSION = 1;

    public const APERTURA = 'jornada.abierta';

    public const CORRECCION = 'medicion.correccion';

    public const TIPOS = [self::APERTURA, self::CORRECCION];

    public const ORIGENES = ['personal', 'gerencial', 'sistema', 'job'];
}
