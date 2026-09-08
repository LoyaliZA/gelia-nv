<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\EquipoAsistenciaDiaPdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\MotivoPausaPdv;
use App\Models\PuntoVenta\OperacionGestionAuditoriaPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\CompletarCierreJornadaTrasAtencionPdvService;
use App\Services\PuntoVenta\Operacion\GestionarEquipoOperativoPdvService;
use App\Services\PuntoVenta\Operacion\OperacionPdvConfig;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GestionEquipoOperativoPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $gerente;

    private User $vendedor;

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

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Equipo Gerencia']);
        $this->vendedor = $this->crearVendedor('Vendedor Gerencia');
        $this->gerente = $this->crearGerente('Gerente Equipo');
    }

    public function test_gerente_activa_vendedor(): void
    {
        $response = $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.activar', $this->vendedor));

        $response->assertOk();

        $miembro = collect($response->json('equipo'))->firstWhere('id', $this->vendedor->id);
        $this->assertSame('disponible', $miembro['estado_vendedor']);

        $this->assertDatabaseHas('pdv_jornadas', [
            'user_id' => $this->vendedor->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => EstadoJornadaPdv::Abierta->value,
        ]);
    }

    public function test_gerente_marca_no_llego(): void
    {
        $response = $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.no_llego', $this->vendedor));

        $response->assertOk();

        $fecha = app(OperacionPdvConfig::class)->fechaOperativa($this->sucursal->id, now());

        $this->assertSame(1, EquipoAsistenciaDiaPdv::query()
            ->where('user_id', $this->vendedor->id)
            ->where('sucursal_id', $this->sucursal->id)
            ->whereDate('fecha_operativa', $fecha)
            ->count());

        $asistencia = EquipoAsistenciaDiaPdv::query()->sole();
        $this->assertNotNull($asistencia->no_llego_at);
    }

    public function test_activar_limpia_marca_no_llego(): void
    {
        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.no_llego', $this->vendedor))
            ->assertOk();

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.activar', $this->vendedor))
            ->assertOk();

        $asistencia = EquipoAsistenciaDiaPdv::query()->first();
        $this->assertNull($asistencia?->no_llego_at);
    }

    public function test_gerente_coloca_y_quita_pausa(): void
    {
        $motivo = MotivoPausaPdv::query()->where('slug', 'comida')->firstOrFail();

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.activar', $this->vendedor))
            ->assertOk();

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.iniciar', $this->vendedor), [
                'motivo_pausa_id' => $motivo->id,
            ])
            ->assertOk();

        $miembroRetencion = collect(
            $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.datos'))->json('equipo')
        )->firstWhere('id', $this->vendedor->id);
        $this->assertSame('en_retencion', $miembroRetencion['estado_vendedor']);
        $this->assertSame('Comida', $miembroRetencion['pausa_motivo']);

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.pausa.finalizar', $this->vendedor))
            ->assertOk();

        $miembroDisponible = collect(
            $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.datos'))->json('equipo')
        )->firstWhere('id', $this->vendedor->id);
        $this->assertSame('disponible', $miembroDisponible['estado_vendedor']);
    }

    public function test_gerente_cierra_jornada_vendedor(): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());
        $jornada = JornadaPdv::query()->sole();

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.cerrar_jornada', $this->vendedor), [
                'version' => $jornada->version,
            ])
            ->assertOk();

        $miembro = collect(
            $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.datos'))->json('equipo')
        )->firstWhere('id', $this->vendedor->id);
        $this->assertSame('jornada_cerrada', $miembro['estado_vendedor']);
    }

    public function test_vendedor_sin_permiso_gestionar_recibe_403(): void
    {
        $this->actingAs($this->vendedor)
            ->postJson(route('punto_venta.operacion.equipo.activar', $this->vendedor))
            ->assertForbidden();
    }

    public function test_gerente_no_puede_activar_usuario_no_elegible(): void
    {
        $recepcion = User::factory()->create(['name' => 'Solo ver']);
        $recepcion->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
        ]);
        $recepcion->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.activar', $recepcion))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendedor']);
    }

    public function test_gerente_no_puede_operar_vendedor_de_otra_sucursal(): void
    {
        $otraSucursal = Sucursal::factory()->create(['nombre' => 'Otra sucursal']);
        $vendedorRemoto = User::factory()->create(['name' => 'Vendedor remoto']);
        $vendedorRemoto->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $vendedorRemoto->concederAccesoSucursal($otraSucursal, esPrincipal: true);

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.activar', $vendedorRemoto))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendedor']);
    }

    public function test_doble_activacion_no_duplica_jornada(): void
    {
        $servicio = app(GestionarEquipoOperativoPdvService::class);
        $abrir = app(\App\Services\PuntoVenta\Operacion\AbrirJornadaPdvService::class);

        $servicio->activar($this->gerente, $this->vendedor, now());
        $abrir->abrirParaUsuario(
            (int) $this->vendedor->id,
            $this->sucursal->id,
            now(),
            (int) $this->gerente->id,
        );

        $this->assertSame(1, JornadaPdv::query()->count());
    }

    public function test_desactivar_con_atencion_abierta_se_rechaza(): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());
        $jornada = JornadaPdv::query()->sole();

        TurnoPdvAtencion::factory()->create([
            'user_id' => $this->vendedor->id,
            'fin_at' => null,
        ]);

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.desactivar', $this->vendedor), [
                'version' => $jornada->version,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accion']);
    }

    public function test_cerrar_jornada_con_atencion_abierta_marca_cierre_pendiente(): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());
        $jornada = JornadaPdv::query()->sole();

        TurnoPdvAtencion::factory()->create([
            'user_id' => $this->vendedor->id,
            'fin_at' => null,
        ]);

        IntervaloOperativoPdv::query()
            ->where('jornada_id', $jornada->id)
            ->update(['tipo' => TipoIntervaloOperativoPdv::EnAtencion]);

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.cerrar_jornada', $this->vendedor), [
                'version' => $jornada->version,
            ])
            ->assertOk();

        $jornada->refresh();
        $this->assertSame(EstadoJornadaPdv::CerradaConAtencion, $jornada->estado);

        $miembro = collect(
            $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.datos'))->json('equipo')
        )->firstWhere('id', $this->vendedor->id);
        $this->assertSame('cierre_pendiente', $miembro['estado_vendedor']);
        $this->assertContains('cancelar_cierre_pendiente', $miembro['acciones']);
    }

    public function test_cierre_pendiente_se_completa_tras_cerrar_atencion(): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());
        $jornada = JornadaPdv::query()->sole();

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
        ]);

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $this->vendedor->id,
            'fin_at' => null,
        ]);

        IntervaloOperativoPdv::query()
            ->where('jornada_id', $jornada->id)
            ->update(['tipo' => TipoIntervaloOperativoPdv::EnAtencion]);

        app(GestionarEquipoOperativoPdvService::class)->cerrarJornada(
            $this->gerente,
            $this->vendedor,
            $jornada->version,
            now(),
        );

        $atencion->update(['fin_at' => now()]);
        app(CompletarCierreJornadaTrasAtencionPdvService::class)->ejecutar(
            $this->vendedor,
            $this->sucursal->id,
            now(),
        );

        $jornada->refresh();
        $this->assertSame(EstadoJornadaPdv::Cerrada, $jornada->estado);

        $miembro = collect(
            $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.datos'))->json('equipo')
        )->firstWhere('id', $this->vendedor->id);
        $this->assertSame('jornada_cerrada', $miembro['estado_vendedor']);
    }

    public function test_gerente_reactiva_vendedor_con_jornada_cerrada(): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());
        $jornada = JornadaPdv::query()->sole();

        app(GestionarEquipoOperativoPdvService::class)->cerrarJornada(
            $this->gerente,
            $this->vendedor,
            $jornada->version,
            now(),
        );

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.reactivar', $this->vendedor))
            ->assertOk();

        $miembro = collect(
            $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.datos'))->json('equipo')
        )->firstWhere('id', $this->vendedor->id);
        $this->assertSame('disponible', $miembro['estado_vendedor']);
        $this->assertSame(1, JornadaPdv::query()->count());
    }

    public function test_cancelar_cierre_pendiente_reabre_jornada(): void
    {
        app(GestionarEquipoOperativoPdvService::class)->activar($this->gerente, $this->vendedor, now());
        $jornada = JornadaPdv::query()->sole();

        TurnoPdvAtencion::factory()->create([
            'user_id' => $this->vendedor->id,
            'fin_at' => null,
        ]);

        IntervaloOperativoPdv::query()
            ->where('jornada_id', $jornada->id)
            ->update(['tipo' => TipoIntervaloOperativoPdv::EnAtencion]);

        app(GestionarEquipoOperativoPdvService::class)->cerrarJornada(
            $this->gerente,
            $this->vendedor,
            $jornada->version,
            now(),
        );

        $jornada->refresh();

        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.cancelar_cierre_pendiente', $this->vendedor), [
                'version' => $jornada->version,
            ])
            ->assertOk();

        $jornada->refresh();
        $this->assertSame(EstadoJornadaPdv::Abierta, $jornada->estado);
        $this->assertNull($jornada->cierre_at);

        $miembro = collect(
            $this->actingAs($this->gerente)->getJson(route('punto_venta.operacion.datos'))->json('equipo')
        )->firstWhere('id', $this->vendedor->id);
        $this->assertSame('atendiendo', $miembro['estado_vendedor']);
    }

    public function test_accion_gerencial_registra_auditoria(): void
    {
        $this->actingAs($this->gerente)
            ->postJson(route('punto_venta.operacion.equipo.activar', $this->vendedor))
            ->assertOk();

        $this->assertDatabaseHas('pdv_operacion_gestion_auditoria', [
            'actor_id' => $this->gerente->id,
            'user_id' => $this->vendedor->id,
            'sucursal_id' => $this->sucursal->id,
            'accion' => 'activar',
            'estado_anterior' => 'no_activado',
            'estado_nuevo' => 'disponible',
        ]);

        $this->assertSame(1, OperacionGestionAuditoriaPdv::query()->count());
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

    private function crearVendedor(string $nombre): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }

    private function crearGerente(string $nombre): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_TURNOS_VER,
            PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_CERRAR_SUCURSAL,
            PuntoVentaModulo::PERMISO_OPERACION_JORNADA_AMPLIAR,
        ]);
        $user->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($user, $this->sucursal->id);

        return $user;
    }
}
