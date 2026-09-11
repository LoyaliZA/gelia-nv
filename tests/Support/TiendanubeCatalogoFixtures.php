<?php

namespace Tests\Support;

final class TiendanubeCatalogoFixtures
{
    public static function get(string $id): mixed
    {
        $md = file_get_contents(base_path('docs/tienda-nb/migracion-api/MIG-02-fixtures-catalogo.md'));
        $thisPos = strpos($md, "### {$id}");
        if ($thisPos === false) {
            throw new \RuntimeException("Fixture {$id} no encontrado.");
        }

        $jsonFence = strpos($md, '```json', $thisPos);
        if ($jsonFence === false) {
            throw new \RuntimeException("JSON de fixture {$id} no encontrado.");
        }

        $start = $jsonFence + strlen('```json');
        $end = strpos($md, '```', $start);
        $json = trim(substr($md, $start, $end - $start));
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException("Fixture {$id} no es JSON válido.");
        }

        return $decoded;
    }
}
