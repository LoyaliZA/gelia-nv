<?php

namespace Tests\Feature\Tiendanube;

use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Services\Tiendanube\AuditarImagenesTiendanubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditarImagenesTiendanubeTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditar_mide_y_marca_alertas(): void
    {
        TiendanubeProducto::create(['id' => 1, 'name' => ['es' => 'P'], 'published' => true]);
        TiendanubeProductoImagen::create([
            'id' => 10,
            'producto_id' => 1,
            'src' => 'https://cdn.example.com/foto.png',
            'position' => 1,
        ]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );

        Http::fake([
            'cdn.example.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $result = app(AuditarImagenesTiendanubeService::class)->ejecutar(10);

        $this->assertSame(1, $result['actualizadas']);
        $imagen = TiendanubeProductoImagen::find(10);
        $this->assertSame(1, $imagen->width);
        $this->assertSame(1, $imagen->height);
        $this->assertTrue($imagen->alerta_pequena);
        $this->assertTrue($imagen->requiere_revision);
    }
}
