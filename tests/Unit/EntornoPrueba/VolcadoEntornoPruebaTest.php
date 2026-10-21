<?php

namespace Tests\Unit\EntornoPrueba;

use App\Console\Commands\DbImportarEntornoPruebaCommand;
use App\Console\Commands\DbVolcadoEntornoPruebaCommand;
use Tests\TestCase;

class VolcadoEntornoPruebaTest extends TestCase
{
    public function test_las_claves_de_secreto_se_reconocen(): void
    {
        $this->assertTrue(DbVolcadoEntornoPruebaCommand::claveEsSecreto('deepseek.api_token'));
        $this->assertTrue(DbVolcadoEntornoPruebaCommand::claveEsSecreto('mail.password'));
        $this->assertTrue(DbVolcadoEntornoPruebaCommand::claveEsSecreto('servicio.secret'));
        $this->assertTrue(DbVolcadoEntornoPruebaCommand::claveEsSecreto('api_key.tienda'));
        $this->assertFalse(DbVolcadoEntornoPruebaCommand::claveEsSecreto('gelia_ai.acceso_modo'));
    }

    public function test_el_volcado_declara_las_tablas_aunque_el_nombre_quede_partido(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'volcado');
        $this->assertNotFalse($ruta);
        $gzRuta = $ruta.'.gz';

        $gz = gzopen($gzRuta, 'wb9');
        $this->assertNotFalse($gz);
        $relleno = str_repeat('x', (1024 * 1024) - 20);
        gzwrite($gz, $relleno."\n-- Dumping data for table `pedidos`\n-- Dumping data for table `clientes`\n");
        gzclose($gz);

        try {
            $tablas = DbImportarEntornoPruebaCommand::tablasEnVolcado($gzRuta);
        } finally {
            @unlink($ruta);
            @unlink($gzRuta);
        }

        $this->assertEqualsCanonicalizing(['pedidos', 'clientes'], $tablas);
    }
}
