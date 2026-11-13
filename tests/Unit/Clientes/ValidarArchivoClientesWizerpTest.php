<?php

namespace Tests\Unit\Clientes;

use App\Services\Clientes\ValidarArchivoClientesWizerp;
use Tests\TestCase;

class ValidarArchivoClientesWizerpTest extends TestCase
{
    public function test_convierte_nombres_windows1252_a_utf8(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cli');
        $nombre = mb_convert_encoding('CARDEÑO GARCIA', 'Windows-1252', 'UTF-8');
        file_put_contents($path, "numero_cliente,nombre\n51,{$nombre}\n");

        try {
            $leido = (new ValidarArchivoClientesWizerp)->leer($path);
        } finally {
            unlink($path);
        }

        $this->assertSame('CARDEÑO GARCIA', $leido['filas'][0]['data']['nombre']);
        $this->assertIsString(json_encode($leido['filas'][0]['data'], JSON_UNESCAPED_UNICODE));
    }
}
