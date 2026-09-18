<?php

namespace Tests\Feature\Mobile;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Mobile\MobileScopeVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileSyncBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_materializa_pagina_y_completa(): void
    {
        $user = $this->usuarioMovil(['clientes.ver']);
        $lista = $this->lista();
        $c1 = $this->cliente('101', $lista->id);
        $c2 = $this->cliente('102', $lista->id);

        $token = $this->token($user);
        $headers = $this->headers($user, $token);

        $inicio = $this->withHeaders($headers)
            ->postJson('/api/v1/mobile/sync/bootstrap')
            ->assertCreated()
            ->json();

        $this->assertSame(2, $inicio['total_items']);
        $this->assertSame('ready', $inicio['status']);

        $pagina = $this->withHeaders($headers)
            ->getJson('/api/v1/mobile/sync/bootstrap?snapshot_id='.$inicio['snapshot_id'].'&after_cliente_id=0&limit=1')
            ->assertOk()
            ->json();

        $this->assertTrue($pagina['has_more']);
        $this->assertCount(1, $pagina['data']);
        $this->assertSame($c1->id, $pagina['data'][0]['id']);

        $resto = $this->withHeaders($headers)
            ->getJson('/api/v1/mobile/sync/bootstrap?snapshot_id='.$inicio['snapshot_id'].'&after_cliente_id='.$pagina['last_cliente_id'].'&limit=10')
            ->assertOk()
            ->json();

        $this->assertFalse($resto['has_more']);
        $this->assertSame($c2->id, $resto['data'][0]['id']);

        $this->withHeaders($headers)
            ->postJson('/api/v1/mobile/sync/bootstrap/complete', [
                'snapshot_id' => $inicio['snapshot_id'],
                'items_count' => 2,
                'max_cliente_id' => $c2->id,
            ])
            ->assertOk()
            ->assertJsonPath('cursor', $inicio['publish_seq_at_start']);
    }

    public function test_complete_con_conteo_incorrecto_falla(): void
    {
        $user = $this->usuarioMovil(['clientes.ver']);
        $this->cliente('1', $this->lista()->id);
        $token = $this->token($user);
        $headers = $this->headers($user, $token);

        $inicio = $this->withHeaders($headers)
            ->postJson('/api/v1/mobile/sync/bootstrap')
            ->assertCreated()
            ->json();

        $this->withHeaders($headers)
            ->postJson('/api/v1/mobile/sync/bootstrap/complete', [
                'snapshot_id' => $inicio['snapshot_id'],
                'items_count' => 99,
                'max_cliente_id' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'bootstrap_mismatch');
    }

    public function test_scope_version_distinta_exige_rebuild(): void
    {
        $user = $this->usuarioMovil(['mis_clientes.gestionar']);
        Permission::findOrCreate('clientes.ver', 'web');
        $token = $this->token($user);
        $versionAntigua = app(MobileScopeVersionService::class)->compute($user);

        config(['mobile.field_policy_version' => 99]);

        $this->withToken($token)
            ->postJson('/api/v1/mobile/sync/bootstrap?scope_version='.$versionAntigua)
            ->assertStatus(409)
            ->assertJsonPath('code', 'scope_changed');
    }

    public function test_field_policy_version_cambia_scope(): void
    {
        $user = $this->usuarioMovil(['clientes.ver']);
        $antes = app(MobileScopeVersionService::class)->compute($user);
        config(['mobile.field_policy_version' => 99]);
        $despues = app(MobileScopeVersionService::class)->compute($user);
        $this->assertNotSame($antes, $despues);
    }

    /**
     * @param  array<int, string>  $permisos
     */
    private function usuarioMovil(array $permisos): User
    {
        $user = User::factory()->create(['password' => 'secret123']);
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            $user->givePermissionTo($permiso);
        }

        return $user;
    }

    private function token(User $user): string
    {
        return $this->postJson('/api/v1/mobile/login', [
            'login' => $user->email,
            'password' => 'secret123',
            'device_uuid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ])->json('access_token');
    }

    /**
     * @return array<string, string>
     */
    private function headers(User $user, string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'X-Mobile-Scope-Version' => app(MobileScopeVersionService::class)->compute($user),
            'Accept' => 'application/json',
        ];
    }

    private function lista(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::first() ?? CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
        ]);
    }

    private function cliente(string $numero, int $listaId): Cliente
    {
        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente '.$numero,
            'lista_actual_id' => $listaId,
        ]);
    }
}
