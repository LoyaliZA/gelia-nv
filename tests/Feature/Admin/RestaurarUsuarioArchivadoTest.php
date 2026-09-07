<?php

namespace Tests\Feature\Admin;

use App\Models\AuditoriaSolicitud;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Usuarios\RestaurarUsuarioArchivadoService;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RestaurarUsuarioArchivadoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        Permission::findOrCreate('usuarios.archivar', 'web');
        Permission::findOrCreate('usuarios.restaurar', 'web');

        $rol = Role::findOrCreate('Super Admin', 'web');
        $rol->syncPermissions(Permission::all());

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole($rol);
    }

    public function test_auditoria_muestra_nombre_de_usuario_archivado(): void
    {
        $actor = User::factory()->create(['name' => 'Jesus']);
        $this->archivarUsuario($actor);

        $auditoria = AuditoriaSolicitud::make(['usuario_id' => $actor->id]);
        $auditoria->load('usuario');

        $this->assertSame('Jesus', $auditoria->usuario?->name);
    }

    public function test_gerente_ve_pedidos_de_vendedor_archivado(): void
    {
        $depto = Departamento::create(['nombre' => 'Ventas archivado', 'activo' => true]);

        $gerente = User::factory()->create(['departamento_id' => $depto->id]);
        $gerente->assignRole(Role::findOrCreate('Gerente', 'web'));

        $vendedor = User::factory()->create(['departamento_id' => $depto->id]);
        $gerente->colaboradores()->attach($vendedor->id);

        $enCurso = CatalogoEstatusPedido::create([
            'codigo_interno' => 'AUX_ARCH',
            'nombre_visual' => 'Pendiente auxiliar',
            'color_hex' => '#3B82F6',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            'orden' => 2,
            'activo' => true,
        ]);

        $pedido = PedidoBma::create([
            'folio' => 'PED-ARCH-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedor->id,
            'catalogo_estatus_pedido_id' => $enCurso->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ]);

        $this->archivarUsuario($vendedor);

        $ids = VisibilidadPedidoBma::idsVendedoresVisibles($gerente);
        $this->assertContains((int) $vendedor->id, $ids);
        $this->assertTrue(VisibilidadPedidoBma::puedeConsultarEnListadoBma($gerente, $pedido->fresh()));
    }

    public function test_restaurar_usuario_archivado_reactiva_credenciales(): void
    {
        $colaborador = User::factory()->create([
            'email' => 'jesus@example.com',
            'username' => 'jesus.cruz',
        ]);

        $emailOriginal = $colaborador->email;
        $usernameOriginal = $colaborador->username;

        $this->archivarUsuario($colaborador);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.restaurar', $colaborador->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $colaborador = User::withTrashed()->find($colaborador->id);
        $this->assertNull($colaborador->deleted_at);
        $this->assertSame($emailOriginal, $colaborador->email);
        $this->assertSame($usernameOriginal, $colaborador->username);
    }

    public function test_restaurar_falla_si_email_ya_esta_asignado(): void
    {
        $colaborador = User::factory()->create([
            'email' => 'duplicado@example.com',
            'username' => 'usuario.archivado',
        ]);

        $this->archivarUsuario($colaborador);

        User::factory()->create([
            'email' => 'duplicado@example.com',
            'username' => 'usuario.activo',
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(RestaurarUsuarioArchivadoService::class)->ejecutar($colaborador->fresh());
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            $this->assertTrue($colaborador->fresh()->trashed());
            throw $e;
        }
    }

    public function test_restaurar_falla_si_username_ya_esta_asignado(): void
    {
        $colaborador = User::factory()->create([
            'email' => 'archivado@example.com',
            'username' => 'usuario.duplicado',
        ]);

        $this->archivarUsuario($colaborador);

        User::factory()->create([
            'email' => 'activo@example.com',
            'username' => 'usuario.duplicado',
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(RestaurarUsuarioArchivadoService::class)->ejecutar($colaborador->fresh());
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('username', $e->errors());
            $this->assertTrue($colaborador->fresh()->trashed());
            throw $e;
        }
    }

    public function test_restaurar_sin_permiso_devuelve_403(): void
    {
        $colaborador = User::factory()->create();
        $this->archivarUsuario($colaborador);

        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->post(route('admin.usuarios.restaurar', $colaborador->id))
            ->assertForbidden();
    }

    private function archivarUsuario(User $user): void
    {
        $suffix = '_archived_'.time();

        $user->update([
            'email' => $user->email.$suffix,
            'username' => $user->username.$suffix,
            'telefono' => $user->telefono ? $user->telefono.$suffix : null,
        ]);

        $user->delete();
    }
}
