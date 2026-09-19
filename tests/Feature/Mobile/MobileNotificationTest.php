<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_puede_listar_sus_notificaciones_moviles(): void
    {
        [$user, $token] = $this->mobileSession();
        $otherUser = User::factory()->create();

        $mine = $this->notificationFor($user, ['titulo' => 'Pedido listo']);
        $this->notificationFor($otherUser, ['titulo' => 'Notificación ajena']);

        $this->withToken($token)
            ->getJson('/api/v1/mobile/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.data.titulo', 'Pedido listo');
    }

    public function test_usuario_puede_marcar_una_notificacion_como_leida(): void
    {
        [$user, $token] = $this->mobileSession();
        $notification = $this->notificationFor($user, ['titulo' => 'Solicitud nueva']);

        $this->withToken($token)
            ->patchJson("/api/v1/mobile/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('id', $notification->id)
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_usuario_no_puede_marcar_notificaciones_ajenas(): void
    {
        [, $token] = $this->mobileSession();
        $otherUser = User::factory()->create();
        $notification = $this->notificationFor($otherUser, ['titulo' => 'Privada']);

        $this->withToken($token)
            ->patchJson("/api/v1/mobile/notifications/{$notification->id}/read")
            ->assertNotFound();
    }

    public function test_usuario_puede_marcar_todas_sus_notificaciones_como_leidas(): void
    {
        [$user, $token] = $this->mobileSession();
        $this->notificationFor($user, ['titulo' => 'Una']);
        $this->notificationFor($user, ['titulo' => 'Dos']);

        $this->withToken($token)
            ->patchJson('/api/v1/mobile/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    /** @return array{User, string} */
    private function mobileSession(): array
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $token = $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => (string) Str::uuid(),
        ])->json('access_token');

        return [$user, $token];
    }

    /** @param array<string, mixed> $data */
    private function notificationFor(User $user, array $data): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\MobileNotification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => $data,
        ]);
    }
}
