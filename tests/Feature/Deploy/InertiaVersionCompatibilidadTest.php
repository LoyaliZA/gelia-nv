<?php

namespace Tests\Feature\Deploy;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Tests\Support\RefreshDatabaseSafe;
use Tests\TestCase;

class InertiaVersionCompatibilidadTest extends TestCase
{
    use RefreshDatabaseSafe;

    protected function setUp(): void
    {
        parent::setUp();

        if (is_file(public_path('hot'))) {
            $this->markTestSkipped('public/hot está presente y la versión de Inertia queda en null.');
        }

        config(['gelia.version_compatibilidad' => '1']);
    }

    public function test_la_version_es_la_de_compatibilidad_y_no_el_hash_del_build(): void
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create('/login', 'GET'));

        $this->assertSame('1', $version);
    }

    public function test_un_get_con_la_version_vigente_no_responde_409(): void
    {
        Route::middleware('web')->get('/_test/deploy-version-inertia', fn () => Inertia::render('Auth/Login'));

        $this->withHeaders($this->cabecerasInertia('1'))
            ->get('/_test/deploy-version-inertia')
            ->assertOk();
    }

    public function test_un_get_con_version_distinta_pide_recarga(): void
    {
        Route::middleware('web')->get('/_test/deploy-version-inertia-vieja', fn () => Inertia::render('Auth/Login'));

        $this->withHeaders($this->cabecerasInertia('0'))
            ->get('/_test/deploy-version-inertia-vieja')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
    }

    public function test_un_post_con_version_distinta_no_responde_409(): void
    {
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        Route::middleware('web')->post('/_test/deploy-version-inertia-post', fn () => Inertia::render('Auth/Login'));

        $this->withHeaders($this->cabecerasInertia('0'))
            ->post('/_test/deploy-version-inertia-post')
            ->assertOk();
    }

    /**
     * @return array<string, string>
     */
    private function cabecerasInertia(string $version): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
