<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\CancelacionPedidoResguardoPdvRecibida;
use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\PuntoVenta\AlertaResguardoPdvNotification;
use App\Services\ControlPedidos\CancelarPedidoBmaService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RecibirCancelacionPedidoResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CancelacionPedidoResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    private User $gerente;

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
            'codigo' => 'PISO-CAN',
            'nombre' => 'Piso cancelación',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->operador = User::factory()->create();
        $this->operador->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
        ]);
        $this->operador->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->gerente = User::factory()->create();
        $this->gerente->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION,
        ]);
        $this->gerente->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->seedCatalogosMinimos();
    }

    public function test_cancelacion_en_custodia_bloquea_entrega_y_registra_evento(): void
    {
        Event::fake([CancelacionPedidoResguardoPdvRecibida::class]);

        $pedido = $this->crearPedidoEnviado();
        $resguardo = $this->crearResguardoEnCustodia($pedido);

        $afectados = app(RecibirCancelacionPedidoResguardoPdvService::class)->ejecutar(
            $pedido,
            $this->gerente->id,
            'Cliente desiste'
        );

        $this->assertCount(1, $afectados);
        $resguardo->refresh();
        $this->assertTrue($resguardo->entrega_bloqueada);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->estado);
        $this->assertSame(2, (int) $resguardo->version);

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertSame(ResguardoPdvEvento::TIPO_CANCELACION_RECIBIDA, $evento->tipo_evento);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $evento->estado_anterior);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $evento->estado_nuevo);
        $this->assertSame(
            RecibirCancelacionPedidoResguardoPdvService::claveIdempotencia((int) $pedido->id, (int) $resguardo->id),
            $evento->idempotency_key
        );

        Event::assertDispatched(CancelacionPedidoResguardoPdvRecibida::class);

        $this->actingAs($this->operador)->putJson(
            route('punto_venta.resguardos.entrega', $resguardo),
            $this->payloadEntrega($resguardo)
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);
    }

    public function test_devolucion_sigue_disponible_tras_cancelacion(): void
    {
        Event::fake([CancelacionPedidoResguardoPdvRecibida::class]);

        $pedido = $this->crearPedidoEnviado();
        $resguardo = $this->crearResguardoEnCustodia($pedido);

        app(RecibirCancelacionPedidoResguardoPdvService::class)->ejecutar($pedido, $this->gerente->id);

        $this->actingAs($this->gerente)->putJson(
            route('punto_venta.resguardos.devolucion', $resguardo->fresh()),
            [
                'version' => (int) $resguardo->fresh()->version,
                'idempotency_key' => 'pdv:dev:'.$resguardo->id.':cancelacion',
                'motivo' => 'Pedido cancelado; mercancía sale a CEDIS',
            ]
        )->assertOk()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_DEVUELTO);
    }

    public function test_reintento_idempotente_no_duplica_efectos(): void
    {
        Event::fake([CancelacionPedidoResguardoPdvRecibida::class]);

        $pedido = $this->crearPedidoEnviado();
        $resguardo = $this->crearResguardoEnCustodia($pedido);
        $servicio = app(RecibirCancelacionPedidoResguardoPdvService::class);

        $servicio->ejecutar($pedido, $this->gerente->id, 'Cliente desiste');
        $version = (int) $resguardo->fresh()->version;

        $servicio->ejecutar($pedido, $this->gerente->id, 'Cliente desiste');

        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        $this->assertSame($version, (int) $resguardo->fresh()->version);
        Event::assertDispatchedTimes(CancelacionPedidoResguardoPdvRecibida::class, 1);
    }

    public function test_cancelacion_en_pendiente_recepcion_bloquea_entrega_sin_cerrar_custodia(): void
    {
        Event::fake([CancelacionPedidoResguardoPdvRecibida::class]);

        $pedido = $this->crearPedidoEnviado();
        $resguardo = ResguardoPdv::factory()->create([
            'pedido_bma_id' => $pedido->id,
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->almacen->id,
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'cantidad_bultos_esperada' => 1,
            'entrega_bloqueada' => false,
            'version' => 1,
        ]);

        app(RecibirCancelacionPedidoResguardoPdvService::class)->ejecutar($pedido, $this->gerente->id);

        $resguardo->refresh();
        $this->assertTrue($resguardo->entrega_bloqueada);
        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->estado);
        $this->assertSame(
            ResguardoPdvEvento::TIPO_CANCELACION_RECIBIDA,
            ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->value('tipo_evento')
        );
    }

    public function test_cancelar_pedido_bma_notifica_pdv(): void
    {
        Event::fake([CancelacionPedidoResguardoPdvRecibida::class]);

        $pedido = $this->crearPedidoBorrador();
        $resguardo = $this->crearResguardoEnCustodia($pedido);

        app(CancelarPedidoBmaService::class)->ejecutar($pedido, $this->gerente->id, [
            'motivo' => 'cliente_desiste',
        ]);

        $resguardo->refresh();
        $this->assertTrue($resguardo->entrega_bloqueada);
        $this->assertNotNull($pedido->fresh()->cancelado_at);
        $this->assertSame(1, ResguardoPdvEvento::query()
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_CANCELACION_RECIBIDA)
            ->count());
    }

    public function test_reintento_de_cancelacion_cp_no_duplica_evento_pdv(): void
    {
        Event::fake([CancelacionPedidoResguardoPdvRecibida::class]);

        $pedido = $this->crearPedidoBorrador();
        $resguardo = $this->crearResguardoEnCustodia($pedido);
        $servicio = app(CancelarPedidoBmaService::class);

        $servicio->ejecutar($pedido, $this->gerente->id, ['motivo' => 'cliente_desiste']);
        $servicio->ejecutar($pedido->fresh(), $this->gerente->id, ['motivo' => 'cliente_desiste']);

        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        $this->assertTrue($resguardo->fresh()->entrega_bloqueada);
        Event::assertDispatchedTimes(CancelacionPedidoResguardoPdvRecibida::class, 1);
    }

    public function test_sin_resguardo_no_crea_registros_pdv(): void
    {
        Event::fake([CancelacionPedidoResguardoPdvRecibida::class]);

        $pedido = $this->crearPedidoEnviado();

        $afectados = app(RecibirCancelacionPedidoResguardoPdvService::class)->ejecutar($pedido);

        $this->assertCount(0, $afectados);
        $this->assertSame(0, ResguardoPdv::query()->count());
        Event::assertNotDispatched(CancelacionPedidoResguardoPdvRecibida::class);
    }

    public function test_notifica_gerencia_de_sucursal_sin_duplicar(): void
    {
        config([
            'queue.default' => 'sync',
            'broadcasting.default' => 'log',
        ]);
        Notification::fake();

        $pedido = $this->crearPedidoEnviado();
        $resguardo = $this->crearResguardoEnCustodia($pedido);

        $servicio = app(RecibirCancelacionPedidoResguardoPdvService::class);
        $servicio->ejecutar($pedido, $this->gerente->id);
        $servicio->ejecutar($pedido, $this->gerente->id);

        Notification::assertSentTo(
            $this->gerente,
            AlertaResguardoPdvNotification::class,
            fn (AlertaResguardoPdvNotification $n) => $n->tipoAlerta === AlertaResguardoPdvNotification::TIPO_CANCELACION
        );
        Notification::assertNotSentTo($this->operador, AlertaResguardoPdvNotification::class);
    }

    public function test_detalle_expone_cancelacion_recibida(): void
    {
        Event::fake([CancelacionPedidoResguardoPdvRecibida::class]);

        $pedido = $this->crearPedidoEnviado();
        $resguardo = $this->crearResguardoEnCustodia($pedido);
        app(RecibirCancelacionPedidoResguardoPdvService::class)->ejecutar($pedido, $this->gerente->id);

        $this->actingAs($this->operador)
            ->get(route('punto_venta.resguardos.show', $resguardo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('resguardo.entrega_bloqueada', true)
                ->where('resguardo.cancelacion_recibida', true)
                ->where('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA));
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadEntrega(ResguardoPdv $resguardo): array
    {
        return [
            'version' => (int) $resguardo->version,
            'idempotency_key' => 'pdv:ent:'.$resguardo->id.':cancel',
            'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Persona titular',
            'metodo_validacion' => RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
            'firma' => UploadedFile::fake()->image('firma.png'),
        ];
    }

    private function crearResguardoEnCustodia(PedidoBma $pedido): ResguardoPdv
    {
        $resguardo = ResguardoPdv::factory()->create([
            'pedido_bma_id' => $pedido->id,
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
            'pedido_bma_id' => $pedido->id,
            'folio' => 'CJA-'.$resguardo->id,
            'tipo' => ResguardoPdvBulto::TIPO_CAJA,
            'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
            'recepcion_at' => now()->subHour(),
            'recepcion_por_id' => $this->operador->id,
            'version' => 1,
        ]);

        return $resguardo;
    }

    private function crearPedidoEnviado(): PedidoBma
    {
        return $this->crearPedido(CatalogoEstatusPedido::FASE_ENVIADO);
    }

    private function crearPedidoBorrador(): PedidoBma
    {
        return $this->crearPedido(CatalogoEstatusPedido::FASE_BORRADOR);
    }

    private function crearPedido(string $fase): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::porFase($fase);

        return PedidoBma::query()->create([
            'folio' => 'PED-CAN-'.uniqid(),
            'folio_remision' => 'REM-CAN-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->operador->id,
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
            'catalogo_estatus_pedido_id' => $estatus->id,
            'sucursal_destino_id' => $this->sucursal->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->operador->id,
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
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
                ['codigo_interno' => 'ENTREGADO', 'nombre_visual' => 'Entregado', 'color_hex' => '#10B981', 'fase_ciclo' => 'ENTREGADO', 'orden' => 8],
                ['codigo_interno' => 'CANCELADO', 'nombre_visual' => 'Cancelado', 'color_hex' => '#EF4444', 'fase_ciclo' => 'CANCELADO', 'orden' => 10],
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
                'vendedor_id' => $this->operador->id,
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
