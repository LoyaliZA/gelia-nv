<?php

namespace Tests\Unit\Support;

use App\Models\Departamento;
use App\Support\DepartamentoLogoAssets;
use App\Support\RhReciboAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartamentoLogoAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_incluye_logos_aromas_y_bellaroma(): void
    {
        $keys = DepartamentoLogoAssets::keysDisponibles();

        $this->assertContains('aromas_logo_negro', $keys);
        $this->assertContains('bellaroma_logo_negro', $keys);
        $this->assertContains('bellaroma_logo_blanco', $keys);
    }

    public function test_branding_publico_incluye_url_claro_y_oscuro(): void
    {
        $depto = Departamento::query()->create([
            'nombre' => 'Bellaroma Test',
            'activo' => true,
            'logo_key_claro' => 'bellaroma_logo_negro',
            'logo_key_oscuro' => 'bellaroma_logo_blanco',
        ]);

        $branding = DepartamentoLogoAssets::brandingPublico($depto);

        $this->assertNotNull($branding);
        $this->assertSame('bellaroma_logo_negro', $branding['key_claro']);
        $this->assertSame('bellaroma_logo_blanco', $branding['key_oscuro']);
        $this->assertStringContainsString('bellaroma_logo_negro', $branding['url_claro']);
        $this->assertStringContainsString('bellaroma_logo_blanco', $branding['url_oscuro']);
    }

    public function test_fallback_cuando_logo_key_invalido(): void
    {
        $depto = Departamento::query()->create([
            'nombre' => 'Sin Logo',
            'activo' => true,
            'logo_key_claro' => 'no_existe',
        ]);

        $branding = DepartamentoLogoAssets::brandingPublico($depto);

        $this->assertSame(DepartamentoLogoAssets::FALLBACK_KEY, $branding['key_claro']);
        $this->assertArrayHasKey('url_oscuro', $branding);
    }

    public function test_sibling_variante_negro_blanco(): void
    {
        $this->assertSame(
            'bellaroma_logo_blanco',
            DepartamentoLogoAssets::siblingVariante('bellaroma_logo_negro', 'blanco')
        );
        $this->assertSame(
            'bellaroma_logo_negro',
            DepartamentoLogoAssets::siblingVariante('bellaroma_logo_blanco', 'negro')
        );
    }

    public function test_recibo_usa_solo_logo_claro(): void
    {
        $depto = Departamento::query()->create([
            'nombre' => 'Bellaroma Recibo',
            'activo' => true,
            'logo_key_claro' => 'bellaroma_logo_negro',
            'logo_key_oscuro' => 'bellaroma_logo_blanco',
        ]);

        $encabezado = RhReciboAssets::encabezadoParaDepartamento(
            $depto->nombre,
            'blanco', // aunque pidan blanco, el recibo debe usar claro/negro
            $depto,
        );

        $this->assertCount(1, $encabezado['logos']);
        $this->assertSame('bellaroma_logo_negro', $encabezado['logos'][0]['key']);
        $this->assertNotSame('', $encabezado['logos'][0]['base64']);
    }
}
