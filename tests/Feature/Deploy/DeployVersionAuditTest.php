<?php

namespace Tests\Feature\Deploy;

use App\Models\AuditoriaDespliegue;
use App\Services\Deploy\RegistrarAuditoriaDespliegueService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Cache;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class DeployVersionAuditTest extends TestCase
{
    use RefreshDatabaseSafe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        Cache::flush();
    }

    public function test_la_consulta_deja_pulso_y_la_publicacion_solo_cuando_cambia_la_version(): void
    {
        $this->getJson('/api/deploy-version')
            ->assertOk()
            ->assertJsonStructure(['version', 'shell', 'surfaces' => ['sala', 'turnos']]);

        $this->assertSame(1, AuditoriaDespliegue::query()->where('accion', 'consulta_ok')->count());
        $this->assertSame(0, AuditoriaDespliegue::query()->where('accion', 'publicacion_detectada')->count());

        Cache::forever('deploy.versiones.snapshot', '{"shell":"anterior","surfaces":{"sala":"a","turnos":"b"}}');
        Cache::forever('deploy.consulta_pulso_at', now()->getTimestamp());

        $this->getJson('/api/deploy-version')->assertOk();

        $this->assertSame(1, AuditoriaDespliegue::query()->where('accion', 'publicacion_detectada')->count());
        $publicacion = AuditoriaDespliegue::query()->where('accion', 'publicacion_detectada')->first();
        $this->assertSame('vigilante_pantalla', $publicacion->detalles['canal']);
    }

    public function test_la_pantalla_puede_registrar_una_recarga(): void
    {
        $this->postJson('/api/deploy-eventos', [
            'accion' => 'recarga_iniciada',
            'superficie' => 'sala',
            'version' => 'abc123',
        ])->assertOk();

        $evento = AuditoriaDespliegue::query()->first();
        $this->assertSame('recarga_iniciada', $evento->accion);
        $this->assertSame('sala', $evento->superficie);
        $this->assertSame('cliente', $evento->origen);
        $this->assertSame('abc123', $evento->detalles['version']);
    }

    public function test_un_fallo_de_bitacora_no_impide_responder_la_version(): void
    {
        $this->mock(RegistrarAuditoriaDespliegueService::class, function ($mock) {
            $mock->shouldReceive('registrarConsulta')->andThrow(new \RuntimeException('bitácora'));
        });

        $this->getJson('/api/deploy-version')->assertOk();
    }
}
