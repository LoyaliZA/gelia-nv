<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EtiquetasQrResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

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
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    public function test_recepcion_asigna_codigo_etiqueta_unico_por_bulto(): void
    {
        $resguardo = $this->crearResguardoPendiente();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.recepcion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:rec:'.$resguardo->id.':etiqueta',
                'almacen_id' => $this->crearAlmacen()->id,
                'bultos' => [
                    ['folio' => 'CJA-A', 'tipo' => ResguardoPdvBulto::TIPO_CAJA, 'condicion' => 'bueno'],
                    ['folio' => 'CJA-B', 'tipo' => ResguardoPdvBulto::TIPO_CAJA, 'condicion' => 'bueno'],
                ],
            ]
        )->assertOk();

        $codigos = ResguardoPdvBulto::query()
            ->where('resguardo_id', $resguardo->id)
            ->pluck('codigo_etiqueta');

        $this->assertCount(2, $codigos);
        $this->assertSame(2, $codigos->unique()->count());
        $codigos->each(fn (?string $codigo) => $this->assertSame(
            GeneradorCodigoEtiquetaResguardoPdv::LONGITUD,
            strlen((string) $codigo)
        ));
    }

    public function test_resolver_codigo_autorizado_en_sucursal_activa(): void
    {
        $resguardo = $this->crearResguardoConBulto('CJA-ETQ-1', 'Ab3CdEfGhJkL');

        $response = $this->actingAs($this->usuario)->getJson(
            route('punto_venta.resguardos.etiquetas.resolver', ['codigo' => 'Ab3CdEfGhJkL'])
        );

        $response->assertOk()
            ->assertJsonPath('resguardo_id', $resguardo->id)
            ->assertJsonPath('folio', 'CJA-ETQ-1')
            ->assertJsonMissing(['snapshot_cliente_nombre', 'nombre', 'telefono', 'direccion']);
    }

    public function test_resolver_bloquea_acceso_cruzado_de_sucursal(): void
    {
        $this->crearResguardoConBulto('CJA-OTRA', 'Zy9XwVuTsRqP', $this->otraSucursal);

        $this->actingAs($this->usuario)->getJson(
            route('punto_venta.resguardos.etiquetas.resolver', ['codigo' => 'Zy9XwVuTsRqP'])
        )->assertNotFound();
    }

    public function test_codigo_invalido_no_filtra_informacion(): void
    {
        $this->actingAs($this->usuario)->getJson(
            route('punto_venta.resguardos.etiquetas.resolver', ['codigo' => 'CodigoInexistente'])
        )->assertNotFound();
    }

    public function test_descarga_pdf_registra_evento_sin_cambiar_estado(): void
    {
        $resguardo = $this->crearResguardoConBulto('CJA-PDF', 'Mn8LpQrStUvW');
        $estadoAnterior = $resguardo->estado;

        $response = $this->actingAs($this->usuario)->get(
            route('punto_venta.resguardos.etiquetas.descargar', $resguardo)
        );

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));

        $resguardo->refresh();
        $this->assertSame($estadoAnterior, $resguardo->estado);
        $this->assertTrue(
            ResguardoPdvEvento::query()
                ->where('resguardo_id', $resguardo->id)
                ->where('tipo_evento', ResguardoPdvEvento::TIPO_ETIQUETAS_GENERADAS)
                ->exists()
        );
    }

    public function test_listado_encuentra_resguardo_por_codigo_etiqueta(): void
    {
        $resguardo = $this->crearResguardoConBulto('CJA-BUSQ', 'Qw2ErTyUiOpA');
        $resguardo->update(['estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION]);

        $response = $this->actingAs($this->usuario)->getJson(
            route('punto_venta.resguardos.listado', [
                'bandeja' => 'por_recibir',
                'q' => 'Qw2ErTyUiOpA',
            ])
        );

        $response->assertOk()
            ->assertJsonPath('resguardos.data.0.id', $resguardo->id);
    }

    private function crearResguardoPendiente(): ResguardoPdv
    {
        return ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'cantidad_bultos_esperada' => 2,
            'salida_cedis_at' => now()->subHour(),
            'version' => 1,
        ]);
    }

    private function crearResguardoConBulto(
        string $folio,
        string $codigoEtiqueta,
        ?Sucursal $sucursal = null,
    ): ResguardoPdv {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => ($sucursal ?? $this->sucursal)->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'cantidad_bultos_esperada' => 1,
            'recepcion_fisica_at' => now()->subDay(),
            'snapshot_folio' => 'PED-'.$folio,
            'version' => 1,
        ]);

        ResguardoPdvBulto::factory()->create([
            'resguardo_id' => $resguardo->id,
            'folio' => $folio,
            'codigo_etiqueta' => $codigoEtiqueta,
            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
            'recepcion_at' => now()->subDay(),
            'recepcion_por_id' => $this->usuario->id,
        ]);

        return $resguardo;
    }

    private function crearAlmacen(): \App\Models\Almacen
    {
        return \App\Models\Almacen::query()->create([
            'codigo' => 'PISO-ETQ',
            'nombre' => 'Piso etiquetas',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
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
