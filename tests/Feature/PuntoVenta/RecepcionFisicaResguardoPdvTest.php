<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\RecepcionFisicaPdvCompletada;
use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarRecepcionFisicaPdvService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecepcionFisicaResguardoPdvTest extends TestCase
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
        Storage::fake('local');

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

    public function test_recepcion_valida_transiciona_a_custodia_con_bultos_ubicacion_y_evidencia(): void
    {
        Event::fake([RecepcionFisicaPdvCompletada::class]);

        $resguardo = $this->crearResguardoPendiente(cantidadEsperada: 2);
        $clave = 'pdv:rec:'.$resguardo->id.':terminal-1';

        $response = $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => $clave,
                'almacen_id' => $this->almacen->id,
                'bultos' => [
                    ['folio' => 'CJA-001', 'tipo' => ResguardoPdvBulto::TIPO_CAJA, 'condicion' => 'bueno', 'piezas' => 1],
                    ['folio' => 'CJA-002', 'tipo' => ResguardoPdvBulto::TIPO_CAJA, 'condicion' => 'bueno', 'piezas' => 2],
                ],
                'evidencias' => [
                    UploadedFile::fake()->image('llegada.jpg'),
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->assertJsonPath('resguardo.version', 2)
            ->assertJsonPath('resguardo.almacen_id', $this->almacen->id)
            ->assertJsonCount(2, 'resguardo.bultos');

        $resguardo->refresh();
        $this->assertNotNull($resguardo->recepcion_fisica_at);
        $this->assertSame(2, ResguardoPdvBulto::query()->where('resguardo_id', $resguardo->id)->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->count());
        $this->assertSame(1, ResguardoPdvEvidencia::query()->where('resguardo_id', $resguardo->id)->count());

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertSame(ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA, $evento->tipo_evento);
        $this->assertSame($clave, $evento->idempotency_key);
        $this->assertSame($this->usuario->id, $evento->actor_id);
        $this->assertSame('bueno', $evento->snapshot_json['bultos'][0]['condicion'] ?? null);

        Event::assertDispatched(RecepcionFisicaPdvCompletada::class);
    }

    public function test_validaciones_obligatorias_y_permiso(): void
    {
        $resguardo = $this->crearResguardoPendiente();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            []
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['version', 'idempotency_key', 'almacen_id', 'bultos']);

        $sinRecibir = User::factory()->create();
        $sinRecibir->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $sinRecibir->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->actingAs($sinRecibir)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $this->payloadRecepcion($resguardo)
        )->assertForbidden();
    }

    public function test_dos_terminales_solo_una_transicion_efectiva(): void
    {
        Event::fake([RecepcionFisicaPdvCompletada::class]);

        $resguardo = $this->crearResguardoPendiente();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $this->payloadRecepcion($resguardo, clave: 'pdv:rec:'.$resguardo->id.':a')
        )->assertOk();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo->fresh()),
            $this->payloadRecepcion($resguardo->fresh(), clave: 'pdv:rec:'.$resguardo->id.':b')
        )->assertStatus(409);

        $this->assertSame(1, ResguardoPdvBulto::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        Event::assertDispatchedTimes(RecepcionFisicaPdvCompletada::class, 1);
    }

    public function test_reintento_idempotente_devuelve_mismo_resultado_sin_duplicar(): void
    {
        Event::fake([RecepcionFisicaPdvCompletada::class]);

        $resguardo = $this->crearResguardoPendiente();
        $payload = $this->payloadRecepcion($resguardo);

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $payload
        )->assertOk();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo->fresh()),
            $payload
        )->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA);

        $this->assertSame(1, ResguardoPdvBulto::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        Event::assertDispatchedTimes(RecepcionFisicaPdvCompletada::class, 1);
    }

    public function test_version_obsoleta_rechazada(): void
    {
        $resguardo = $this->crearResguardoPendiente();

        $payload = $this->payloadRecepcion($resguardo);
        $payload['version'] = 99;

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $payload
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);

        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->fresh()->estado);
        $this->assertSame(0, ResguardoPdvBulto::query()->count());
    }

    public function test_transaccion_fallida_no_deja_bultos_eventos_ni_archivos(): void
    {
        $resguardo = $this->crearResguardoPendiente();
        $archivo = UploadedFile::fake()->image('rollback.jpg');

        $publicado = false;
        Event::listen(RecepcionFisicaPdvCompletada::class, function () use (&$publicado) {
            $publicado = true;
        });

        ResguardoPdvEvidencia::creating(function () {
            throw new \RuntimeException('fallo forzado evidencia');
        });

        try {
            app(RegistrarRecepcionFisicaPdvService::class)->ejecutar(
                $resguardo,
                $this->usuario,
                1,
                'pdv:rec:'.$resguardo->id.':rollback',
                $this->almacen->id,
                [
                    ['folio' => 'CJA-ROLL', 'tipo' => ResguardoPdvBulto::TIPO_CAJA, 'condicion' => 'bueno'],
                ],
                [$archivo]
            );
            $this->fail('Debía revertir la transacción');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo forzado evidencia', $e->getMessage());
        } finally {
            ResguardoPdvEvidencia::flushEventListeners();
        }

        $this->assertSame(0, ResguardoPdvBulto::query()->count());
        $this->assertSame(0, ResguardoPdvEvento::query()->count());
        $this->assertSame(0, ResguardoPdvEvidencia::query()->count());
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->fresh()->estado);
        $this->assertFalse($publicado);
        Storage::disk('local')->assertDirectoryEmpty('pdv/resguardos/'.$resguardo->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadRecepcion(ResguardoPdv $resguardo, ?string $clave = null): array
    {
        return [
            'version' => (int) $resguardo->version,
            'idempotency_key' => $clave ?? 'pdv:rec:'.$resguardo->id.':default',
            'almacen_id' => $this->almacen->id,
            'bultos' => [
                [
                    'folio' => 'CJA-'.$resguardo->id,
                    'tipo' => ResguardoPdvBulto::TIPO_CAJA,
                    'condicion' => 'bueno',
                ],
            ],
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
