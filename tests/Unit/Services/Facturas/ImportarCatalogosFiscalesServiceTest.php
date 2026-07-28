<?php

namespace Tests\Unit\Services\Facturas;

use App\Models\CatalogoRegimenFiscal;
use App\Models\CatalogoUsoCfdi;
use App\Services\Facturas\ImportarCatalogosFiscalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportarCatalogosFiscalesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_regimen_y_uso_desde_csv(): void
    {
        $resultado = app(ImportarCatalogosFiscalesService::class)->ejecutar();

        $this->assertGreaterThanOrEqual(14, $resultado['regimen']);
        $this->assertGreaterThanOrEqual(20, $resultado['uso_cfdi']);
        $this->assertTrue(CatalogoRegimenFiscal::query()->where('codigo', '601')->exists());
        $this->assertTrue(CatalogoRegimenFiscal::query()->where('codigo', '626')->exists());
        $this->assertTrue(CatalogoUsoCfdi::query()->where('codigo', 'G03')->exists());
        $this->assertTrue(CatalogoUsoCfdi::query()->where('codigo', 'S01')->exists());
        $this->assertSame(
            'Régimen Simplificado de Confianza',
            CatalogoRegimenFiscal::query()->where('codigo', '626')->value('nombre')
        );
    }

    public function test_rechaza_codigo_de_regimen_inexistente(): void
    {
        app(ImportarCatalogosFiscalesService::class)->ejecutar();

        $validator = validator(
            ['regimen_fiscal' => '999', 'uso_factura' => 'G03'],
            [
                'regimen_fiscal' => [\Illuminate\Validation\Rule::exists('catalogo_regimen_fiscal', 'codigo')->where('activo', true)],
                'uso_factura' => [\Illuminate\Validation\Rule::exists('catalogo_uso_cfdi', 'codigo')->where('activo', true)],
            ]
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('regimen_fiscal', $validator->errors()->toArray());
        $this->assertFalse(validator(
            ['regimen_fiscal' => '626', 'uso_factura' => 'G03'],
            [
                'regimen_fiscal' => [\Illuminate\Validation\Rule::exists('catalogo_regimen_fiscal', 'codigo')->where('activo', true)],
                'uso_factura' => [\Illuminate\Validation\Rule::exists('catalogo_uso_cfdi', 'codigo')->where('activo', true)],
            ]
        )->fails());
    }
}
