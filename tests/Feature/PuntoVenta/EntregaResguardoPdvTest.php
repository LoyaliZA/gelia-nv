<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\EntregaResguardoPdvCompletada;
use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\InformarEntregaResguardoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EntregaResguardoPdvTest extends TestCase
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
            'codigo' => 'PISO-ENT',
            'nombre' => 'Piso entrega',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->usuario = User::factory()->create();
        $this->seedCatalogosMinimos();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        Storage::fake('local');
    }

    public function test_entrega_titular_transiciona_a_entregado_con_firma_y_evidencia(): void
    {
        Event::fake([EntregaResguardoPdvCompletada::class]);

        $pedido = $this->crearPedidoEnviado();
        $resguardo = $this->crearResguardoEnCustodia($pedido);
        $clave = 'pdv:ent:'.$resguardo->id.':terminal-1';

        $response = $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => $clave,
                'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
                'nombre_quien_retira' => 'Persona titular',
                'metodo_validacion' => RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
                'observaciones' => 'Entrega sin novedad',
                'firma' => UploadedFile::fake()->image('firma.png'),
                'evidencias' => [
                    UploadedFile::fake()->image('entrega.jpg'),
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_ENTREGADO)
            ->assertJsonPath('resguardo.version', 2)
            ->assertJsonPath('entrega.relacion', ResguardoPdvEntrega::RELACION_TITULAR);

        $resguardo->refresh();
        $this->assertNotNull($resguardo->entrega_completada_at);
        $this->assertSame(1, ResguardoPdvEntrega::query()->where('resguardo_id', $resguardo->id)->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->count());
        $this->assertSame(2, ResguardoPdvEvidencia::query()->where('resguardo_id', $resguardo->id)->count());

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertSame(ResguardoPdvEvento::TIPO_ENTREGA_TITULAR, $evento->tipo_evento);
        $this->assertSame('evt:'.$clave, $evento->idempotency_key);

        $bulto = ResguardoPdvBulto::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertSame(ResguardoPdvBulto::ESTADO_ENTREGADO, $bulto->estado);
        $this->assertNotNull($bulto->entrega_at);

        Event::assertDispatched(EntregaResguardoPdvCompletada::class);
    }

    public function test_entrega_tercero_registra_relacion_y_evento(): void
    {
        Event::fake([EntregaResguardoPdvCompletada::class]);

        $resguardo = $this->crearResguardoEnCustodia();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            $this->payloadEntrega($resguardo, relacion: ResguardoPdvEntrega::RELACION_TERCERO, nombre: 'Persona tercera')
        )->assertOk()
            ->assertJsonPath('entrega.relacion', ResguardoPdvEntrega::RELACION_TERCERO);

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertSame(ResguardoPdvEvento::TIPO_ENTREGA_TERCERO, $evento->tipo_evento);
        $this->assertSame('tercero', $evento->snapshot_json['receptor']['relacion'] ?? null);
    }

    public function test_validaciones_obligatorias_permiso_y_estado_invalido(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            []
        )->assertUnprocessable()
            ->assertJsonValidationErrors([
                'version',
                'idempotency_key',
                'relacion',
                'nombre_quien_retira',
                'metodo_validacion',
                'firma',
            ]);

        $sinEntregar = User::factory()->create();
        $sinEntregar->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $sinEntregar->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->actingAs($sinEntregar)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            $this->payloadEntrega($resguardo)
        )->assertForbidden();

        $pendiente = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'version' => 1,
        ]);

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $pendiente),
            $this->payloadEntrega($pendiente)
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);
    }

    public function test_entrega_bloqueada_por_incidencia_abierta(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();

        ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_DANO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Caja dañada',
            'reportado_por_id' => $this->usuario->id,
            'reportado_at' => now(),
            'idempotency_key' => 'inc-bloqueo-1',
            'version' => 1,
        ]);

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            $this->payloadEntrega($resguardo)
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);
    }

    public function test_dos_terminales_solo_una_entrega_efectiva(): void
    {
        Event::fake([EntregaResguardoPdvCompletada::class]);

        $resguardo = $this->crearResguardoEnCustodia();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            $this->payloadEntrega($resguardo, clave: 'pdv:ent:'.$resguardo->id.':a')
        )->assertOk();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo->fresh()),
            $this->payloadEntrega($resguardo->fresh(), clave: 'pdv:ent:'.$resguardo->id.':b')
        )->assertStatus(409);

        $this->assertSame(1, ResguardoPdvEntrega::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        Event::assertDispatchedTimes(EntregaResguardoPdvCompletada::class, 1);
    }

    public function test_reintento_idempotente_devuelve_mismo_resultado_sin_duplicar(): void
    {
        Event::fake([EntregaResguardoPdvCompletada::class]);

        $resguardo = $this->crearResguardoEnCustodia();
        $payload = $this->payloadEntrega($resguardo);

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            $payload
        )->assertOk();

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo->fresh()),
            $payload
        )->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_ENTREGADO);

        $this->assertSame(1, ResguardoPdvEntrega::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        Event::assertDispatchedTimes(EntregaResguardoPdvCompletada::class, 1);
    }

    public function test_version_obsoleta_rechazada(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();
        $payload = $this->payloadEntrega($resguardo);
        $payload['version'] = 99;

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            $payload
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);

        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
        $this->assertSame(0, ResguardoPdvEntrega::query()->count());
    }

    public function test_integracion_idempotente_con_control_de_pedidos(): void
    {
        $pedido = $this->crearPedidoEnviado();
        $resguardo = $this->crearResguardoEnCustodia($pedido);

        $this->actingAs($this->usuario)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            $this->payloadEntrega($resguardo)
        )->assertOk();

        $entrega = ResguardoPdvEntrega::query()->where('resguardo_id', $resguardo->id)->firstOrFail();

        // ShouldDispatchAfterCommit no corre dentro de RefreshDatabase; el listener invoca este servicio en producción.
        app(InformarEntregaResguardoPdvService::class)->ejecutar(
            $resguardo->fresh(),
            $entrega->fresh(),
            $this->usuario->id
        );

        $pedido->refresh()->load('estatus');
        $this->assertSame(
            CatalogoEstatusPedido::FASE_ENTREGADO,
            $pedido->estatus?->fase_ciclo
        );

        $this->assertSame('completada', $entrega->fresh()->snapshot_json['integracion_cp']['estado'] ?? null);

        $this->assertTrue(
            PedidoBmaHistorialEstado::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('accion', AccionesHistorialPedidoBma::ENTREGA_PDV)
                ->exists()
        );

        app(InformarEntregaResguardoPdvService::class)->ejecutar(
            $resguardo->fresh(),
            $entrega->fresh(),
            $this->usuario->id
        );

        $this->assertSame(
            1,
            PedidoBmaHistorialEstado::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('accion', AccionesHistorialPedidoBma::ENTREGA_PDV)
                ->count()
        );
    }

    public function test_fallo_integracion_no_revierte_entrega_confirmada(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();
        $entrega = ResguardoPdvEntrega::query()->create([
            'resguardo_id' => $resguardo->id,
            'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Titular',
            'entregado_por_id' => $this->usuario->id,
            'entregado_at' => now(),
            'snapshot_json' => ['integracion_cp' => ['estado' => 'pendiente']],
            'idempotency_key' => 'ent-manual-1',
            'version' => 1,
        ]);

        $resguardo->update(['estado' => ResguardoPdv::ESTADO_ENTREGADO]);

        $resultado = app(InformarEntregaResguardoPdvService::class)->ejecutar(
            $resguardo->fresh(),
            $entrega->fresh(),
            $this->usuario->id
        );

        $this->assertFalse($resultado);
        $this->assertSame(ResguardoPdv::ESTADO_ENTREGADO, $resguardo->fresh()->estado);
        $this->assertSame('pendiente', $entrega->fresh()->snapshot_json['integracion_cp']['estado'] ?? null);
        $this->assertNotNull($entrega->fresh()->snapshot_json['integracion_cp']['ultimo_error'] ?? null);
    }

    public function test_transaccion_fallida_no_deja_entrega_eventos_ni_archivos(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();
        $firma = UploadedFile::fake()->image('rollback.png');

        $publicado = false;
        Event::listen(EntregaResguardoPdvCompletada::class, function () use (&$publicado) {
            $publicado = true;
        });

        ResguardoPdvEvidencia::creating(function () {
            throw new \RuntimeException('fallo forzado evidencia');
        });

        try {
            app(RegistrarEntregaResguardoPdvService::class)->ejecutar(
                $resguardo,
                $this->usuario,
                1,
                'pdv:ent:'.$resguardo->id.':rollback',
                ResguardoPdvEntrega::RELACION_TITULAR,
                'Titular rollback',
                RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
                $firma
            );
            $this->fail('Debía revertir la transacción');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo forzado evidencia', $e->getMessage());
        } finally {
            ResguardoPdvEvidencia::flushEventListeners();
        }

        $this->assertSame(0, ResguardoPdvEntrega::query()->count());
        $this->assertSame(0, ResguardoPdvEvento::query()->count());
        $this->assertSame(0, ResguardoPdvEvidencia::query()->count());
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
        $this->assertFalse($publicado);
        Storage::disk('local')->assertDirectoryEmpty('pdv/resguardos/'.$resguardo->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadEntrega(
        ResguardoPdv $resguardo,
        ?string $clave = null,
        string $relacion = ResguardoPdvEntrega::RELACION_TITULAR,
        string $nombre = 'Persona titular',
    ): array {
        return [
            'version' => (int) $resguardo->version,
            'idempotency_key' => $clave ?? 'pdv:ent:'.$resguardo->id.':default',
            'relacion' => $relacion,
            'nombre_quien_retira' => $nombre,
            'metodo_validacion' => RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
            'firma' => UploadedFile::fake()->image('firma.png'),
        ];
    }

    private function crearResguardoEnCustodia(?PedidoBma $pedido = null): ResguardoPdv
    {
        $resguardo = ResguardoPdv::factory()->create([
            'pedido_bma_id' => $pedido?->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->almacen->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'cantidad_bultos_esperada' => 1,
            'recepcion_fisica_at' => now()->subHour(),
            'entrega_bloqueada' => false,
            'version' => 1,
        ]);

        ResguardoPdvBulto::query()->create([
            'resguardo_id' => $resguardo->id,
            'pedido_bma_id' => $pedido?->id,
            'folio' => 'CJA-'.$resguardo->id,
            'tipo' => ResguardoPdvBulto::TIPO_CAJA,
            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
            'recepcion_at' => now()->subHour(),
            'recepcion_por_id' => $this->usuario->id,
            'version' => 1,
        ]);

        return $resguardo;
    }

    private function crearPedidoEnviado(): PedidoBma
    {
        $enviado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENVIADO);

        return PedidoBma::query()->create([
            'folio' => 'PED-ENT-'.uniqid(),
            'folio_remision' => 'REM-ENT-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenMostrador()->id,
            'almacen_id' => $this->almacen->id,
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 1.5,
            'catalogo_paqueteria_id' => DB::table('catalogo_paqueterias_pedido')->value('id'),
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'catalogo_estatus_pedido_id' => $enviado->id,
            'sucursal_destino_id' => $this->sucursal->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ]);
    }

    private function origenMostrador(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::query()->firstOrCreate(
            ['nombre' => 'Mostrador'],
            ['requiere_logistica' => false, 'activo' => true]
        );
    }

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::query()->exists()) {
            foreach ([
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
                ['codigo_interno' => 'ENTREGADO', 'nombre_visual' => 'Entregado', 'color_hex' => '#10B981', 'fase_ciclo' => 'ENTREGADO', 'orden' => 8],
            ] as $row) {
                CatalogoEstatusPedido::query()->create(array_merge($row, ['activo' => true]));
            }
        }

        $this->origenMostrador();

        if (! DB::table('catalogo_bancos')->exists()) {
            DB::table('catalogo_bancos')->insert([
                'nombre' => 'BBVA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_paqueterias_pedido')->exists()) {
            DB::table('catalogo_paqueterias_pedido')->insert([
                'nombre' => 'TAXI FRONTERA',
                'categoria' => 'local_regional',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_tipos_caja_pedido')->exists()) {
            DB::table('catalogo_tipos_caja_pedido')->insert([
                'nombre' => 'CAJA TEST',
                'peso_volumetrico' => 1,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_tipos_guia_pedido')->exists()) {
            DB::table('catalogo_tipos_guia_pedido')->insert([
                'nombre' => 'Terrestre', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_zonas_pedido')->exists()) {
            DB::table('catalogo_zonas_pedido')->insert([
                'nombre' => 'Sin reexpedición', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente Test',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'vendedor_id' => $this->usuario->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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
