<?php

namespace App\Support;

final class GeliaBuildVersion
{
    public static function actual(): string
    {
        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            return substr((string) hash_file('sha256', $manifest), 0, 16);
        }

        return (string) config('app.version', 'dev');
    }
}
