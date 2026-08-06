<?php

namespace Tests\Feature\GeliaAi;

use App\Models\GeliaAiConversacion;
use App\Models\GeliaAiMensaje;
use App\Models\GeliaAiUso;
use App\Models\User;
use App\Services\GeliaAi\GeliaAiUsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeliaAiUsoTest extends TestCase
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
        Permission::findOrCreate('gelia_ai.gestionar_acceso', 'web');
        config([
            'deepseek.api_token' => 'test-token',
            'deepseek.base_url' => 'https://api.deepseek.test',
            'gelia_ai.acceso_modo' => 'super_admin',
            'gelia_ai.model' => 'deepseek-chat',
        ]);
    }

    public function test_chat_persiste_uso_con_tokens_y_mode(): void
    {
        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Los listados sirven para generar Excel.',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 80,
                    'completion_tokens' => 20,
                    'total_tokens' => 100,
                ],
            ], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->postJson(route('gelia_ai.chat'), [
                'message' => '¿Cómo funcionan los listados?',
                'messages' => [],
            ])
            ->assertOk();

        $uso = GeliaAiUso::query()->first();
        $this->assertNotNull($uso);
        $this->assertSame($admin->id, $uso->user_id);
        $this->assertSame(80, $uso->prompt_tokens);
        $this->assertSame(20, $uso->completion_tokens);
        $this->assertSame(100, $uso->total_tokens);
        $this->assertSame(1, $uso->rounds);
        $this->assertSame('prefetch_ayuda', $uso->mode);
        $this->assertNotNull($uso->conversacion_id);
        $this->assertSame('deepseek-chat', $uso->modelo);
    }

    public function test_registrar_no_lanza_si_falla_insert(): void
    {
        $user = User::factory()->create();

        // conversacion_id inválido fuerza fallo de FK / create
        app(GeliaAiUsoService::class)->registrar(
            $user,
            999999,
            'hola',
            [
                'reply' => 'ok',
                'usage' => [
                    'gelia_acc' => ['prompt' => 1, 'completion' => 1, 'total' => 2, 'rounds' => 1],
                    'gelia_mode' => 'tools',
                ],
            ],
        );

        $this->assertSame(0, GeliaAiUso::query()->count());
    }

    public function test_admin_uso_sin_permiso_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.gelia_ai.uso.index'))
            ->assertForbidden();
    }

    public function test_admin_uso_index_y_turnos_y_conversacion(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('gelia_ai.gestionar_acceso');

        $otro = User::factory()->create();
        $conv = GeliaAiConversacion::create([
            'user_id' => $otro->id,
            'titulo' => 'Chat prueba',
            'temporal' => false,
        ]);
        GeliaAiMensaje::create([
            'conversacion_id' => $conv->id,
            'role' => 'user',
            'content' => '¿Cuánto stock de Armaf?',
            'created_at' => now(),
        ]);
        GeliaAiMensaje::create([
            'conversacion_id' => $conv->id,
            'role' => 'assistant',
            'content' => 'Hay 280 unidades.',
            'created_at' => now(),
        ]);
        GeliaAiUso::create([
            'user_id' => $otro->id,
            'conversacion_id' => $conv->id,
            'prompt_tokens' => 100,
            'completion_tokens' => 40,
            'total_tokens' => 140,
            'rounds' => 2,
            'mode' => 'tools',
            'modelo' => 'deepseek-chat',
            'mensaje_chars' => 20,
            'reply_chars' => 18,
            'con_archivos' => false,
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.gelia_ai.uso.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/GeliaAi/Uso', false)
                ->where('totales.total_tokens', 140)
                ->where('totales.turnos', 1)
                ->has('ranking', 1)
                ->has('top_turnos', 1)
            );

        $this->actingAs($admin)
            ->getJson(route('admin.gelia_ai.uso.turnos'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.total_tokens', 140)
            ->assertJsonPath('data.0.mode', 'tools');

        $this->actingAs($admin)
            ->getJson(route('admin.gelia_ai.uso.conversacion', $conv->id))
            ->assertOk()
            ->assertJsonPath('id', $conv->id)
            ->assertJsonPath('mensajes.0.role', 'user')
            ->assertJsonPath('mensajes.0.content', '¿Cuánto stock de Armaf?')
            ->assertJsonPath('mensajes.1.role', 'assistant');
    }
}
