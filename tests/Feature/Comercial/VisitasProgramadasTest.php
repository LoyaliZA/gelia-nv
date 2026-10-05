<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Comercial\VisitaClienteProgramada;
use App\Models\CatalogoListaDescuento;
use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\Comercial\AlertaVisitaProgramadaNotification;
use App\Services\Comercial\VisitasProgramadas\CerrarVisitasProgramadasVencidasService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisitasProgramadasTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedora;

    private User $recepcion;

    private Cliente $cliente;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            ThrottleRequests::class,
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        foreach ([
            'visitas_programadas.gestionar',
            'mis_clientes.gestionar',
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_VER,
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_CONFIRMAR_LLEGADA,
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );

        $listaId = CatalogoListaDescuento::query()->create([
            'nombre' => 'PUBLICO GENERAL',
            'activo' => true,
        ])->id;

        $this->sucursal = Sucursal::factory()->create();
        $this->vendedora = User::factory()->create();
        $rolVendedora = Role::findOrCreate('vendedora_visitas_test', 'web');
        $rolVendedora->syncPermissions(['visitas_programadas.gestionar', 'mis_clientes.gestionar']);
        $this->vendedora->assignRole($rolVendedora);

        $this->recepcion = User::factory()->create();
        $rolRecepcion = Role::findOrCreate('recepcion_visitas_test', 'web');
        $rolRecepcion->syncPermissions([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_VER,
            PuntoVentaModulo::PERMISO_VISITAS_PROGRAMADAS_CONFIRMAR_LLEGADA,
        ]);
        $this->recepcion->assignRole($rolRecepcion);
        $this->recepcion->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->cliente = Cliente::query()->create([
            'numero_cliente' => '92001',
            'nombre' => 'Cliente visita prueba',
            'vendedor_id' => $this->vendedora->id,
            'lista_actual_id' => $listaId,
            'monto_venta_actual' => 0,
        ]);
    }

    public function test_acceso_web_operaciones_requiere_permiso(): void
    {
        $otro = User::factory()->create();

        $this->actingAs($otro)
            ->get(route('visitas_programadas.index'))
            ->assertForbidden();

        $this->actingAs($this->vendedora)
            ->get(route('visitas_programadas.index'))
            ->assertOk();
    }

    public function test_registro_y_llegada_notifica_a_vendedora(): void
    {
        Notification::fake();

        $this->actingAs($this->vendedora)
            ->post(route('visitas_programadas.store'), [
                'cliente_id' => $this->cliente->id,
                'sucursal_id' => $this->sucursal->id,
                'fecha' => now()->toDateString(),
                'tipo_hora' => VisitaClienteProgramada::TIPO_HORA_EXACTA,
                'hora_exacta' => '10:00',
                'intencion' => VisitaClienteProgramada::INTENCION_CONFIRMO,
            ])
            ->assertRedirect(route('visitas_programadas.index'));

        $visita = VisitaClienteProgramada::query()->first();
        $this->assertNotNull($visita);

        $this->actingAs($this->recepcion)
            ->post(route('punto_venta.visitas_programadas.llegada', $visita))
            ->assertRedirect();

        $visita->refresh();
        $this->assertSame(VisitaClienteProgramada::ESTADO_ASISTIO, $visita->estado);

        Notification::assertSentTo($this->vendedora, AlertaVisitaProgramadaNotification::class);
    }

    public function test_buscar_cliente_por_numero_permite_un_digito_y_limita_resultados(): void
    {
        Cliente::query()->create([
            'numero_cliente' => '91',
            'nombre' => 'Otro cliente',
            'lista_actual_id' => $this->cliente->lista_actual_id,
            'monto_venta_actual' => 0,
        ]);

        $response = $this->actingAs($this->vendedora)
            ->getJson(route('visitas_programadas.buscar_cliente', ['modo' => 'numero', 'q' => '9']));

        $response->assertOk();
        $this->assertLessThanOrEqual(5, count($response->json('data')));
        $this->assertTrue(collect($response->json('data'))->contains('numero_cliente', '92001'));
    }

    public function test_no_registra_visita_sin_acceso_al_cliente(): void
    {
        $otroCliente = Cliente::query()->create([
            'numero_cliente' => '92002',
            'nombre' => 'Cliente de otro vendedor',
            'vendedor_id' => User::factory()->create()->id,
            'lista_actual_id' => CatalogoListaDescuento::query()->value('id')
                ?? CatalogoListaDescuento::query()->create(['nombre' => 'LISTA', 'activo' => true])->id,
            'monto_venta_actual' => 0,
        ]);

        $this->actingAs($this->vendedora)
            ->post(route('visitas_programadas.store'), [
                'cliente_id' => $otroCliente->id,
                'sucursal_id' => $this->sucursal->id,
                'fecha' => now()->toDateString(),
                'tipo_hora' => VisitaClienteProgramada::TIPO_HORA_SIN,
                'intencion' => VisitaClienteProgramada::INTENCION_CONFIRMO,
            ])
            ->assertForbidden();
    }

    public function test_comando_cierra_visitas_vencidas(): void
    {
        VisitaClienteProgramada::query()->create([
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'fecha' => now()->subDay()->toDateString(),
            'tipo_hora' => VisitaClienteProgramada::TIPO_HORA_SIN,
            'intencion' => VisitaClienteProgramada::INTENCION_POSIBLE,
            'estado' => VisitaClienteProgramada::ESTADO_PROGRAMADA,
            'registrado_por_user_id' => $this->vendedora->id,
        ]);

        app(CerrarVisitasProgramadasVencidasService::class)->handle();

        $this->assertSame(
            VisitaClienteProgramada::ESTADO_NO_ASISTIO,
            VisitaClienteProgramada::query()->value('estado')
        );
    }
}
