<?php

namespace Tests\Unit\Services\GeliaAi;

use App\Models\User;
use App\Services\GeliaAi\ResolverAccesoGeliaAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResolverAccesoGeliaAiTest extends TestCase
{
    use RefreshDatabase;

    private ResolverAccesoGeliaAi $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Super Admin', 'web');
        $this->resolver = app(ResolverAccesoGeliaAi::class);
    }

    public function test_super_admin_siempre_puede(): void
    {
        config(['gelia_ai.acceso_modo' => 'super_admin']);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->assertTrue($this->resolver->puedeUsar($admin));
    }

    public function test_modo_super_admin_bloquea_usuario_normal(): void
    {
        config(['gelia_ai.acceso_modo' => 'super_admin']);
        $user = User::factory()->create();

        $this->assertFalse($this->resolver->puedeUsar($user));
    }

    public function test_modo_general_permite_autenticado(): void
    {
        config(['gelia_ai.acceso_modo' => 'general']);
        $user = User::factory()->create();

        $this->assertTrue($this->resolver->puedeUsar($user));
    }

    public function test_modo_usuarios_solo_lista(): void
    {
        $permitido = User::factory()->create();
        $otro = User::factory()->create();
        config([
            'gelia_ai.acceso_modo' => 'usuarios',
            'gelia_ai.acceso_user_ids' => json_encode([$permitido->id]),
        ]);

        $this->assertTrue($this->resolver->puedeUsar($permitido));
        $this->assertFalse($this->resolver->puedeUsar($otro));
    }
}
