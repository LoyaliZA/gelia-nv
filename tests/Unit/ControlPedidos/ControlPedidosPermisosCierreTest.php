<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ControlPedidosPermisosCierreTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoEstatusPedido $pendienteAuxiliar;

    private CatalogoEstatusPedido $pendienteEnvio;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([
            'control_pedidos.auditar',
            'control_pedidos.auditar.aprobar',
            'control_pedidos.liberar_resguardo',
            'control_pedidos.cedis',
            'control_pedidos.cedis.enviar',
            'control_pedidos.delegado',
            'control_pedidos.delegado.importar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $depto = Departamento::create(['nombre' => 'Depto cierre '.uniqid(), 'activo' => true]);
        $this->vendedor = User::factory()->create(['departamento_id' => $depto->id]);

        $this->pendienteAuxiliar = CatalogoEstatusPedido::create([
            'codigo_interno' => 'CIERRE_AUX',
            'nombre_visual' => 'Pendiente auxiliar',
            'color_hex' => '#EAB308',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            'orden' => 1,
            'activo' => true,
        ]);
        $this->pendienteEnvio = CatalogoEstatusPedido::create([
            'codigo_interno' => 'CIERRE_ENV',
            'nombre_visual' => 'Pendiente envío',
            'color_hex' => '#22C55E',
            'fase_ciclo' => CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            'orden' => 2,
            'activo' => true,
        ]);
    }

    public function test_aprobar_exige_extra(): void
    {
        $this->assertCierre(
            'control_pedidos.auditar',
            'control_pedidos.auditar.aprobar',
            'control_pedidos.auditar.aprobar',
            $this->pedido($this->pendienteAuxiliar),
            ['departamento_id' => $this->vendedor->departamento_id],
        );
    }

    public function test_liberar_resguardo_exige_extra(): void
    {
        $this->assertCierre(
            'control_pedidos.auditar',
            'control_pedidos.liberar_resguardo',
            'control_pedidos.auditar.liberar_resguardo',
            $this->pedido($this->pendienteAuxiliar, ['es_resguardo' => true]),
            ['departamento_id' => $this->vendedor->departamento_id],
        );
    }

    public function test_marcar_enviado_exige_extra(): void
    {
        $this->assertCierre(
            'control_pedidos.cedis',
            'control_pedidos.cedis.enviar',
            'control_pedidos.cedis.marcar_enviado',
            $this->pedido($this->pendienteEnvio),
        );
    }

    public function test_importar_guias_exige_extra(): void
    {
        $this->assertCierre(
            'control_pedidos.delegado',
            'control_pedidos.delegado.importar',
            'control_pedidos.delegado.importar',
        );
    }

    private function pedido(CatalogoEstatusPedido $estatus, array $extra = []): PedidoBma
    {
        return PedidoBma::create(array_merge([
            'folio' => 'PED-CIERRE-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->vendedor->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'es_resguardo' => false,
        ], $extra));
    }

    private function assertCierre(
        string $padre,
        string $extra,
        string $ruta,
        ?PedidoBma $pedido = null,
        array $attrsUsuario = [],
    ): void {
        $junior = User::factory()->create($attrsUsuario);
        $junior->givePermissionTo($padre);

        $this->actingAs($junior)
            ->post(route($ruta, $pedido ?? []))
            ->assertForbidden();

        $titular = User::factory()->create($attrsUsuario);
        $titular->givePermissionTo([$padre, $extra]);

        $status = $this->actingAs($titular)
            ->post(route($ruta, $pedido ?? []))
            ->status();

        $this->assertNotEquals(403, $status, "Titular no debe recibir 403 en {$ruta}");
    }
}
