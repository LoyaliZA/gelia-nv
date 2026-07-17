<?php

namespace Tests\Unit\Services\Facturas;

use App\Services\Facturas\ImportarDatosFiscalesService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportarDatosFiscalesServiceTest extends TestCase
{
    public function test_extrae_telefono_desde_csv_con_plantilla_nueva(): void
    {
        $csv = implode("\n", [
            'RFC,CODIGO POSTAL,REGIMEN FISCAL,CORREO ELECTRONICO,USO DE FACTURA,NOMBRE (RAZON SOCIAL),NUMERO TELEFONICO',
            'XAXX010101000,12345,601,facturacion@ejemplo.com,G03,EMPRESA EJEMPLO SA DE CV,5512345678',
        ])."\n";

        $archivo = UploadedFile::fake()->createWithContent('fiscales.csv', $csv);
        $datos = (new ImportarDatosFiscalesService())->extraer($archivo);

        $this->assertSame('XAXX010101000', $datos['rfc']);
        $this->assertSame('5512345678', $datos['telefono']);
        $this->assertSame('EMPRESA EJEMPLO SA DE CV', $datos['nombre_razon_social']);
    }

    public function test_acepta_alias_telefono_en_cabecera(): void
    {
        $csv = implode("\n", [
            'RFC,CODIGO POSTAL,REGIMEN FISCAL,CORREO ELECTRONICO,USO DE FACTURA,NOMBRE (RAZON SOCIAL),TELEFONO',
            'XAXX010101000,12345,601,facturacion@ejemplo.com,G03,EMPRESA EJEMPLO SA DE CV,5598765432',
        ])."\n";

        $archivo = UploadedFile::fake()->createWithContent('fiscales.csv', $csv);
        $datos = (new ImportarDatosFiscalesService())->extraer($archivo);

        $this->assertSame('5598765432', $datos['telefono']);
    }
}
