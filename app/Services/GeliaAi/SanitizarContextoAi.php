<?php

namespace App\Services\GeliaAi;

class SanitizarContextoAi
{
    /** @var list<string> */
    private const CLAVES_PROHIBIDAS = [
        'rfc',
        'nombre_razon_social',
        'razon_social',
        'codigo_postal',
        'regimen_fiscal',
        'uso_factura',
        'correo_electronico',
        'costo',
        'costo_reposicion',
        'precio_venta',
        'password',
        'token',
        'secret',
        'api_token',
        'api_key',
    ];

    /**
     * @param  mixed  $data
     * @return mixed
     */
    public function limpiar(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $out = [];
        foreach ($data as $key => $value) {
            $keyStr = is_string($key) ? strtolower($key) : (string) $key;
            if ($this->esProhibida($keyStr)) {
                continue;
            }
            $out[$key] = is_array($value) ? $this->limpiar($value) : $value;
        }

        return $out;
    }

    private function esProhibida(string $key): bool
    {
        foreach (self::CLAVES_PROHIBIDAS as $prohibida) {
            if ($key === $prohibida || str_contains($key, $prohibida)) {
                return true;
            }
        }

        return false;
    }
}
