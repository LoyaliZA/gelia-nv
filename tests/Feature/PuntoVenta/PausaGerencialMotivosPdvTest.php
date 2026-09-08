<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\MotivoPausaPdv;
use App\Models\PuntoVenta\OperacionGestionAuditoriaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService;
use App\Services\PuntoVenta\Operacion\ConsultaPersonaDisponiblePdvService;
use App\Services\PuntoVenta\Operacion\GestionarEquipoOperativoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PausaGerencialMotivosPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    private User $gerente;

    private User $vendedor;

    private MotivoPausaPdv $motivoComida;

    private MotivoPausaPdv $motivoOtro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Pausa Motivos']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Remota']);
        $this->vendedor = $this->crearVendedor('Vendedor Motivos');
        $this->gerente = $this->crearGerente('Gerente Motivos');

        $this->motivoComida = MotivoPausaPdv::query()->where('slug', 'comida')->firstOrFail();
        $this->motivoOtro = MotivoPausaPdv::query()->where('slug', 'otro')->firstOrFail();
    }

    public function test_gerente_pausa_vendedor_disponible_con_motivo(): void
    {
        $this->activarVendedor();

        $response = $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $this->vendedor), [
                'motivo_pausa_id' => $this->motivoComida->id,
            ]);

        $response->assertOk();

        $intervalo = IntervaloOperativoPdv::query()->whereNull('fin_at')->sole();
        $this->assertSame(TipoIntervaloOperativoPdv::EnPausa, $intervalo->tipo);
        $this->assertSame($this->motivoComida->id, $intervalo->motivo_pausa_id);
        $this->assertSame($this->gerente->id, $intervalo->pausa_iniciada_por_id);

        $miembro = collect($response->json('equipo'))->firstWhere('id', $this->vendedor->id);
        $this->assertSame('en_retencion', $miembro['estado_vendedor']);
        $this->assertSame('Comida', $miembro['pausa_motivo']);
        $this->assertSame('Tiempo en pausa', $miembro['cronometro']['etiqueta']);
    }

    public function test_no_puede_pausar_vendedor_atendiendo(): void
    {
        $this->activarVendedor();

        TurnoPdvAtencion::factory()->create([
            'user_id' => $this->vendedor->id,
            'fin_at' => null,
        ]);

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $this->vendedor), [
                'motivo_pausa_id' => $this->motivoComida->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accion']);
    }

    public function test_usuario_sin_permiso_gestionar_no_puede_pausar(): void
    {
        $this->activarVendedor();

        $this->actingAs($this->vendedor)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $this->vendedor), [
                'motivo_pausa_id' => $this->motivoComida->id,
            ])
            ->assertForbidden();
    }

    public function test_rechaza_pausa_vendedor_de_otra_sucursal(): void
    {
        $vendedorRemoto = User::factory()->create(['name' => 'Vendedor remoto']);
        $vendedorRemoto->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $vendedorRemoto->concederAccesoSucursal($this->otraSucursal, esPrincipal: true);

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $vendedorRemoto), [
                'motivo_pausa_id' => $this->motivoComida->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendedor']);
    }

    public function test_motivo_inactivo_no_se_acepta(): void
    {
        $this->activarVendedor();
        $this->motivoComida->update(['activo' => false]);

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $this->vendedor), [
                'motivo_pausa_id' => $this->motivoComida->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_pausa_id']);
    }

    public function test_otro_sin_detalle_se_rechaza(): void
    {
        $this->activarVendedor();

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $this->vendedor), [
                'motivo_pausa_id' => $this->motivoOtro->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo_detalle']);
    }

    public function test_otro_con_detalle_se_acepta_y_serializa(): void
    {
        $this->activarVendedor();

        $response = $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $this->vendedor), [
                'motivo_pausa_id' => $this->motivoOtro->id,
                'motivo_detalle' => 'Revisión de inventario',
            ]);

        $response->assertOk();

        $intervalo = IntervaloOperativoPdv::query()->whereNull('fin_at')->sole();
        $this->assertSame('Revisión de inventario', $intervalo->motivo_detalle);

        $miembro = collect($response->json('equipo'))->firstWhere('id', $this->vendedor->id);
        $this->assertSame('Otro — Revisión de inventario', $miembro['pausa_motivo']);
    }

    public function test_finalizacion_registra_actor_y_vuelve_a_disponible(): void
    {
        $this->activarVendedor();
        $servicioDisponible = app(ConsultaPersonaDisponiblePdvService::class);

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $this->vendedor), [
                'motivo_pausa_id' => $this->motivoComida->id,
            ])
            ->assertOk();

        $this->assertFalse($servicioDisponible->esDisponible($this->vendedor, $this->sucursal->id));

        $response = $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.finalizar', $this->vendedor));

        $response->assertOk();

        $intervaloPausa = IntervaloOperativoPdv::query()
            ->where('tipo', TipoIntervaloOperativoPdv::EnPausa)
            ->sole();
        $this->assertNotNull($intervaloPausa->fin_at);
        $this->assertSame($this->gerente->id, $intervaloPausa->pausa_finalizada_por_id);

        $intervaloAbierto = IntervaloOperativoPdv::query()->whereNull('fin_at')->sole();
        $this->assertSame(TipoIntervaloOperativoPdv::Disponible, $intervaloAbierto->tipo);

        $miembro = collect($response->json('equipo'))->firstWhere('id', $this->vendedor->id);
        $this->assertSame('disponible', $miembro['estado_vendedor']);
        $this->assertTrue($servicioDisponible->esDisponible($this->vendedor, $this->sucursal->id));
    }

    public function test_pausa_queda_auditada_con_contexto(): void
    {
        $this->activarVendedor();

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $this->vendedor), [
                'motivo_pausa_id' => $this->motivoComida->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pdv_operacion_gestion_auditoria', [
            'actor_id' => $this->gerente->id,
            'user_id' => $this->vendedor->id,
            'accion' => 'pausa_iniciar',
        ]);

        $auditoria = OperacionGestionAuditoriaPdv::query()->where('accion', 'pausa_iniciar')->sole();
        $this->assertSame($this->motivoComida->id, $auditoria->contexto['motivo_pausa_id']);
    }

    public function test_matchmaker_excluye_vendedor_en_pausa(): void
    {
        $this->activarVendedor();

        app(GestionarEquipoOperativoPdvService::class)->iniciarPausa(
            $this->gerente,
            $this->vendedor,
            $this->motivoComida->id,
            null,
            now(),
        );

        $this->assertFalse(
            app(ConsultaPersonaDisponiblePdvService::class)->esDisponible($this->vendedor, $this->sucursal->id),
        );

        $disponible = app(ConsultaPersonaDisponiblePdvService::class)
            ->primeraDisponible($this->sucursal->id, 'ventas');

        $this->assertNull($disponible);
    }

    public function test_vendedores_datos_incluye_motivos_activos(): void
    {
        $response = $this->actingAs($this->gerente)
            ->getJson(route('punto_venta.operacion.vendedores.datos'));

        $response->assertOk()
            ->assertJsonStructure([
                'motivos_pausa' => [
                    ['id', 'slug', 'nombre', 'requiere_detalle'],
                ],
            ]);

        $slugs = collect($response->json('motivos_pausa'))->pluck('slug')->all();
        $this->assertContains('comida', $slugs);
        $this->assertContains('otro', $slugs);
    }

    private function activarVendedor(): void
    {
        app(AbrirJornadaPdvService::class)->abrirParaUsuario(
            (int) $this->vendedor->id,
            $this->sucursal->id,
            now(),
            (int) $this->gerente->id,
        );
    }

    private function crearVendedor(string $nombre): User
    {
        $usuario = User::factory()->create(['name' => $nombre]);
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        return $usuario;
    }

    private function crearGerente(string $nombre): User
    {
        $usuario = User::factory()->create(['name' => $nombre]);
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_VER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR,
        ]);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $this->sucursal->id);

        return $usuario;
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }
}
