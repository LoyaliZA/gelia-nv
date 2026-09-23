<?php

namespace Tests\Feature\Mobile;

use App\Models\ConfiguracionUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileProfileTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): string
    {
        return $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => '44444444-4444-4444-4444-444444444444',
        ])->json('access_token');
    }

    public function test_me_incluye_foto_perfil(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'password' => 'secret123',
            'foto_perfil' => 'perfiles/test.webp',
        ]);

        $token = $this->login($user);

        $this->withToken($token)
            ->getJson('/api/v1/mobile/me')
            ->assertOk()
            ->assertJsonPath('user.foto_perfil', 'perfiles/test.webp')
            ->assertJsonPath('user.foto_perfil_url', url('/storage/perfiles/test.webp'));
    }

    public function test_actualiza_tema_visual(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        ConfiguracionUsuario::create([
            'user_id' => $user->id,
            'tema_visual' => ['modo' => 'dark', 'color_nombre' => 'rosa'],
        ]);

        $token = $this->login($user);

        $this->withToken($token)
            ->patchJson('/api/v1/mobile/profile', [
                'tema_visual' => [
                    'modo' => 'light',
                    'color_nombre' => 'azul',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('tema_visual.modo', 'light')
            ->assertJsonPath('tema_visual.color_nombre', 'azul')
            ->assertJsonPath('tema_visual.color_hex', '#3b82f6');

        $this->assertDatabaseHas('configuraciones_usuarios', [
            'user_id' => $user->id,
        ]);
    }

    public function test_actualiza_foto_perfil(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['password' => 'secret123']);
        $token = $this->login($user);

        $archivo = UploadedFile::fake()->image('perfil.jpg', 400, 400);

        $this->withToken($token)
            ->post('/api/v1/mobile/profile', [
                'foto_perfil' => $archivo,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['user' => ['foto_perfil', 'foto_perfil_url']]);

        $user->refresh();
        $this->assertNotNull($user->foto_perfil);
        Storage::disk('public')->assertExists($user->foto_perfil);
    }

    public function test_elimina_foto_perfil(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('perfiles/vieja.webp', 'contenido');

        $user = User::factory()->create([
            'password' => 'secret123',
            'foto_perfil' => 'perfiles/vieja.webp',
        ]);

        $token = $this->login($user);

        $this->withToken($token)
            ->patchJson('/api/v1/mobile/profile', ['remove_foto' => true])
            ->assertOk()
            ->assertJsonPath('user.foto_perfil', null)
            ->assertJsonPath('user.foto_perfil_url', null);

        $this->assertNull($user->fresh()->foto_perfil);
        Storage::disk('public')->assertMissing('perfiles/vieja.webp');
    }
}
