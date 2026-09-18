<?php

namespace Tests\Feature\Mobile;

use App\Events\MobileSyncDisponibleEvent;
use App\Jobs\NotificarMobileSyncJob;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoTipoCliente;
use App\Models\Cliente;
use App\Models\MobileSyncPublication;
use App\Models\User;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Mobile\MobileScopeVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileSyncChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambio_de_vendedor_emite_grant_y_revoke(): void
    {
        Queue::fake();

        $vendedorA = $this->usuarioMovil(['mis_clientes.gestionar']);
        $vendedorB = $this->usuarioMovil(['mis_clientes.gestionar']);
        $lista = $this->lista();

        $cliente = Cliente::create([
            'numero_cliente' => '10',
            'nombre' => 'Cliente',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $vendedorA->id,
            'vendedor_original_id' => $vendedorA->id,
        ]);

        $this->assertDatabaseHas('mobile_sync_publications', [
            'aggregate_id' => $cliente->id,
            'operation' => 'granted',
        ]);

        $cliente->update([
            'vendedor_id' => $vendedorB->id,
            'vendedor_original_id' => $vendedorB->id,
        ]);

        $publicaciones = MobileSyncPublication::query()
            ->where('aggregate_id', $cliente->id)
            ->orderBy('seq')
            ->get();

        $this->assertGreaterThanOrEqual(2, $publicaciones->count());
        $publicacion = $publicaciones->last();

        $this->assertContains($vendedorB->id, array_map('intval', $publicacion->grant_user_ids ?? []));
        $this->assertContains($vendedorA->id, array_map('intval', $publicacion->revoke_user_ids ?? []));

        Queue::assertPushed(NotificarMobileSyncJob::class);
    }

    public function test_changes_filtra_por_destinatario_y_avanza_cursor_global(): void
    {
        $vendedor = $this->usuarioMovil(['mis_clientes.gestionar']);
        $ajeno = $this->usuarioMovil(['mis_clientes.gestionar']);
        $lista = $this->lista();

        $propio = Cliente::create([
            'numero_cliente' => '20',
            'nombre' => 'Propio',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $vendedor->id,
            'vendedor_original_id' => $vendedor->id,
        ]);

        Cliente::create([
            'numero_cliente' => '21',
            'nombre' => 'Ajeno',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $ajeno->id,
            'vendedor_original_id' => $ajeno->id,
        ]);

        $token = $this->token($vendedor);
        $headers = $this->headers($vendedor, $token);

        $head = $this->withHeaders($headers)
            ->getJson('/api/v1/mobile/sync/changes/head')
            ->assertOk()
            ->json();

        $this->assertGreaterThan(0, $head['max_seq']);

        $delta = $this->withHeaders($headers)
            ->getJson('/api/v1/mobile/sync/changes?cursor=0')
            ->assertOk()
            ->json();

        $this->assertSame(1, $delta['first_seq']);
        $this->assertSame($head['max_seq'], $delta['next_cursor']);
        $ids = collect($delta['events'])->pluck('aggregate_id')->all();
        $this->assertContains($propio->id, $ids);
        $this->assertCount(1, $delta['events']);
        $this->assertSame('granted', $delta['events'][0]['operation']);
        $this->assertArrayHasKey('data', $delta['events'][0]);
    }

    public function test_importacion_wizerp_publica_lote_con_updated(): void
    {
        Queue::fake();
        $this->lista();
        CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'MAYOREO BRONCE'],
            ['monto_requerido' => 0.01, 'activo' => true]
        );

        $vendedor = $this->usuarioMovil(['mis_clientes.gestionar']);
        $lista = CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->first();

        $cliente = Cliente::create([
            'numero_cliente' => '900',
            'nombre' => 'Viejo',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $vendedor->id,
            'es_inactivo' => false,
        ]);

        $csv = "numero_cliente,nombre,monto_venta_actual,codigo_lista\n900,Nuevo Nombre,150,BRONCE\n";
        $archivo = UploadedFile::fake()->createWithContent('wizerp.csv', $csv);

        app(ImportarClientesWizerpService::class)->ejecutar($archivo);

        $cliente->refresh();
        $this->assertSame('Nuevo Nombre', $cliente->nombre);

        $this->assertTrue(
            MobileSyncPublication::query()
                ->where('aggregate_id', $cliente->id)
                ->whereNotNull('batch_id')
                ->exists()
        );
    }

    public function test_cambio_de_tipo_publica_updated(): void
    {
        $lista = $this->lista();
        $tipo = CatalogoTipoCliente::create(['nombre' => 'Mayoreo', 'activo' => true]);
        $cliente = Cliente::create([
            'numero_cliente' => '30',
            'nombre' => 'Con tipo',
            'lista_actual_id' => $lista->id,
        ]);

        $cliente->update(['catalogo_tipo_cliente_id' => $tipo->id]);

        $this->assertTrue(
            MobileSyncPublication::query()
                ->where('aggregate_id', $cliente->id)
                ->where('operation', 'updated')
                ->exists()
        );
    }

    public function test_evento_reverb_se_despacha_al_procesar_job(): void
    {
        Event::fake([MobileSyncDisponibleEvent::class]);

        $user = $this->usuarioMovil(['clientes.ver']);
        \Illuminate\Support\Facades\Cache::put("mobile_sync_notify:{$user->id}", [
            'requires_bootstrap' => false,
            'max_seq' => 7,
        ], 10);

        (new NotificarMobileSyncJob($user->id))->handle();

        Event::assertDispatched(MobileSyncDisponibleEvent::class, function (MobileSyncDisponibleEvent $event) use ($user) {
            return $event->userId === $user->id && $event->maxSeq === 7;
        });
    }

    public function test_sin_permiso_de_clientes_sync_es_403(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $token = $this->token($user);

        $this->withToken($token)
            ->getJson('/api/v1/mobile/sync/changes/head')
            ->assertForbidden();
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
            'device_uuid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
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
        return CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
    }
}
