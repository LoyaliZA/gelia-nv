<?php

namespace App\Support;

use App\Support\Deploy\CalcularVersionesDeploy;

final class GeliaBuildVersion
{
    public static function actual(): string
    {
        return CalcularVersionesDeploy::actual()['shell'];
    }
}
