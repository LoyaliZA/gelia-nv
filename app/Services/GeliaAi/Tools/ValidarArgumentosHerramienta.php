<?php

namespace App\Services\GeliaAi\Tools;

/**
 * Valida argumentos de tools contra el subconjunto de JSON Schema que usan los schemas de Gelia
 * (object, string, integer, number, boolean, array, enum, required, minimum/maximum, maxLength, format uuid).
 * Propiedades no declaradas se descartan: el modelo a veces inventa parámetros.
 */
final class ValidarArgumentosHerramienta
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array{ok: bool, args: array<string, mixed>, errores: list<string>}
     */
    public function validar(array $schema, mixed $args): array
    {
        if (! is_array($args) || ($args !== [] && array_is_list($args))) {
            return ['ok' => false, 'args' => [], 'errores' => ['Los argumentos deben ser un objeto JSON.']];
        }

        $errores = [];
        $limpio = $this->objeto($schema, $args, '', $errores);

        return ['ok' => $errores === [], 'args' => $limpio, 'errores' => $errores];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $valor
     * @param  list<string>  $errores
     * @return array<string, mixed>
     */
    private function objeto(array $schema, array $valor, string $ruta, array &$errores): array
    {
        $props = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $out = [];

        foreach ((array) ($schema['required'] ?? []) as $req) {
            if (! array_key_exists($req, $valor) || $valor[$req] === null || $valor[$req] === '') {
                $errores[] = "Falta el parámetro obligatorio {$ruta}{$req}.";
            }
        }

        foreach ($props as $nombre => $sub) {
            if (! array_key_exists($nombre, $valor) || $valor[$nombre] === null) {
                continue;
            }
            $res = $this->valor((array) $sub, $valor[$nombre], $ruta.$nombre, $errores);
            if ($res !== null) {
                $out[$nombre] = $res;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errores
     */
    private function valor(array $schema, mixed $valor, string $ruta, array &$errores): mixed
    {
        $tipo = $schema['type'] ?? null;

        $res = match ($tipo) {
            'string' => $this->texto($schema, $valor, $ruta, $errores),
            'integer' => $this->entero($schema, $valor, $ruta, $errores),
            'number' => is_numeric($valor) ? (float) $valor : $this->error($errores, "{$ruta} debe ser numérico."),
            'boolean' => $this->booleano($valor, $ruta, $errores),
            'array' => $this->arreglo($schema, $valor, $ruta, $errores),
            'object' => is_array($valor) && ($valor === [] || ! array_is_list($valor))
                ? $this->objeto($schema, $valor, $ruta.'.', $errores)
                : $this->error($errores, "{$ruta} debe ser un objeto."),
            default => $valor,
        };

        if ($res !== null && isset($schema['enum']) && ! in_array($res, (array) $schema['enum'], true)) {
            return $this->error($errores, "{$ruta} debe ser uno de: ".implode(', ', (array) $schema['enum']).'.');
        }

        return $res;
    }

    /** @param  list<string>  $errores */
    private function texto(array $schema, mixed $valor, string $ruta, array &$errores): ?string
    {
        if (is_int($valor) || is_float($valor)) {
            $valor = (string) $valor;
        }
        if (! is_string($valor)) {
            return $this->error($errores, "{$ruta} debe ser texto.");
        }
        $valor = trim($valor);
        $max = (int) ($schema['maxLength'] ?? 500);
        if (mb_strlen($valor) > $max) {
            return $this->error($errores, "{$ruta} excede {$max} caracteres.");
        }
        if (($schema['format'] ?? null) === 'uuid' && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $valor)) {
            return $this->error($errores, "{$ruta} debe ser un file_id (UUID) de los archivos disponibles.");
        }

        return $valor;
    }

    /** @param  list<string>  $errores */
    private function entero(array $schema, mixed $valor, string $ruta, array &$errores): ?int
    {
        if (is_string($valor) && preg_match('/^-?\d+$/', trim($valor))) {
            $valor = (int) trim($valor);
        }
        if (is_float($valor) && floor($valor) === $valor) {
            $valor = (int) $valor;
        }
        if (! is_int($valor)) {
            return $this->error($errores, "{$ruta} debe ser un entero.");
        }
        if (isset($schema['minimum']) && $valor < $schema['minimum']) {
            return $this->error($errores, "{$ruta} debe ser ≥ {$schema['minimum']}.");
        }
        if (isset($schema['maximum']) && $valor > $schema['maximum']) {
            return $this->error($errores, "{$ruta} debe ser ≤ {$schema['maximum']}.");
        }

        return $valor;
    }

    /** @param  list<string>  $errores */
    private function booleano(mixed $valor, string $ruta, array &$errores): ?bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if ($valor === 'true' || $valor === 1 || $valor === '1') {
            return true;
        }
        if ($valor === 'false' || $valor === 0 || $valor === '0') {
            return false;
        }

        return $this->error($errores, "{$ruta} debe ser booleano.");
    }

    /**
     * @param  list<string>  $errores
     * @return list<mixed>|null
     */
    private function arreglo(array $schema, mixed $valor, string $ruta, array &$errores): ?array
    {
        if (! is_array($valor) || ! array_is_list($valor)) {
            return $this->error($errores, "{$ruta} debe ser una lista.");
        }
        $max = (int) ($schema['maxItems'] ?? 20);
        if (count($valor) > $max) {
            return $this->error($errores, "{$ruta} admite máximo {$max} elementos.");
        }
        $items = is_array($schema['items'] ?? null) ? $schema['items'] : [];
        $out = [];
        foreach ($valor as $i => $v) {
            $res = $items === [] ? $v : $this->valor($items, $v, "{$ruta}[{$i}]", $errores);
            if ($res !== null) {
                $out[] = $res;
            }
        }

        return $out;
    }

    /** @param  list<string>  $errores */
    private function error(array &$errores, string $mensaje): null
    {
        $errores[] = $mensaje;

        return null;
    }
}
