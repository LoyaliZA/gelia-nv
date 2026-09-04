<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\ResguardoPdvVencidoRepuesto;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\CalcularAntiguedadOperativaResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\ReponerVencidoResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReponerVencidoResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $operador;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-28 12:00:00', 'America/Mexico_City'));

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        $this->withoutVite();
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);

        $this->gerente = User::factory()->create();
        $this->gerente->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO,
        ]);
        $this->gerente->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->operador = User::factory()->create();
        $this->operador->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $this->operador->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reponer_vencido_lo_muestra_en_bandeja_principal_sin_reiniciar_plazo(): void
    {
        Event::fake([ResguardoPdvVencidoRepuesto::class]);

        $recepcion = Carbon::parse('2026-07-01 10:00:00');
        $resguardo = $this->crearResguardoVencido($recepcion);
        $clave = 'pdv:rep:'.$resguardo->id.':test-1';

        $antiguedad = app(CalcularAntiguedadOperativaResguardoPdvService::class);
        $this->assertTrue($antiguedad->debeExcluirDeVistaPrincipal($resguardo));

        $response = $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.reponer_vencido', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => $clave,
                'motivo' => 'Cliente contactado; se autoriza nueva gestión en bandeja principal',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('resguardo.version', 2)
            ->assertJsonPath('evento.motivo', 'Cliente contactado; se autoriza nueva gestión en bandeja principal');

        $resguardo->refresh();
        $this->assertNotNull($resguardo->vencido_repuesto_at);
        $this->assertTrue($resguardo->recepcion_fisica_at->equalTo($recepcion));
        $this->assertFalse($antiguedad->debeExcluirDeVistaPrincipal($resguardo));
        $this->assertTrue($antiguedad->coincideConFiltro($resguardo, AntiguedadOperativaResguardoPdv::VENCIDO));

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertSame(ResguardoPdvEvento::TIPO_VENCIDO_REPUESTO, $evento->tipo_evento);
        $this->assertSame($this->gerente->id, $evento->actor_id);
        $this->assertSame($clave, $evento->idempotency_key);
        $this->assertSame('Cliente contactado; se autoriza nueva gestión en bandeja principal', $evento->snapshot_json['motivo'] ?? null);
        $this->assertFalse($evento->snapshot_json['plazo_reiniciado'] ?? true);

        $listado = $this->actingAs($this->operador)->getJson(route('punto_venta.resguardos.listado', [
            'bandeja' => BandejaResguardoPdv::EN_CUSTODIA,
        ]));

        $listado->assertOk()
            ->assertJsonPath('resguardos.total', 1)
            ->assertJsonPath('resguardos.data.0.id', $resguardo->id);

        Event::assertDispatched(ResguardoPdvVencidoRepuesto::class);
    }

    public function test_reponer_vencido_es_idempotente(): void
    {
        $resguardo = $this->crearResguardoVencido();
        $clave = 'pdv:rep:'.$resguardo->id.':idempotent';

        $payload = [
            'version' => 1,
            'idempotency_key' => $clave,
            'motivo' => 'Reposición autorizada por gerencia',
        ];

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.reponer_vencido', $resguardo),
            $payload
        )->assertOk();

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.reponer_vencido', $resguardo),
            $payload
        )->assertOk();

        $this->assertSame(
            1,
            ResguardoPdvEvento::query()
                ->where('resguardo_id', $resguardo->id)
                ->where('tipo_evento', ResguardoPdvEvento::TIPO_VENCIDO_REPUESTO)
                ->count()
        );
    }

    public function test_reponer_vencido_rechazado_sin_permiso(): void
    {
        $resguardo = $this->crearResguardoVencido();

        $this->actingAs($this->operador)->putJson(
            route('punto_venta.resguardos.reponer_vencido', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:rep:'.$resguardo->id.':denegado',
                'motivo' => 'Intento sin permiso',
            ]
        )->assertForbidden();

        $this->assertNull($resguardo->fresh()->vencido_repuesto_at);
    }

    public function test_reponer_vencido_rechazado_si_no_esta_clasificado_vencido(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-08-27 10:00:00'),
            'version' => 1,
        ]);

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.reponer_vencido', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:rep:'.$resguardo->id.':no-vencido',
                'motivo' => 'Intento sobre resguardo vigente',
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['clasificacion']);
    }

    public function test_servicio_registra_auditoria_con_actor_y_timestamp(): void
    {
        $resguardo = $this->crearResguardoVencido();
        $motivo = 'Gestión operativa en piso';

        $actualizado = app(ReponerVencidoResguardoPdvService::class)->ejecutar(
            $resguardo,
            $this->gerente,
            1,
            'pdv:rep:'.$resguardo->id.':servicio',
            $motivo,
        );

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $actualizado->id)->first();

        $this->assertNotNull($evento);
        $this->assertSame($this->gerente->id, $evento->actor_id);
        $this->assertNotNull($evento->ocurrido_at);
        $this->assertSame($motivo, $evento->snapshot_json['motivo'] ?? null);
    }

    private function crearResguardoVencido(?Carbon $recepcion = null): ResguardoPdv
    {
        return ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => $recepcion ?? Carbon::parse('2026-07-01 10:00:00'),
            'snapshot_folio' => 'REM-VEN-001',
            'version' => 1,
        ]);
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
