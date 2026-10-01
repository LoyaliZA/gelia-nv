<?php

namespace App\Models\Concerns;

use App\Models\Scopes\EsDemoScope;

trait FiltraFilasDemo
{
    public static function bootFiltraFilasDemo(): void
    {
        static::addGlobalScope(new EsDemoScope);
    }
}
