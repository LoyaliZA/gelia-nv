<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPasskeyOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_con_register_passkey_flashea_prompt(): void
    {
        config(['webauthn.enabled' => true]);

        $user = User::factory()->create([
            'email' => 'operador@empresa.com',
            'password' => bcrypt('secreto-seguro'),
        ]);

        $response = $this->post('/login', [
            'login' => 'operador@empresa.com',
            'password' => 'secreto-seguro',
            'remember' => true,
            'register_passkey' => true,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('prompt_passkey_registration', true);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_sin_register_passkey_no_flashea_prompt(): void
    {
        config(['webauthn.enabled' => true]);

        User::factory()->create([
            'email' => 'operador@empresa.com',
            'password' => bcrypt('secreto-seguro'),
        ]);

        $response = $this->post('/login', [
            'login' => 'operador@empresa.com',
            'password' => 'secreto-seguro',
            'remember' => true,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionMissing('prompt_passkey_registration');
    }

    public function test_register_passkey_ignorado_si_webauthn_desactivado(): void
    {
        config(['webauthn.enabled' => false]);

        User::factory()->create([
            'email' => 'operador@empresa.com',
            'password' => bcrypt('secreto-seguro'),
        ]);

        $response = $this->post('/login', [
            'login' => 'operador@empresa.com',
            'password' => 'secreto-seguro',
            'register_passkey' => true,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionMissing('prompt_passkey_registration');
    }
}
