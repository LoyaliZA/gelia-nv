<?php

namespace Tests\Feature\Mobile;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Comercial\VisitaClienteProgramada;
use App\Models\ConfiguracionSistema;
use App\Models\MobileDevice;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileVisitasProgramadasTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ThrottleRequests::class]);
        Role::findOrCreate('Super Admin', 'web');

        foreach (array_merge(PuntoVentaModulo::permisosIniciales(), ['visitas_programadas.gestionar', 'mis_clientes.gestionar']) as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );

        $this->sucursal = Sucursal::factory()->create();
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_VER,
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_CONFIRMAR_LLEGADA,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    public function test_api_pdv_lista_visitas_del_dia(): void
    {
        $cliente = $this->crearCliente();
        $vendedora = User::factory()->create();

        VisitaClienteProgramada::query()->create([
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'fecha' => now()->toDateString(),
            'tipo_hora' => VisitaClienteProgramada::TIPO_HORA_SIN,
            'intencion' => VisitaClienteProgramada::INTENCION_CONFIRMO,
            'estado' => VisitaClienteProgramada::ESTADO_PROGRAMADA,
            'registrado_por_user_id' => $vendedora->id,
        ]);

        $this->conToken($this->token())
            ->getJson('/api/v1/mobile/punto-venta/visitas-programadas')
            ->assertOk()
            ->assertJsonPath('visitas.0.cliente.numero_cliente', $cliente->numero_cliente)
            ->assertJsonStructure(['servidor_at', 'visitas' => [['id', 'estado_tiempo', 'cliente', 'registrado_por']]]);
    }

    public function test_api_pdv_confirma_llegada(): void
    {
        $cliente = $this->crearCliente();
        $vendedora = User::factory()->create();

        $visita = VisitaClienteProgramada::query()->create([
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'fecha' => now()->toDateString(),
            'tipo_hora' => VisitaClienteProgramada::TIPO_HORA_SIN,
            'intencion' => VisitaClienteProgramada::INTENCION_CONFIRMO,
            'estado' => VisitaClienteProgramada::ESTADO_PROGRAMADA,
            'registrado_por_user_id' => $vendedora->id,
        ]);

        $this->conToken($this->token())
            ->postJson("/api/v1/mobile/punto-venta/visitas-programadas/{$visita->id}/llegada", [
                'idempotency_key' => 'test:llegada:'.$visita->id,
            ])
            ->assertOk();

        $this->assertSame(VisitaClienteProgramada::ESTADO_ASISTIO, $visita->fresh()->estado);
    }

    public function test_contexto_expone_permisos_de_visitas(): void
    {
        $this->conToken($this->token())
            ->getJson('/api/v1/mobile/punto-venta/contexto')
            ->assertOk()
            ->assertJsonPath('permisos.visitas_programadas_ver', true)
            ->assertJsonPath('permisos.visitas_programadas_confirmar_llegada', true);
    }

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function token(): string
    {
        $device = MobileDevice::query()->create([
            'user_id' => $this->usuario->id,
            'device_uuid' => (string) Str::uuid(),
            'sucursal_activa_id' => $this->sucursal->id,
            'last_seen_at' => now(),
        ]);

        return $this->usuario->createToken('mobile:'.$device->device_uuid, ['mobile'])->plainTextToken;
    }

    private function crearCliente(): Cliente
    {
        $listaId = CatalogoListaDescuento::query()->create([
            'nombre' => 'PUBLICO GENERAL',
            'activo' => true,
        ])->id;

        return Cliente::query()->create([
            'numero_cliente' => '94001',
            'nombre' => 'Cliente visita móvil',
            'lista_actual_id' => $listaId,
            'monto_venta_actual' => 0,
        ]);
    }
}
