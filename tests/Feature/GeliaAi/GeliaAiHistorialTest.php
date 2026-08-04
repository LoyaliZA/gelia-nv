<?php

namespace Tests\Feature\GeliaAi;

use App\Models\GeliaAiConversacion;
use App\Models\GeliaAiMensaje;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeliaAiHistorialTest extends TestCase
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

    public function test_chat_persiste_conversacion_y_mensajes(): void
    {
        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Stock OK.']]],
                'usage' => ['total_tokens' => 10],
            ], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)
            ->postJson(route('gelia_ai.chat'), [
                'message' => 'revisa stock 810101501227',
                'messages' => [],
            ])
            ->assertOk()
            ->assertJsonPath('reply', 'Stock OK.');

        $convId = $response->json('conversacion_id');
        $this->assertNotEmpty($convId);

        $conv = GeliaAiConversacion::find($convId);
        $this->assertNotNull($conv);
        $this->assertFalse($conv->temporal);
        $this->assertSame($admin->id, $conv->user_id);
        $this->assertStringContainsString('810101501227', (string) $conv->titulo);

        $this->assertSame(2, GeliaAiMensaje::where('conversacion_id', $convId)->count());
    }

    public function test_listar_y_cargar_conversacion(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $conv = GeliaAiConversacion::create([
            'user_id' => $admin->id,
            'titulo' => 'Consulta perfume',
            'temporal' => false,
        ]);
        GeliaAiMensaje::create([
            'conversacion_id' => $conv->id,
            'role' => 'user',
            'content' => 'hola',
            'created_at' => now(),
        ]);
        GeliaAiMensaje::create([
            'conversacion_id' => $conv->id,
            'role' => 'assistant',
            'content' => 'respuesta',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson(route('gelia_ai.conversaciones.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $conv->id)
            ->assertJsonPath('data.0.titulo', 'Consulta perfume');

        $this->actingAs($admin)
            ->getJson(route('gelia_ai.conversaciones.show', $conv->id))
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'hola')
            ->assertJsonPath('messages.1.content', 'respuesta');
    }

    public function test_eliminar_conversacion(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $conv = GeliaAiConversacion::create([
            'user_id' => $admin->id,
            'titulo' => 'Borrar',
            'temporal' => false,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('gelia_ai.conversaciones.destroy', $conv->id))
            ->assertOk();

        $this->assertDatabaseMissing('gelia_ai_conversaciones', ['id' => $conv->id]);
    }

    public function test_no_lista_chats_temporales_vacios(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        GeliaAiConversacion::create([
            'user_id' => $admin->id,
            'titulo' => null,
            'temporal' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('gelia_ai.conversaciones.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
