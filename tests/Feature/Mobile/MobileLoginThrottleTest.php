<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MobileLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_password_limita_intentos_repetidos(): void
    {
        Cache::flush();

        $user = User::factory()->create([
            'email' => 'repetido-'.uniqid().'@example.com',
            'password' => 'secret123',
        ]);

        $payload = [
            'login' => $user->email,
            'password' => 'incorrecta',
            'device_uuid' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/mobile/login', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/v1/mobile/login', $payload)->assertStatus(429);
    }
}
