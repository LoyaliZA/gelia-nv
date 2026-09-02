<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\EntregaResguardoPdvCompletada;
use App\Events\PuntoVenta\IncidenciaResguardoPdvRegistrada;
use App\Events\PuntoVenta\IncidenciaResguardoPdvResuelta;
use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IncidenciaResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    private User $gerente;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

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
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);
        $this->almacen = Almacen::query()->create([
            'codigo' => 'PISO-INC',
            'nombre' => 'Piso incidencias',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->operador = User::factory()->create();
        $this->operador->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO,
            PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_DANO,
            PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
        ]);
        $this->operador->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->gerente = User::factory()->create();
        $this->gerente->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO,
        ]);
        $this->gerente->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    public function test_registra_incidencia_folio_sin_custodia(): void
    {
        Event::fake([IncidenciaResguardoPdvRegistrada::class]);

        $resguardo = $this->crearResguardoPendiente();
        $clave = 'pdv:inc:'.$resguardo->id.':folio-1';

        $response = $this->actingAs($this->operador)->postJson(
            route('punto_venta.resguardos.incidencias.store', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => $clave,
                'tipo' => ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO,
                'descripcion' => 'No aparece el folio escaneado',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_PENDIENTE_RECEPCION)
            ->assertJsonPath('incidencia.tipo', ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO)
            ->assertJsonPath('incidencia.estado', ResguardoPdvIncidencia::ESTADO_ABIERTA);

        $this->assertSame(1, ResguardoPdvIncidencia::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        $this->assertSame(0, ResguardoPdvBulto::query()->count());

        $evento = ResguardoPdvEvento::query()->first();
        $this->assertSame(ResguardoPdvEvento::TIPO_INCIDENCIA_FOLIO_NO_ENCONTRADO, $evento->tipo_evento);
        Event::assertDispatched(IncidenciaResguardoPdvRegistrada::class);
    }

    public function test_registra_incidencia_dano_recibiendo_bulto_con_foto(): void
    {
        Event::fake([IncidenciaResguardoPdvRegistrada::class]);

        $resguardo = $this->crearResguardoPendiente(cantidadEsperada: 2);
        $clave = 'pdv:inc:'.$resguardo->id.':dano-1';

        $response = $this->actingAs($this->operador)->postJson(
            route('punto_venta.resguardos.incidencias.store', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => $clave,
                'tipo' => ResguardoPdvIncidencia::TIPO_DANO,
                'descripcion' => 'Caja visiblemente golpeada',
                'almacen_id' => $this->almacen->id,
                'bulto' => [
                    'folio' => 'CJA-DAN-1',
                    'tipo' => ResguardoPdvBulto::TIPO_CAJA,
                    'condicion' => 'danado',
                ],
                'evidencias' => [
                    UploadedFile::fake()->image('dano.jpg'),
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->assertJsonPath('incidencia.tipo', ResguardoPdvIncidencia::TIPO_DANO);

        $resguardo->refresh();
        $this->assertNotNull($resguardo->recepcion_fisica_at);
        $this->assertSame(1, ResguardoPdvBulto::query()->count());
        $this->assertSame(1, ResguardoPdvEvidencia::query()->count());
        Event::assertDispatched(IncidenciaResguardoPdvRegistrada::class);
    }

    public function test_registra_incidencia_faltante_exige_foto(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();

        $this->actingAs($this->operador)->postJson(
            route('punto_venta.resguardos.incidencias.store', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:inc:'.$resguardo->id.':faltante-sin-foto',
                'tipo' => ResguardoPdvIncidencia::TIPO_FALTANTE,
                'descripcion' => 'Falta un bulto del pedido',
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['evidencias']);
    }

    public function test_autoriza_incidencia_dano_sin_sobrescribir_reporte(): void
    {
        Event::fake([IncidenciaResguardoPdvResuelta::class]);

        $resguardo = $this->crearResguardoEnCustodia();
        $incidencia = ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_DANO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Reporte original intacto',
            'reportado_por_id' => $this->operador->id,
            'reportado_at' => now()->subMinutes(10),
            'idempotency_key' => 'inc-preexistente',
            'version' => 1,
        ]);

        $response = $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.incidencias.resolver', [$resguardo, $incidencia]),
            [
                'version' => 1,
                'incidencia_version' => 1,
                'idempotency_key' => 'pdv:inc-res:'.$incidencia->id.':autorizar',
                'motivo_resolucion' => 'Gerencia autoriza entrega con daño menor',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('incidencia.estado', ResguardoPdvIncidencia::ESTADO_AUTORIZADA)
            ->assertJsonPath('incidencia.descripcion', 'Reporte original intacto')
            ->assertJsonPath('incidencia.motivo_resolucion', 'Gerencia autoriza entrega con daño menor');

        $incidencia->refresh();
        $this->assertSame('Reporte original intacto', $incidencia->descripcion);
        $this->assertSame($this->gerente->id, $incidencia->autorizado_por_id);
        $this->assertNotNull($incidencia->autorizado_at);

        $evento = ResguardoPdvEvento::query()->where('tipo_evento', ResguardoPdvEvento::TIPO_INCIDENCIA_ENTREGA_AUTORIZADA)->first();
        $this->assertNotNull($evento);
        $this->assertSame('Reporte original intacto', $evento->snapshot_json['descripcion_original'] ?? null);
        Event::assertDispatched(IncidenciaResguardoPdvResuelta::class);
    }

    public function test_entrega_permitida_tras_autorizar_incidencia_dano(): void
    {
        Event::fake([EntregaResguardoPdvCompletada::class]);

        $resguardo = $this->crearResguardoEnCustodia();
        $bulto = ResguardoPdvBulto::query()->where('resguardo_id', $resguardo->id)->first();
        $incidencia = ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'bulto_id' => $bulto->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_DANO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Daño en esquina',
            'reportado_por_id' => $this->operador->id,
            'reportado_at' => now()->subMinutes(5),
            'idempotency_key' => 'inc-entrega-bloqueada',
            'version' => 1,
        ]);

        $this->actingAs($this->operador)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            $this->payloadEntrega($resguardo)
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.incidencias.resolver', [$resguardo, $incidencia]),
            [
                'version' => 1,
                'incidencia_version' => 1,
                'idempotency_key' => 'pdv:inc-res:'.$incidencia->id.':ok',
                'motivo_resolucion' => 'Autorizado por gerencia',
            ]
        )->assertOk();

        $this->actingAs($this->operador)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo->fresh()),
            $this->payloadEntrega($resguardo->fresh(), version: 2)
        )->assertOk();
    }

    public function test_cierra_incidencia_folio_con_resolucion(): void
    {
        Event::fake([IncidenciaResguardoPdvResuelta::class]);

        $resguardo = $this->crearResguardoPendiente();
        $incidencia = ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Folio no localizado en llegada',
            'reportado_por_id' => $this->operador->id,
            'reportado_at' => now()->subHour(),
            'idempotency_key' => 'inc-folio-abierta',
            'version' => 1,
        ]);
        $resguardo->update(['version' => 2]);

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.incidencias.resolver', [$resguardo->fresh(), $incidencia]),
            [
                'version' => 2,
                'incidencia_version' => 1,
                'idempotency_key' => 'pdv:inc-res:'.$incidencia->id.':cerrar',
                'motivo_resolucion' => 'CEDIS confirmó corrección de folio',
            ]
        )->assertOk()
            ->assertJsonPath('incidencia.estado', ResguardoPdvIncidencia::ESTADO_CERRADA);

        $this->assertDatabaseHas('pdv_resguardo_eventos', [
            'tipo_evento' => ResguardoPdvEvento::TIPO_INCIDENCIA_CERRADA,
        ]);
        Event::assertDispatched(IncidenciaResguardoPdvResuelta::class);
    }

    public function test_resolucion_rechazada_sin_permiso(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();
        $incidencia = ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_DANO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Daño pendiente',
            'reportado_por_id' => $this->operador->id,
            'reportado_at' => now(),
            'idempotency_key' => 'inc-sin-autorizar',
            'version' => 1,
        ]);

        $this->actingAs($this->operador)->putJson(
            route('punto_venta.resguardos.incidencias.resolver', [$resguardo, $incidencia]),
            [
                'version' => 1,
                'incidencia_version' => 1,
                'idempotency_key' => 'pdv:inc-res:'.$incidencia->id.':denegado',
                'motivo_resolucion' => 'Intento sin permiso',
            ]
        )->assertForbidden();
    }

    public function test_aislamiento_por_sucursal(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->otraSucursal->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'cantidad_bultos_esperada' => 1,
            'version' => 1,
        ]);

        $this->actingAs($this->operador)->postJson(
            route('punto_venta.resguardos.incidencias.store', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:inc:'.$resguardo->id.':otra-sucursal',
                'tipo' => ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO,
                'descripcion' => 'Intento fuera de alcance',
            ]
        )->assertForbidden();
    }

    public function test_reintento_idempotente_no_duplica_incidencia(): void
    {
        Event::fake([IncidenciaResguardoPdvRegistrada::class]);

        $resguardo = $this->crearResguardoPendiente();
        $payload = [
            'version' => 1,
            'idempotency_key' => 'pdv:inc:'.$resguardo->id.':idempotente',
            'tipo' => ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO,
            'descripcion' => 'Folio no encontrado',
        ];

        $this->actingAs($this->operador)->postJson(
            route('punto_venta.resguardos.incidencias.store', $resguardo),
            $payload
        )->assertOk();

        $this->actingAs($this->operador)->postJson(
            route('punto_venta.resguardos.incidencias.store', $resguardo->fresh()),
            $payload
        )->assertOk();

        $this->assertSame(1, ResguardoPdvIncidencia::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        Event::assertDispatchedTimes(IncidenciaResguardoPdvRegistrada::class, 1);
    }

    public function test_version_obsoleta_rechazada(): void
    {
        $resguardo = $this->crearResguardoPendiente();

        $this->actingAs($this->operador)->postJson(
            route('punto_venta.resguardos.incidencias.store', $resguardo),
            [
                'version' => 99,
                'idempotency_key' => 'pdv:inc:'.$resguardo->id.':version',
                'tipo' => ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO,
                'descripcion' => 'Folio no encontrado',
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);

        $this->assertSame(0, ResguardoPdvIncidencia::query()->count());
    }

    private function crearResguardoPendiente(int $cantidadEsperada = 1): ResguardoPdv
    {
        return ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'cantidad_bultos_esperada' => $cantidadEsperada,
            'version' => 1,
        ]);
    }

    private function crearResguardoEnCustodia(): ResguardoPdv
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->almacen->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'cantidad_bultos_esperada' => 1,
            'recepcion_fisica_at' => now()->subHour(),
            'version' => 1,
        ]);

        ResguardoPdvBulto::query()->create([
            'resguardo_id' => $resguardo->id,
            'folio' => 'CJA-'.$resguardo->id,
            'tipo' => ResguardoPdvBulto::TIPO_CAJA,
            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
            'recepcion_at' => now()->subHour(),
            'recepcion_por_id' => $this->operador->id,
            'version' => 1,
        ]);

        return $resguardo;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadEntrega(ResguardoPdv $resguardo, ?int $version = null): array
    {
        return [
            'version' => $version ?? (int) $resguardo->version,
            'idempotency_key' => 'pdv:ent:'.$resguardo->id.':post-incidencia',
            'relacion' => \App\Models\PuntoVenta\ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Persona titular',
            'metodo_validacion' => RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
            'firma' => UploadedFile::fake()->image('firma.png'),
        ];
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => true, 'tipo' => 'boolean']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }
}
