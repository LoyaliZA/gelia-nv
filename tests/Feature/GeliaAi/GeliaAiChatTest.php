<?php

namespace Tests\Feature\GeliaAi;

use App\Models\User;
use App\Services\GeliaAi\GeliaAiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeliaAiChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
        Role::findOrCreate('Super Admin', 'web');
        config([
            'deepseek.api_token' => 'test-token',
            'deepseek.base_url' => 'https://api.deepseek.test',
            'gelia_ai.acceso_modo' => 'super_admin',
            'gelia_ai.model' => 'deepseek-chat',
        ]);
    }

    public function test_sin_acceso_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('gelia_ai.index'))
            ->assertForbidden();
    }

    public function test_sin_api_token_503_en_chat(): void
    {
        config(['deepseek.api_token' => '']);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->postJson(route('gelia_ai.chat'), ['message' => 'Hola'])
            ->assertStatus(503);
    }

    public function test_chat_con_http_fake_responde(): void
    {
        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Los listados sirven para generar Excel de precios.',
                        ],
                    ],
                ],
                'usage' => ['total_tokens' => 42],
            ], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->postJson(route('gelia_ai.chat'), [
                'message' => '¿Cómo funcionan los listados?',
                'messages' => [],
            ])
            ->assertOk()
            ->assertJsonPath('reply', 'Los listados sirven para generar Excel de precios.')
            ->assertJsonPath('usage.total_tokens', 42);
    }

    public function test_chat_nombre_producto_usa_prefetch_una_ronda(): void
    {
        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Encontré coincidencias de vainilla mist.',
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 150, 'completion_tokens' => 30, 'total_tokens' => 180],
            ], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $result = app(GeliaAiChatService::class)->chat($admin, 'busca el producto vainilla mist', []);

        $this->assertStringContainsString('vainilla', mb_strtolower($result['reply']));
        $this->assertSame('prefetch_inventario', $result['usage']['gelia_mode'] ?? null);
        $this->assertSame(1, $result['usage']['gelia_acc']['rounds'] ?? null);
        Http::assertSentCount(1);
    }

    public function test_codigo_barra_usa_una_sola_ronda(): void
    {
        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Stock disponible: 12.',
                        ],
                    ],
                ],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 20, 'total_tokens' => 220],
            ], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $result = app(GeliaAiChatService::class)->chat(
            $admin,
            'revisa el stock de este producto 810101501227',
            [['role' => 'assistant', 'content' => 'Hola, soy GELIA. Puedo explicarte listados...']]
        );

        $this->assertSame('prefetch_inventario', $result['usage']['gelia_mode'] ?? null);
        $this->assertSame(1, $result['usage']['gelia_acc']['rounds'] ?? null);
        Http::assertSentCount(1);
    }
}
