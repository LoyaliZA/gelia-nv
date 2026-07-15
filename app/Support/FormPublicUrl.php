<?php

namespace App\Support;

class FormPublicUrl
{
    public static function base(): string
    {
        $base = rtrim((string) config('app.form_public_url'), '/');

        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        return $base;
    }

    public static function direccionShow(string $codigo): string
    {
        return self::base().'/direcciones-envio/'.ltrim($codigo, '/');
    }

    public static function host(): ?string
    {
        $host = parse_url(self::base(), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    /**
     * @return list<string>
     */
    public static function allowedHosts(): array
    {
        $raw = (string) config('app.allowed_hosts', '');
        $hosts = array_values(array_filter(array_map(
            static fn (string $h): string => strtolower(trim($h)),
            $raw === '' ? [] : explode(',', $raw)
        )));

        $formHost = self::host();
        if ($formHost !== null && ! in_array(strtolower($formHost), $hosts, true)) {
            $hosts[] = strtolower($formHost);
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '' && ! in_array(strtolower($appHost), $hosts, true)) {
            $hosts[] = strtolower($appHost);
        }

        return $hosts;
    }
}
