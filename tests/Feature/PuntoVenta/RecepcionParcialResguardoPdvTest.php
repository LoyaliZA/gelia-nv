<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\RecepcionFisicaPdvCompletada;
use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarRecepcionFisicaPdvService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecepcionParcialResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    private Almacen $almacen;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->almacen = Almacen::query()->create([
            'codigo' => 'PISO-1',
            'nombre' => 'Piso recepción',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    public function test_dos_llegadas_completan_un_resguardo(): void
    {
        Event::fake([RecepcionFisicaPdvCompletada::class]);

        $resguardo = $this->crearResguardoPendiente(cantidadEsperada: 2);

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $this->payloadLlegada($resguardo, ['CJA-001'], 'pdv:rec:'.$resguardo->id.':llegada-1')
        )->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->assertJsonPath('resguardo.recepcion_completa', false)
            ->assertJsonPath('resguardo.cantidad_bultos_recibida', 1)
            ->assertJsonPath('resguardo.cantidad_bultos_pendiente', 1)
            ->assertJsonCount(1, 'resguardo.bultos');

        $resguardo->refresh();
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        $this->assertSame(
            ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL,
            ResguardoPdvEvento::query()->first()->tipo_evento
        );

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $this->payloadLlegada($resguardo, ['CJA-002'], 'pdv:rec:'.$resguardo->id.':llegada-2')
        )->assertOk()
            ->assertJsonPath('resguardo.recepcion_completa', true)
            ->assertJsonPath('resguardo.cantidad_bultos_recibida', 2)
            ->assertJsonPath('resguardo.cantidad_bultos_pendiente', 0)
            ->assertJsonCount(2, 'resguardo.bultos');

        $this->assertSame(2, ResguardoPdvBulto::query()->count());
        $this->assertSame(2, ResguardoPdvEvento::query()->count());
        $this->assertSame(
            ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            ResguardoPdvEvento::query()->orderByDesc('id')->first()->tipo_evento
        );
        Event::assertDispatchedTimes(RecepcionFisicaPdvCompletada::class, 2);
    }

    public function test_rechaza_folio_duplicado_exceso_y_version_obsoleta(): void
    {
        $resguardo = $this->crearResguardoPendiente(cantidadEsperada: 2);

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $this->payloadLlegada($resguardo, ['CJA-001'], 'pdv:rec:'.$resguardo->id.':ok')
        )->assertOk();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo->fresh()),
            $this->payloadLlegada($resguardo->fresh(), ['CJA-001', 'CJA-002'], 'pdv:rec:'.$resguardo->id.':exceso')
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['bultos']);

        $payloadDuplicado = $this->payloadLlegada($resguardo->fresh(), ['CJA-001'], 'pdv:rec:'.$resguardo->id.':dup');
        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo->fresh()),
            $payloadDuplicado
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['bultos.0.folio']);

        $payloadVersion = $this->payloadLlegada($resguardo->fresh(), ['CJA-002'], 'pdv:rec:'.$resguardo->id.':ver');
        $payloadVersion['version'] = 1;
        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo->fresh()),
            $payloadVersion
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_dos_terminales_agregan_el_mismo_bulto_solo_uno_efectivo(): void
    {
        Event::fake([RecepcionFisicaPdvCompletada::class]);

        $resguardo = $this->crearResguardoPendiente(cantidadEsperada: 2);
        $bulto = [
            'folio' => 'CJA-RACE',
            'tipo' => ResguardoPdvBulto::TIPO_CAJA,
            'condicion' => 'bueno',
        ];

        $servicio = app(RegistrarRecepcionFisicaPdvService::class);

        $servicio->ejecutar(
            $resguardo,
            $this->usuario,
            1,
            'pdv:rec:'.$resguardo->id.':race-a',
            $this->almacen->id,
            [$bulto],
        );

        try {
            $servicio->ejecutar(
                $resguardo->fresh(['bultos']),
                $this->usuario,
                2,
                'pdv:rec:'.$resguardo->id.':race-b',
                $this->almacen->id,
                [$bulto],
            );
            $this->fail('Debía rechazar el folio duplicado');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('bultos.0.folio', $e->errors());
        }

        $this->assertSame(1, ResguardoPdvBulto::query()->where('folio', 'CJA-RACE')->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        Event::assertDispatchedTimes(RecepcionFisicaPdvCompletada::class, 1);
    }

    public function test_historial_conserva_cada_llegada(): void
    {
        $resguardo = $this->crearResguardoPendiente(cantidadEsperada: 3);

        foreach (['CJA-A', 'CJA-B', 'CJA-C'] as $indice => $folio) {
            $this->actingAs($this->usuario)->putJson(
                route('punto_venta.resguardos.recepcion', $resguardo->fresh()),
                $this->payloadLlegada($resguardo->fresh(), [$folio], 'pdv:rec:'.$resguardo->id.':h-'.$indice)
            )->assertOk();
        }

        $eventos = ResguardoPdvEvento::query()
            ->where('resguardo_id', $resguardo->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $eventos);
        $this->assertSame(ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL, $eventos[0]->tipo_evento);
        $this->assertSame(ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL, $eventos[1]->tipo_evento);
        $this->assertSame(ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA, $eventos[2]->tipo_evento);
        $this->assertSame(['CJA-A'], array_column($eventos[0]->snapshot_json['bultos'], 'folio'));
        $this->assertSame(['CJA-B'], array_column($eventos[1]->snapshot_json['bultos'], 'folio'));
        $this->assertSame(['CJA-C'], array_column($eventos[2]->snapshot_json['bultos'], 'folio'));
        $this->assertTrue($eventos[2]->snapshot_json['recepcion_completa']);
    }

    public function test_llegada_complementaria_rechazada_si_ya_esta_completo(): void
    {
        $resguardo = $this->crearResguardoPendiente(cantidadEsperada: 1);

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $this->payloadLlegada($resguardo, ['CJA-UNO'], 'pdv:rec:'.$resguardo->id.':full')
        )->assertOk();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo->fresh()),
            $this->payloadLlegada($resguardo->fresh(), ['CJA-EXTRA'], 'pdv:rec:'.$resguardo->id.':extra')
        )->assertStatus(409);

        $this->assertSame(1, ResguardoPdvBulto::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
    }

    /**
     * @param  list<string>  $folios
     * @return array<string, mixed>
     */
    private function payloadLlegada(ResguardoPdv $resguardo, array $folios, string $clave): array
    {
        return [
            'version' => (int) $resguardo->version,
            'idempotency_key' => $clave,
            'almacen_id' => $this->almacen->id,
            'bultos' => array_map(
                fn (string $folio) => [
                    'folio' => $folio,
                    'tipo' => ResguardoPdvBulto::TIPO_CAJA,
                    'condicion' => 'bueno',
                ],
                $folios
            ),
        ];
    }

    private function crearResguardoPendiente(int $cantidadEsperada = 1): ResguardoPdv
    {
        return ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'cantidad_bultos_esperada' => $cantidadEsperada,
            'salida_cedis_at' => now()->subHour(),
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
