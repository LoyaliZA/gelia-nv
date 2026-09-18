<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    public function test_flujo_completo_recibido_en_recepcion_y_custodia(): void
    {
        $resguardo = $this->crearResguardoPendiente(cantidadEsperada: 2);

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $this->payloadRecepcion($resguardo, 'pdv:rec:'.$resguardo->id.':1')
        )->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_RECIBIDO)
            ->assertJsonCount(2, 'resguardo.bultos');

        $resguardo->refresh();
        $folios = $resguardo->bultos->pluck('folio')->all();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.pasar_recepcion', $resguardo),
            [
                'version' => (int) $resguardo->version,
                'idempotency_key' => 'pdv:pasar:'.$resguardo->id.':1',
            ]
        )->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_RECEPCION);

        $this->assertSame(
            ResguardoPdvEvento::TIPO_PASADO_A_RECEPCION,
            ResguardoPdvEvento::query()->orderByDesc('id')->first()->tipo_evento
        );

        $resguardo->refresh();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.custodia', $resguardo),
            [
                'version' => (int) $resguardo->version,
                'idempotency_key' => 'pdv:custodia:'.$resguardo->id.':1',
                'almacen_id' => $this->almacen->id,
                'bultos' => [
                    [
                        'folio' => $folios[0],
                        'tipo' => ResguardoPdvBulto::TIPO_CAJA,
                        'condicion' => 'bueno',
                        'piezas' => 2,
                    ],
                ],
            ]
        )->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_RECEPCION)
            ->assertJsonPath('resguardo.custodia_completa', false);

        $resguardo->refresh();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.custodia', $resguardo),
            [
                'version' => (int) $resguardo->version,
                'idempotency_key' => 'pdv:custodia:'.$resguardo->id.':2',
                'almacen_id' => $this->almacen->id,
                'bultos' => [
                    [
                        'folio' => $folios[1],
                        'tipo' => ResguardoPdvBulto::TIPO_CAJA,
                        'condicion' => 'danado',
                        'piezas' => 1,
                    ],
                ],
            ]
        )->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->assertJsonPath('resguardo.custodia_completa', true);

        $bulto = ResguardoPdvBulto::query()->where('folio', $folios[0])->first();
        $this->assertSame(2, (int) $bulto->piezas);
        $this->assertSame('bueno', $bulto->condicion);
    }

    public function test_custodia_rechazada_si_no_esta_en_recepcion(): void
    {
        $resguardo = $this->crearResguardoPendiente();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            $this->payloadRecepcion($resguardo, 'pdv:rec:'.$resguardo->id.':solo')
        )->assertOk();

        $folio = $resguardo->fresh()->bultos->first()->folio;

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.custodia', $resguardo->fresh()),
            [
                'version' => (int) $resguardo->fresh()->version,
                'idempotency_key' => 'pdv:custodia:'.$resguardo->id.':prematura',
                'almacen_id' => $this->almacen->id,
                'bultos' => [
                    [
                        'folio' => $folio,
                        'tipo' => ResguardoPdvBulto::TIPO_CAJA,
                        'condicion' => 'bueno',
                        'piezas' => 1,
                    ],
                ],
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadRecepcion(ResguardoPdv $resguardo, string $clave): array
    {
        return [
            'version' => (int) $resguardo->version,
            'idempotency_key' => $clave,
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
