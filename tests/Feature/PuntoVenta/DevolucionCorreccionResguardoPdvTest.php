<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\CorreccionResguardoPdvAplicada;
use App\Events\PuntoVenta\DevolucionResguardoPdvConfirmada;
use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\InformarDevolucionResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\ConfirmarDevolucionResguardoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\PuntoVenta\Resguardos\CorreccionResguardoPdv;
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

class DevolucionCorreccionResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $operador;

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
            'codigo' => 'PISO-DEV',
            'nombre' => 'Piso devolución',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->gerente = User::factory()->create();
        $this->gerente->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR,
        ]);
        $this->gerente->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->operador = User::factory()->create();
        $this->operador->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $this->operador->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->seedCatalogosMinimos();
    }

    public function test_devolucion_valida_transiciona_a_devuelto_con_evento_y_evidencia(): void
    {
        Event::fake([DevolucionResguardoPdvConfirmada::class]);

        $pedido = $this->crearPedidoEnviado();
        $resguardo = $this->crearResguardoEnCustodia($pedido);
        $clave = 'pdv:dev:'.$resguardo->id.':terminal-1';

        $response = $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.devolucion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => $clave,
                'motivo' => 'Cancelación del pedido; mercancía sale a CEDIS',
                'evidencias' => [
                    UploadedFile::fake()->image('salida.jpg'),
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_DEVUELTO)
            ->assertJsonPath('resguardo.version', 2);

        $resguardo->refresh();
        $this->assertNotNull($resguardo->devolucion_confirmada_at);

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertSame(ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA, $evento->tipo_evento);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $evento->estado_anterior);
        $this->assertSame(ResguardoPdv::ESTADO_DEVUELTO, $evento->estado_nuevo);
        $this->assertSame($clave, $evento->idempotency_key);
        $this->assertSame('Cancelación del pedido; mercancía sale a CEDIS', $evento->snapshot_json['motivo'] ?? null);

        $bulto = ResguardoPdvBulto::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertSame(ResguardoPdvBulto::ESTADO_DEVUELTO, $bulto->estado);
        $this->assertNotNull($bulto->devolucion_salida_at);
        $this->assertSame(1, ResguardoPdvEvidencia::query()->where('resguardo_id', $resguardo->id)->count());

        Event::assertDispatched(DevolucionResguardoPdvConfirmada::class);
    }

    public function test_devolucion_invalida_sin_bultos_en_custodia(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'cantidad_bultos_esperada' => 1,
            'version' => 1,
        ]);

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.devolucion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:dev:'.$resguardo->id.':sin-bultos',
                'motivo' => 'Intento sin bultos',
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['bultos']);

        $this->assertSame(0, ResguardoPdvEvento::query()->count());
    }

    public function test_devolucion_rechazada_sin_permiso(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();

        $this->actingAs($this->operador)->putJson(
            route('punto_venta.resguardos.devolucion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:dev:'.$resguardo->id.':denegado',
                'motivo' => 'Intento sin permiso',
            ]
        )->assertForbidden();
    }

    public function test_correccion_snapshot_autorizada_conserva_evento_original(): void
    {
        Event::fake([CorreccionResguardoPdvAplicada::class]);

        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'snapshot_folio' => 'FOLIO-ERRONEO',
            'snapshot_cliente_nombre' => 'Cliente anterior',
            'version' => 1,
        ]);

        $eventoOriginal = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'estado_anterior' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => now()->subHour(),
            'snapshot_json' => ['folio' => 'FOLIO-ERRONEO'],
            'idempotency_key' => 'evt:orig:'.$resguardo->id,
        ]);
        $actorOriginalId = $eventoOriginal->actor_id;
        $ocurridoOriginal = $eventoOriginal->ocurrido_at?->toIso8601String();

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.correccion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:corr:'.$resguardo->id.':snapshot',
                'tipo_correccion' => CorreccionResguardoPdv::TIPO_SNAPSHOT_RESGUARDO,
                'motivo' => 'Folio corregido tras validación con CEDIS',
                'snapshot_folio' => 'FOLIO-CORRECTO',
            ]
        )->assertOk()
            ->assertJsonPath('resguardo.snapshot_folio', 'FOLIO-CORRECTO')
            ->assertJsonPath('resguardo.version', 2);

        $eventoOriginal->refresh();
        $this->assertSame($actorOriginalId, $eventoOriginal->actor_id);
        $this->assertSame($ocurridoOriginal, $eventoOriginal->ocurrido_at?->toIso8601String());

        $correccion = ResguardoPdvEvento::query()
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_CORRECCION_ADMINISTRATIVA)
            ->first();
        $this->assertNotNull($correccion);
        $this->assertSame('FOLIO-ERRONEO', $correccion->snapshot_json['valores_anteriores']['snapshot_folio'] ?? null);
        $this->assertSame('FOLIO-CORRECTO', $correccion->snapshot_json['valores_nuevos']['snapshot_folio'] ?? null);

        Event::assertDispatched(CorreccionResguardoPdvAplicada::class);
    }

    public function test_correccion_no_autorizada_sin_permiso(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'snapshot_folio' => 'FOLIO-1',
            'version' => 1,
        ]);

        $this->actingAs($this->operador)->putJson(
            route('punto_venta.resguardos.correccion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:corr:'.$resguardo->id.':denegado',
                'tipo_correccion' => CorreccionResguardoPdv::TIPO_SNAPSHOT_RESGUARDO,
                'motivo' => 'Intento sin permiso',
                'snapshot_folio' => 'FOLIO-2',
            ]
        )->assertForbidden();
    }

    public function test_anotacion_compensatoria_sobre_evento_historico(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'version' => 1,
        ]);

        $eventoReferencia = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL,
            'estado_anterior' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => now()->subMinutes(30),
            'snapshot_json' => ['cantidad_recibida' => 1],
            'idempotency_key' => 'evt:ref:'.$resguardo->id,
        ]);

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.correccion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:corr:'.$resguardo->id.':anotacion',
                'tipo_correccion' => CorreccionResguardoPdv::TIPO_ANOTACION_EVENTO,
                'motivo' => 'Se aclara que la recepción parcial correspondía a un bulto adicional autorizado',
                'evento_referencia_id' => $eventoReferencia->id,
            ]
        )->assertOk();

        $this->assertSame(2, ResguardoPdvEvento::query()->count());

        $eventoReferencia->refresh();
        $this->assertSame(ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL, $eventoReferencia->tipo_evento);

        $anotacion = ResguardoPdvEvento::query()
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_CORRECCION_ADMINISTRATIVA)
            ->first();
        $this->assertSame($eventoReferencia->id, $anotacion->snapshot_json['evento_referencia_id'] ?? null);
    }

    public function test_reintento_idempotente_no_duplica_devolucion(): void
    {
        Event::fake([DevolucionResguardoPdvConfirmada::class]);

        $resguardo = $this->crearResguardoEnCustodia();
        $payload = [
            'version' => 1,
            'idempotency_key' => 'pdv:dev:'.$resguardo->id.':idempotente',
            'motivo' => 'Devolución por cancelación',
        ];

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.devolucion', $resguardo),
            $payload
        )->assertOk();

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.devolucion', $resguardo->fresh()),
            $payload
        )->assertOk();

        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        Event::assertDispatchedTimes(DevolucionResguardoPdvConfirmada::class, 1);
    }

    public function test_version_obsoleta_rechazada_en_devolucion(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.devolucion', $resguardo),
            [
                'version' => 99,
                'idempotency_key' => 'pdv:dev:'.$resguardo->id.':version',
                'motivo' => 'Versión incorrecta',
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);

        $this->assertSame(0, ResguardoPdvEvento::query()->count());
    }

    public function test_integracion_cp_idempotente_sin_cambiar_fase_ciclo(): void
    {
        $pedido = $this->crearPedidoEnviado();
        $resguardo = $this->crearResguardoEnCustodia($pedido);

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.devolucion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:dev:'.$resguardo->id.':cp',
                'motivo' => 'Devolución confirmada',
            ]
        )->assertOk();

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->first();
        $faseAntes = $pedido->fresh()->estatus?->fase_ciclo;

        app(InformarDevolucionResguardoPdvService::class)->ejecutar($resguardo->fresh(), $evento, $this->gerente->id);
        app(InformarDevolucionResguardoPdvService::class)->ejecutar($resguardo->fresh(), $evento->fresh(), $this->gerente->id);

        $this->assertSame($faseAntes, $pedido->fresh()->estatus?->fase_ciclo);
        $this->assertSame('completada', $evento->fresh()->snapshot_json['integracion_cp']['estado'] ?? null);
        $this->assertSame(
            1,
            PedidoBmaHistorialEstado::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('accion', AccionesHistorialPedidoBma::DEVOLUCION_PDV)
                ->count()
        );
    }

    public function test_transaccion_fallida_no_deja_devolucion_ni_eventos(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();

        $publicado = false;
        Event::listen(DevolucionResguardoPdvConfirmada::class, function () use (&$publicado) {
            $publicado = true;
        });

        ResguardoPdvEvidencia::creating(function () {
            throw new \RuntimeException('fallo forzado evidencia');
        });

        try {
            app(ConfirmarDevolucionResguardoPdvService::class)->ejecutar(
                $resguardo,
                $this->gerente,
                1,
                'pdv:dev:'.$resguardo->id.':rollback',
                'Devolución con evidencia',
                [UploadedFile::fake()->image('fallo.jpg')],
            );
            $this->fail('Debía revertir la transacción');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo forzado evidencia', $e->getMessage());
        } finally {
            ResguardoPdvEvidencia::flushEventListeners();
        }

        $this->assertFalse($publicado);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
        $this->assertSame(0, ResguardoPdvEvento::query()->count());
        $this->assertSame(0, ResguardoPdvEvidencia::query()->count());
    }

    public function test_aislamiento_por_sucursal_en_devolucion(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->otraSucursal->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'cantidad_bultos_esperada' => 1,
            'version' => 1,
        ]);

        ResguardoPdvBulto::query()->create([
            'resguardo_id' => $resguardo->id,
            'folio' => 'CJA-OTRA',
            'tipo' => ResguardoPdvBulto::TIPO_CAJA,
            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
            'recepcion_at' => now(),
            'recepcion_por_id' => $this->gerente->id,
            'version' => 1,
        ]);

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.devolucion', $resguardo),
            [
                'version' => 1,
                'idempotency_key' => 'pdv:dev:'.$resguardo->id.':otra-sucursal',
                'motivo' => 'Intento fuera de alcance',
            ]
        )->assertForbidden();
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
            'entrega_bloqueada' => true,
            'version' => 1,
        ]);

        ResguardoPdvBulto::query()->create([
            'resguardo_id' => $resguardo->id,
            'pedido_bma_id' => $pedido?->id,
            'folio' => 'CJA-'.$resguardo->id,
            'tipo' => ResguardoPdvBulto::TIPO_CAJA,
            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
            'recepcion_at' => now()->subHour(),
            'recepcion_por_id' => $this->gerente->id,
            'version' => 1,
        ]);

        return $resguardo;
    }

    private function crearPedidoEnviado(): PedidoBma
    {
        $enviado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENVIADO);

        return PedidoBma::query()->create([
            'folio' => 'PED-DEV-'.uniqid(),
            'folio_remision' => 'REM-DEV-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->gerente->id,
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
            'pago_validado_por_id' => $this->gerente->id,
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
                'vendedor_id' => $this->gerente->id,
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
