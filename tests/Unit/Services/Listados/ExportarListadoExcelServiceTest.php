<?php

namespace Tests\Unit\Services\Listados;

use App\Models\CustomList;
use App\Services\Listados\ExportarListadoExcelService;
use PHPUnit\Framework\TestCase;
use Rap2hpoutre\FastExcel\FastExcel;

class ExportarListadoExcelServiceTest extends TestCase
{
    private ExportarListadoExcelService $service;

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExportarListadoExcelService();
        $this->tempPath = sys_get_temp_dir().'/listado_nota_'.uniqid().'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempPath)) {
            unlink($this->tempPath);
        }
        parent::tearDown();
    }

    public function test_resolver_sin_request_ni_lista_devuelve_null(): void
    {
        $this->assertNull($this->service->resolverNota(null, null, null));
    }

    public function test_resolver_request_false_anula_lista(): void
    {
        $lista = new CustomList([
            'mostrar_nota_encabezado' => true,
            'nota_encabezado' => '*Desde lista',
        ]);

        $this->assertNull($this->service->resolverNota(false, 'ignorado', $lista));
    }

    public function test_resolver_request_true_prioriza_texto(): void
    {
        $lista = new CustomList([
            'mostrar_nota_encabezado' => true,
            'nota_encabezado' => '*Desde lista',
        ]);

        $this->assertSame(
            '*Cambio de precio sin previo aviso',
            $this->service->resolverNota(true, '*Cambio de precio sin previo aviso', $lista)
        );
    }

    public function test_resolver_usa_lista_si_request_omitido(): void
    {
        $lista = new CustomList([
            'mostrar_nota_encabezado' => true,
            'nota_encabezado' => '*Desde lista',
        ]);

        $this->assertSame('*Desde lista', $this->service->resolverNota(null, null, $lista));
    }

    public function test_exportar_sin_nota_primera_fila_es_header(): void
    {
        $filas = [
            ['Folio' => 1, 'SKU' => 'A1', 'Descripcion' => 'Prod'],
        ];
        $this->service->exportar($filas, $this->tempPath, null);

        $rows = [];
        (new FastExcel)->withoutHeaders()->import($this->tempPath, function ($linea) use (&$rows) {
            $rows[] = array_values((array) $linea);
        });

        $this->assertSame(['Folio', 'SKU', 'Descripcion'], $rows[0]);
        $this->assertSame([1, 'A1', 'Prod'], $rows[1]);
    }

    public function test_exportar_con_nota_inserta_fila_antes_de_headers(): void
    {
        $filas = [
            ['Folio' => 1, 'SKU' => 'A1', 'Descripcion' => 'Prod'],
        ];
        $nota = '*Cambio de precio sin previo aviso';
        $this->service->exportar($filas, $this->tempPath, $nota);

        $rows = [];
        (new FastExcel)->withoutHeaders()->import($this->tempPath, function ($linea) use (&$rows) {
            $rows[] = array_values((array) $linea);
        });

        $this->assertSame($nota, $rows[0][0]);
        $this->assertSame(['Folio', 'SKU', 'Descripcion'], $rows[1]);
        $this->assertCount(3, $rows);
    }
}
