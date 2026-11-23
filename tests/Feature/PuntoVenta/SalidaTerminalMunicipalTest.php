<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\EntregaResguardoPdvCompletada;
use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\Conversion\EvaluarElegibilidadConversion;
use App\Services\ControlPedidos\FusionarSolicitudPreparacionPedido;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Notifications\AlertaPedidoBma;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use App\Support\PuntoVenta\Resguardos\BultosEsperadosResguardoPdv;
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

class SalidaTerminalMunicipalTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISO = 'punto_venta.resguardos.salida_terminal';

    private User $usuario;

    private User $gerencia;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    private Almacen $cedis;

    private Almacen $piso;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $this->withHeader('Accept', 'application/json');
        Storage::fake('local');
        ConfiguracionSistema::query()->updateOrCreate(['clave' => PuntoVentaModulo::CLAVE_FLAG], ['valor' => '1']);
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        Permission::findOrCreate(self::PERMISO, 'web');

        $this->usuario = User::factory()->create();
        $this->sucursal = Sucursal::factory()->create(['activo' => true, 'nombre' => 'Sucursal Norte']);
        $this->otraSucursal = Sucursal::factory()->create(['activo' => true, 'nombre' => 'Sucursal Sur']);
        $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'CEDIS'], ['activo' => true]);
        $this->cedis = Almacen::query()->create([
            'nombre' => 'CEDIS-REM', 'codigo' => 'CEDIS-REM', 'tipo_almacen_id' => $tipo->id,
            'activo' => true, 'visible_en_pedidos' => true,
        ]);
        $this->piso = Almacen::query()->create([
            'codigo' => 'PISO-1', 'nombre' => 'Piso recepción', 'sucursal_id' => $this->sucursal->id, 'activo' => true,
        ]);
        $this->seedCatalogos();

        $this->gerencia = $this->usuarioPdv($this->sucursal);
    }

    public function test_permiso_existe_por_migracion_y_en_catalogo(): void
    {
        $this->assertContains(self::PERMISO, require base_path('database/seeders/data/permisos_actuales.php'));
        $this->assertSame(self::PERMISO, PuntoVentaModulo::PERMISO_RESGUARDOS_SALIDA_TERMINAL);
        $this->assertTrue(Permission::query()->where('name', self::PERMISO)->exists());
    }

    public function test_gerencia_registra_salida_con_receptor(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $pedido = $resguardo->pedido()->first();
        $estatusAntes = (int) $pedido->catalogo_estatus_pedido_id;

        $this->actingAs($this->gerencia)
            ->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
                'version' => (int) $resguardo->version,
                'idempotency_key' => 'term:1',
                'receptor' => '  Juan Pérez  ',
            ])
            ->assertOk()
            ->assertJsonPath('resguardo.id', $resguardo->id)
            ->assertJsonPath('resguardo.estado_etiqueta', 'Salida a terminal')
            ->assertJsonPath('resguardo.salida_terminal.receptor', 'Juan Pérez');

        $resguardo->refresh();
        $this->assertSame(ResguardoPdv::ESTADO_ENTREGADO, $resguardo->estado);
        $this->assertSame('Juan Pérez', $resguardo->snapshot_json['salida_terminal']['receptor']);
        $this->assertSame(0, ResguardoPdvBulto::query()->where('resguardo_id', $resguardo->id)
            ->where('estado', ResguardoPdvBulto::ESTADO_EN_CUSTODIA)->count());

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_SALIDA_TERMINAL)->sole();
        $this->assertSame('Juan Pérez', $evento->snapshot_json['receptor']);
        $this->assertSame($this->gerencia->id, (int) $evento->actor_id);
        $this->assertSame($this->gerencia->id, (int) $evento->snapshot_json['actor_id']);
        $this->assertNotEmpty($evento->snapshot_json['at']);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $evento->estado_anterior);

        $pedido->refresh();
        $marca = $pedido->solicitud_preparacion_json['salida_terminal'];
        $this->assertSame('Juan Pérez', $marca['receptor']);
        $this->assertSame($this->gerencia->id, (int) $marca['actor_id']);
        $this->assertNotEmpty($marca['at']);
        $this->assertTrue($pedido->solicitud_preparacion_json['recorrido_fisico_municipal']);
        $this->assertSame($estatusAntes, (int) $pedido->catalogo_estatus_pedido_id);

        $historial = PedidoBmaHistorialEstado::query()->where('pedido_bma_id', $pedido->id)
            ->where('accion', 'salida_terminal')->sole();
        $this->assertSame($estatusAntes, (int) $historial->estatus_nuevo_id);
        $this->assertSame('Salida a terminal registrada', \App\Support\ControlPedidos\AccionesHistorialPedidoBma::etiqueta('salida_terminal'));

        $detalle = $this->getJson(route('punto_venta.resguardos.show', $resguardo))->assertOk();
        $detalle->assertJsonPath('resguardo.estado_etiqueta', 'Salida a terminal')
            ->assertJsonPath('resguardo.salida_terminal.receptor', 'Juan Pérez')
            ->assertJsonPath('resguardo.puede_salida_terminal', false)
            ->assertJsonPath('resguardo.ultima_entrega', null);
        $this->assertNotEmpty($detalle->json('resguardo.salida_terminal.at'));
        $timeline = collect($detalle->json('timeline'))->firstWhere('tipo_evento', ResguardoPdvEvento::TIPO_SALIDA_TERMINAL);
        $this->assertSame('Salida a terminal', $timeline['tipo_etiqueta']);
        $this->assertSame('Salida a terminal', $timeline['estado_nuevo_etiqueta']);

        $fila = collect($this->getJson(route('punto_venta.resguardos.entregados.listado'))->assertOk()->json('resguardos.data'))
            ->firstWhere('id', $resguardo->id);
        $this->assertNotNull($fila);
        $this->assertSame('Salida a terminal', $fila['estado_etiqueta']);
        $this->assertSame('Juan Pérez', $fila['salida_terminal']['receptor']);
        $this->assertNull($fila['ultima_entrega']);
    }

    public function test_detalle_municipal_resume_la_caratula_segun_permiso_y_estado(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $this->assertSame('municipal', $resguardo->snapshot_json['origen']);

        $sin = $this->getJson(route('punto_venta.resguardos.show', $resguardo))->assertOk();
        $sin->assertJsonPath('resguardo.es_municipal', true)
            ->assertJsonPath('resguardo.caratula.puede_generar', false)
            ->assertJsonPath('resguardo.caratula.puede_descargar', false);

        foreach (['generar_caratula', 'regenerar_caratula', 'imprimir_caratula'] as $permiso) {
            Permission::findOrCreate('control_pedidos.tienda.'.$permiso, 'web');
            $this->gerencia->givePermissionTo('control_pedidos.tienda.'.$permiso);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->gerencia = User::query()->findOrFail($this->gerencia->id);

        $ninguna = $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))->assertOk();
        $ninguna->assertJsonPath('resguardo.caratula.id', null)
            ->assertJsonPath('resguardo.caratula.puede_generar', true)
            ->assertJsonPath('resguardo.caratula.puede_regenerar', false)
            ->assertJsonPath('resguardo.caratula.puede_descargar', false);

        $caratula = \App\Models\ControlPedidos\PedidoBmaCaratula::query()->create([
            'pedido_bma_id' => $resguardo->pedido_bma_id, 'version' => 1, 'estado' => 'GENERADA', 'ruta_pdf' => 'x.pdf',
            'modalidad_cobro' => 'PAGADO', 'catalogo_paqueteria_id' => $resguardo->pedido()->value('catalogo_paqueteria_id'), 'destinatario_nombre' => 'Ana', 'destinatario_telefono' => '5550000000', 'municipio_destino' => 'Terminal', 'generada_por_id' => $this->gerencia->id, 'generada_at' => now(),
        ]);
        $con = $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))->assertOk();
        $con->assertJsonPath('resguardo.caratula.id', $caratula->id)
            ->assertJsonPath('resguardo.caratula.pedido_id', $resguardo->pedido_bma_id)
            ->assertJsonPath('resguardo.caratula.estado', 'GENERADA')
            ->assertJsonPath('resguardo.caratula.puede_generar', false)
            ->assertJsonPath('resguardo.caratula.puede_regenerar', true)
            ->assertJsonPath('resguardo.caratula.puede_descargar', true);

        $caratula->update(['estado' => 'COLOCADA']);
        $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))
            ->assertJsonPath('resguardo.caratula.puede_regenerar', false)
            ->assertJsonPath('resguardo.caratula.puede_descargar', true);
    }

    public function test_tras_la_salida_a_terminal_solo_queda_descargar_la_caratula(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        foreach (['generar_caratula', 'regenerar_caratula', 'imprimir_caratula'] as $permiso) {
            Permission::findOrCreate('control_pedidos.tienda.'.$permiso, 'web');
            $this->gerencia->givePermissionTo('control_pedidos.tienda.'.$permiso);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->gerencia = User::query()->findOrFail($this->gerencia->id);
        \App\Models\ControlPedidos\PedidoBmaCaratula::query()->create([
            'pedido_bma_id' => $resguardo->pedido_bma_id, 'version' => 1, 'estado' => 'GENERADA', 'ruta_pdf' => 'x.pdf',
            'modalidad_cobro' => 'PAGADO', 'catalogo_paqueteria_id' => $resguardo->pedido()->value('catalogo_paqueteria_id'), 'destinatario_nombre' => 'Ana', 'destinatario_telefono' => '5550000000', 'municipio_destino' => 'Terminal', 'generada_por_id' => $this->gerencia->id, 'generada_at' => now(),
        ]);
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:car', 'receptor' => 'Ana',
        ])->assertOk();

        $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))->assertOk()
            ->assertJsonPath('resguardo.caratula.puede_generar', false)
            ->assertJsonPath('resguardo.caratula.puede_regenerar', false)
            ->assertJsonPath('resguardo.caratula.puede_descargar', true);
    }

    public function test_detalle_no_municipal_conserva_su_forma(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $snapshot = $resguardo->snapshot_json;
        $snapshot['origen'] = 'tienda';
        $resguardo->forceFill(['snapshot_json' => $snapshot])->save();

        $json = $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))->assertOk()->json('resguardo');
        foreach (['es_municipal', 'caratula', 'puede_salida_terminal', 'salida_terminal'] as $clave) {
            $this->assertArrayNotHasKey($clave, $json);
        }
    }

    public function test_receptor_vacio_o_largo_rechazado(): void
    {
        $resguardo = $this->resguardoEnCustodia();

        foreach (['   ', str_repeat('a', 121)] as $i => $receptor) {
            $this->actingAs($this->gerencia)
                ->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
                    'version' => (int) $resguardo->version,
                    'idempotency_key' => 'term:vacio:'.$i,
                    'receptor' => $receptor,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('receptor');
        }

        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
        $this->assertArrayNotHasKey('salida_terminal', $resguardo->pedido()->first()->solicitud_preparacion_json);
    }

    public function test_doble_envio_con_misma_clave_no_duplica(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $payload = ['version' => (int) $resguardo->version, 'idempotency_key' => 'term:dup', 'receptor' => 'Ana'];

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), $payload)->assertOk();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), $payload)
            ->assertOk()
            ->assertJsonPath('resguardo.salida_terminal.receptor', 'Ana');

        $this->assertSame(1, ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_SALIDA_TERMINAL)->count());
        $this->assertSame(1, PedidoBmaHistorialEstado::query()->where('pedido_bma_id', $resguardo->pedido_bma_id)
            ->where('accion', 'salida_terminal')->count());
    }

    public function test_salida_avisa_una_sola_vez_a_la_vendedora(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $avisos = [];
        $this->mock(NotificarPedidoBmaService::class, function ($mock) use (&$avisos) {
            $mock->shouldReceive('ejecutar')->andReturnUsing(function (...$args) use (&$avisos) {
                $avisos[] = $args;
            });
        });
        $payload = ['version' => (int) $resguardo->version, 'idempotency_key' => 'term:aviso', 'receptor' => 'Ana Ruiz'];

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), $payload)->assertOk();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), $payload)->assertOk();

        $this->assertCount(1, $avisos);
        [$pedido, $tipo, $mensaje, $permisos, $excluir, $incluirVendedora] = $avisos[0];
        $this->assertSame((int) $resguardo->pedido_bma_id, (int) $pedido->id);
        $this->assertSame('pedido_salida_terminal', $tipo);
        $this->assertSame('Salida a terminal registrada. Recibe: Ana Ruiz.', $mensaje);
        $this->assertSame([], $permisos);
        $this->assertSame($this->gerencia->id, $excluir);
        $this->assertTrue($incluirVendedora);

        $alerta = new AlertaPedidoBma($pedido, $tipo, $mensaje);
        $this->assertSame('Salida a terminal registrada: '.$pedido->folio_remision, $alerta->titulo);
        $this->assertStringContainsString('Ana Ruiz', $alerta->mensajeVisible);
        $this->assertStringContainsString($pedido->folio_remision, $alerta->mensajeVisible);
    }

    public function test_remision_de_un_municipal_salido_a_terminal_dice_que_salio_de_custodia(): void
    {
        $borrador = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)->id;
        $envio = $this->pedido(['catalogo_estatus_pedido_id' => $borrador]);
        $mensaje = function (array $snapshot) use ($borrador, $envio): string {
            $origen = $this->pedido(['catalogo_estatus_pedido_id' => $borrador]);
            ResguardoPdv::factory()->create([
                'sucursal_id' => $this->sucursal->id, 'pedido_bma_id' => $origen->id,
                'estado' => ResguardoPdv::ESTADO_ENTREGADO, 'entrega_completada_at' => now(),
                'snapshot_json' => ['origen' => 'municipal'] + $snapshot,
            ]);
            try {
                \App\Support\ControlPedidos\ExclusividadRemisionEnvio::assertDisponible($origen->fresh(), $envio->fresh());
            } catch (\InvalidArgumentException $e) {
                return $e->getMessage();
            }
            $this->fail('Debía rechazar la remisión.');
        };

        $this->assertSame('Esa remisión ya salió de custodia.', $mensaje(['salida_terminal' => ['at' => now()->toIso8601String(), 'receptor' => 'Ana', 'actor_id' => 1]]));
        $this->assertSame('Esa remisión ya fue entregada.', $mensaje([]));
    }

    public function test_otra_clave_sobre_resguardo_ya_salido_es_conflicto(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:a', 'receptor' => 'Ana',
        ])->assertOk();

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->fresh()->version, 'idempotency_key' => 'term:b', 'receptor' => 'Luis',
        ])->assertStatus(409);

        $this->assertSame('Ana', $resguardo->fresh()->snapshot_json['salida_terminal']['receptor']);
        $this->assertSame(1, ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_SALIDA_TERMINAL)->count());
    }

    public function test_version_obsoleta_rechazada(): void
    {
        $resguardo = $this->resguardoEnCustodia();

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version + 5, 'idempotency_key' => 'term:ver', 'receptor' => 'Ana',
        ])->assertUnprocessable()->assertJsonValidationErrors('version');

        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
    }

    public function test_rechaza_resguardo_no_en_custodia(): void
    {
        $resguardo = $this->resguardoMunicipalPendiente();

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:pend', 'receptor' => 'Ana',
        ])->assertUnprocessable();

        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->fresh()->estado);
        $this->assertSame(0, ResguardoPdvEvento::query()->where('tipo_evento', ResguardoPdvEvento::TIPO_SALIDA_TERMINAL)->count());
    }

    public function test_rechaza_con_incidencia_abierta(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Etiqueta ilegible',
            'reportado_por_id' => $this->gerencia->id,
            'reportado_at' => now(),
        ]);

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->fresh()->version, 'idempotency_key' => 'term:inc', 'receptor' => 'Ana',
        ])->assertUnprocessable();

        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
    }

    public function test_rechaza_otra_sucursal(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $ajeno = $this->usuarioPdv($this->otraSucursal);

        $this->actingAs($ajeno)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:ajeno', 'receptor' => 'Ana',
        ])->assertForbidden();

        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
    }

    public function test_sin_permiso_403(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $sinPermiso = $this->usuarioPdv($this->sucursal, false);

        $this->actingAs($sinPermiso)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:sinperm', 'receptor' => 'Ana',
        ])->assertForbidden();

        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
    }

    public function test_rechaza_resguardo_sin_origen_municipal(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $snapshot = $resguardo->snapshot_json;
        $snapshot['origen'] = 'tienda';
        $resguardo->update(['snapshot_json' => $snapshot]);

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->fresh()->version, 'idempotency_key' => 'term:tienda', 'receptor' => 'Ana',
        ])->assertUnprocessable();

        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
    }

    public function test_rechaza_pedido_municipal_sin_marca_de_recorrido(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $pedido = $resguardo->pedido()->first();
        $json = $pedido->solicitud_preparacion_json;
        unset($json['recorrido_fisico_municipal']);
        $pedido->update(['solicitud_preparacion_json' => $json]);

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:legado', 'receptor' => 'Ana',
        ])->assertUnprocessable();

        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
    }

    public function test_rechaza_pedido_cancelado(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $resguardo->pedido()->first()->update(['cancelado_at' => now()]);

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:cancel', 'receptor' => 'Ana',
        ])->assertUnprocessable();

        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
    }

    public function test_tras_la_salida_no_se_entrega_al_cliente(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $this->gerencia->givePermissionTo(PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR);
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:ent', 'receptor' => 'Ana',
        ])->assertOk();

        $resguardo->refresh();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.entrega', $resguardo), [
            'version' => (int) $resguardo->version,
            'idempotency_key' => 'ent:tras-terminal',
            'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Cliente',
            'metodo_validacion' => 'firma',
            'firma' => UploadedFile::fake()->image('firma.png'),
        ])->assertStatus(409);

        $this->assertSame(0, ResguardoPdvEntrega::query()->where('resguardo_id', $resguardo->id)->count());
    }

    public function test_salida_no_marca_entregado_al_cliente_ni_cobra_flete(): void
    {
        Event::fake([EntregaResguardoPdvCompletada::class]);
        $resguardo = $this->resguardoEnCustodia();
        $pedido = $resguardo->pedido()->first();
        $antes = $pedido->only(['costo_envio', 'pago_validado_at', 'catalogo_estatus_pedido_id']);

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:flete', 'receptor' => 'Ana',
        ])->assertOk();

        $pedido->refresh()->load('estatus');
        $this->assertSame(CatalogoEstatusPedido::FASE_ENVIADO, $pedido->estatus->fase_ciclo);
        $this->assertNotSame(CatalogoEstatusPedido::FASE_ENTREGADO, $pedido->estatus->fase_ciclo);
        $this->assertEquals($antes, $pedido->only(['costo_envio', 'pago_validado_at', 'catalogo_estatus_pedido_id']));
        $this->assertArrayNotHasKey('finanzas_municipales', $pedido->solicitud_preparacion_json);
        $this->assertSame(0, ResguardoPdvEntrega::query()->count());
        Event::assertNotDispatched(EntregaResguardoPdvCompletada::class);
    }

    public function test_tras_la_salida_no_hay_cambio_ordinario_de_modalidad(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $pedido = $resguardo->pedido()->first();
        $vendedora = $this->usuario;
        $evaluar = app(EvaluarElegibilidadConversion::class);

        $sinMarca = $evaluar->paraDestino($pedido->fresh(), $vendedora, CodigosConversionModalidad::DESTINO_TIENDA);
        $this->assertFalse($sinMarca->permitido);
        $this->assertStringNotContainsString('terminal', (string) $sinMarca->motivo_bloqueo);

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->version, 'idempotency_key' => 'term:conv', 'receptor' => 'Ana',
        ])->assertOk();

        foreach ([
            CodigosConversionModalidad::DESTINO_TIENDA,
            CodigosConversionModalidad::DESTINO_NACIONAL,
            CodigosConversionModalidad::DESTINO_MUNICIPAL,
        ] as $destino) {
            $conMarca = $evaluar->paraDestino($pedido->fresh(), $vendedora, $destino);
            $this->assertFalse($conMarca->permitido);
            $this->assertSame(CodigosConversionModalidad::CODIGO_NO_ELEGIBLE, $conMarca->codigo);
            $this->assertStringContainsString('salió a terminal', (string) $conMarca->motivo_bloqueo);
        }
        $flags = $evaluar->flags($pedido->fresh(), $vendedora);
        $this->assertFalse($flags->puede_iniciar);

        // Sin la marca, el motivo es el mismo que antes de la salida (municipales en vuelo/legados).
        $pedido = $pedido->fresh();
        $json = $pedido->solicitud_preparacion_json;
        unset($json['salida_terminal']);
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $legado = $evaluar->paraDestino($pedido->fresh(), $vendedora, CodigosConversionModalidad::DESTINO_TIENDA);
        $this->assertSame($sinMarca->motivo_bloqueo, $legado->motivo_bloqueo);
        $this->assertSame($sinMarca->codigo, $legado->codigo);
    }

    public function test_fusion_de_solicitud_conserva_salida_terminal(): void
    {
        $marca = ['at' => now()->toIso8601String(), 'receptor' => 'Ana', 'actor_id' => 7];
        $fusion = app(FusionarSolicitudPreparacionPedido::class)->ejecutar(
            ['modalidad_entrega' => 'municipal', 'recorrido_fisico_municipal' => true, 'salida_terminal' => $marca],
            ['modalidad_entrega' => 'municipal'],
        );

        $this->assertSame($marca, $fusion['salida_terminal']);
    }

    public function test_detalle_municipal_en_custodia_expone_puede_salida_terminal(): void
    {
        $resguardo = $this->resguardoEnCustodia();

        $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))
            ->assertOk()
            ->assertJsonPath('resguardo.estado_etiqueta', 'En custodia')
            ->assertJsonPath('resguardo.puede_salida_terminal', true)
            ->assertJsonPath('resguardo.salida_terminal', null);

        $sinPermiso = $this->usuarioPdv($this->sucursal, false);
        $this->actingAs($sinPermiso)->getJson(route('punto_venta.resguardos.show', $resguardo))
            ->assertOk()
            ->assertJsonPath('resguardo.puede_salida_terminal', false);
    }

    public function test_detalle_de_resguardo_no_municipal_no_cambia(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $snapshot = $resguardo->snapshot_json;
        unset($snapshot['origen']);
        $resguardo->update(['snapshot_json' => $snapshot]);
        $resguardo->pedido()->first()->update(['solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda']]);

        $json = $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))
            ->assertOk()
            ->assertJsonPath('resguardo.estado_etiqueta', 'En custodia')
            ->json('resguardo');

        $this->assertArrayNotHasKey('puede_salida_terminal', $json);
        $this->assertArrayNotHasKey('salida_terminal', $json);
    }

    // ── M-03b: piezas adicionales (complemento municipal) ─────────────────────────────────────────────

    private function complemento(PedidoBma $principal, ?string $solicitudId = null): PedidoBma
    {
        Storage::fake('public');
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $principal->update(['numero_remision' => $principal->numero_remision ?: 'REM-PRIN-'.$principal->id]);

        return app(\App\Services\ControlPedidos\Municipal\RegistrarComplementoMunicipalService::class)->ejecutar($principal->fresh(), $this->usuario, [
            'solicitud_id' => $solicitudId ?? (string) \Illuminate\Support\Str::uuid(),
            'folio_remision' => 'WIZ-COMP-'.uniqid(),
            'total_mercancia' => 250,
            'piezas' => [['descripcion' => 'Pieza adicional', 'cantidad' => 2]],
            'evidencias' => [UploadedFile::fake()->create('piezas.pdf', 10, 'application/pdf')],
        ]);
    }

    private function pagarYRemisionar(PedidoBma $complemento): void
    {
        $complemento->update(['pago_validado_at' => now(), 'pago_validado_por_id' => $this->usuario->id, 'numero_remision' => 'REM-COMP-'.$complemento->id]);
    }

    public function test_complemento_hereda_destino_con_identidad_propia_y_sin_semantica_de_resguardo(): void
    {
        $principal = $this->pedido();
        $total = $principal->total_mercancia;
        $complemento = $this->complemento($principal);
        $principal->refresh();

        $this->assertSame((int) $principal->id, (int) $complemento->pedido_principal_id);
        $this->assertFalse((bool) $complemento->es_resguardo);
        $this->assertSame($principal->cliente_id, $complemento->cliente_id);
        $this->assertSame($principal->sucursal_destino_id, $complemento->sucursal_destino_id);
        $this->assertSame($principal->catalogo_paqueteria_id, $complemento->catalogo_paqueteria_id);
        $this->assertEquals(250, $complemento->total_mercancia);
        $this->assertEquals(250, $complemento->total_a_cobrar);
        $this->assertNull($complemento->pago_validado_at);
        $this->assertNull($complemento->numero_remision);
        $this->assertNotSame($principal->folio, $complemento->folio);
        $this->assertNotNull(\App\Support\ControlPedidos\Municipal\ComplementosMunicipales::marca($complemento));
        $this->assertSame('municipal', ($principal->solicitud_preparacion_json['modalidad_entrega'] ?? null));
        $this->assertNotSame('municipal', $complemento->solicitud_preparacion_json['modalidad_entrega'] ?? null);
        $this->assertEquals($total, $principal->total_mercancia, 'El principal conserva su importe');
        $this->assertSame($complemento->id, $principal->solicitud_preparacion_json['complementos_municipales'][0]['pedido_id']);
        $this->assertSame(1, $complemento->documentos()->count());
        $this->assertTrue(\App\Support\ControlPedidos\Municipal\ComplementosMunicipales::hayAbiertos($principal));
    }

    public function test_misma_solicitud_no_duplica_el_complemento(): void
    {
        $principal = $this->pedido();
        $clave = (string) \Illuminate\Support\Str::uuid();
        $uno = $this->complemento($principal, $clave);
        $dos = $this->complemento($principal->fresh(), $clave);

        $this->assertSame($uno->id, $dos->id);
        $this->assertSame(1, PedidoBma::query()->where('pedido_principal_id', $principal->id)->count());
    }

    public function test_complemento_rechaza_sin_remision_o_tras_salida_a_terminal(): void
    {
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $servicio = app(\App\Services\ControlPedidos\Municipal\RegistrarComplementoMunicipalService::class);
        $datos = fn () => [
            'solicitud_id' => (string) \Illuminate\Support\Str::uuid(), 'folio_remision' => 'WIZ-X', 'total_mercancia' => 50,
            'piezas' => [['descripcion' => 'Pieza', 'cantidad' => 1]],
            'evidencias' => [UploadedFile::fake()->create('p.pdf', 5, 'application/pdf')],
        ];
        Storage::fake('public');

        $sinRemision = $this->pedido(['folio_remision' => null]);
        try {
            $servicio->ejecutar($sinRemision, $this->usuario, $datos());
            $this->fail('Sin remisión se modifica el pedido original.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('anexo', $e->errors());
        }

        $salio = $this->pedido(['numero_remision' => 'REM-SALIO']);
        $salio->update(['solicitud_preparacion_json' => array_merge($salio->solicitud_preparacion_json, ['salida_terminal' => ['at' => now()->toIso8601String(), 'receptor' => 'Terminal ficticia']])]);
        try {
            $servicio->ejecutar($salio->fresh(), $this->usuario, $datos());
            $this->fail('Tras la salida a terminal las piezas van en otro envío.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(\App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal::MENSAJE_ANEXO_SALIO, $e->errors()['anexo'][0]);
        }
        $this->assertSame(0, PedidoBma::query()->whereNotNull('pedido_principal_id')->count());
    }

    public function test_complemento_abierto_impide_la_salida_a_terminal_hasta_integrarlo(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $principal = $resguardo->pedido()->first();
        $complemento = $this->complemento($principal);
        $enviar = fn () => $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->fresh()->version,
            'idempotency_key' => 'term:comp:'.uniqid(),
            'receptor' => 'Juan Pérez',
        ]);

        $enviar()->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertNull($principal->fresh()->solicitud_preparacion_json['salida_terminal'] ?? null);

        $this->pagarYRemisionar($complemento);
        app(\App\Services\ControlPedidos\Municipal\IntegrarComplementoMunicipalService::class)->ejecutar($principal->fresh(), $complemento->fresh(), $this->gerencia);

        $enviar()->assertOk();
    }

    public function test_integracion_en_pdv_la_confirma_la_sucursal_y_respeta_pago_remision_y_rol(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $principal = $resguardo->pedido()->first();
        $complemento = $this->complemento($principal);
        $servicio = app(\App\Services\ControlPedidos\Municipal\IntegrarComplementoMunicipalService::class);
        $this->assertSame('pdv', $servicio::ubicacion($principal));

        // Sin pago ni remisión propios no se integra.
        try {
            $servicio->ejecutar($principal->fresh(), $complemento->fresh(), $this->gerencia);
            $this->fail('El complemento necesita pago verificado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('pago', $e->errors());
        }
        $this->pagarYRemisionar($complemento);

        // Ventas (sin facultad de sucursal) no confirma hechos físicos.
        try {
            $servicio->ejecutar($principal->fresh(), $complemento->fresh(), $this->usuario);
            $this->fail('Ventas no confirma la integración física.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('permiso', $e->errors());
        }

        // Personal de otra sucursal tampoco.
        try {
            $servicio->ejecutar($principal->fresh(), $complemento->fresh(), $this->usuarioPdv($this->otraSucursal));
            $this->fail('Otra sucursal no integra.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('permiso', $e->errors());
        }

        $integrado = $servicio->ejecutar($principal->fresh(), $complemento->fresh(), $this->gerencia);
        $this->assertSame('integrado', \App\Support\ControlPedidos\Municipal\ComplementosMunicipales::estado($integrado));
        $this->assertSame('pdv', $integrado->solicitud_preparacion_json['complemento_municipal']['integracion']['ubicacion']);
        $this->assertSame($this->gerencia->id, $integrado->solicitud_preparacion_json['complemento_municipal']['integracion']['integrado_por_id']);
        $this->assertFalse(\App\Support\ControlPedidos\Municipal\ComplementosMunicipales::hayAbiertos($principal->fresh()));

        // Idempotente.
        $otra = $servicio->ejecutar($principal->fresh(), $complemento->fresh(), $this->gerencia);
        $this->assertSame($integrado->id, $otra->id);
        // La custodia del principal sigue en su sitio: no se creó traslado ni recepción.
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
    }

    public function test_integracion_no_ocurre_en_transito_ni_despues_de_la_salida_a_terminal(): void
    {
        $resguardo = $this->resguardoMunicipalPendiente();
        $principal = $resguardo->pedido()->first();
        $complemento = $this->complemento($principal);
        $this->pagarYRemisionar($complemento);
        $servicio = app(\App\Services\ControlPedidos\Municipal\IntegrarComplementoMunicipalService::class);

        $this->assertSame('transito', $servicio::ubicacion($principal));
        try {
            $servicio->ejecutar($principal->fresh(), $complemento->fresh(), $this->gerencia);
            $this->fail('En tránsito no se integra.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('ubicacion', $e->errors());
        }

        $principal->update(['solicitud_preparacion_json' => array_merge($principal->fresh()->solicitud_preparacion_json, ['salida_terminal' => ['at' => now()->toIso8601String(), 'receptor' => 'Terminal ficticia']])]);
        try {
            $servicio->ejecutar($principal->fresh(), $complemento->fresh(), $this->gerencia);
            $this->fail('Después de la salida a terminal no se integra.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(\App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal::MENSAJE_ANEXO_SALIO, $e->errors()['complemento'][0]);
        }
    }

    public function test_integracion_en_cedis_la_confirma_cedis_y_no_la_sucursal(): void
    {
        $principal = $this->pedido(['numero_remision' => 'REM-CEDIS']);
        $complemento = $this->complemento($principal);
        $this->pagarYRemisionar($complemento);
        $servicio = app(\App\Services\ControlPedidos\Municipal\IntegrarComplementoMunicipalService::class);
        $this->assertSame('cedis', $servicio::ubicacion($principal));

        try {
            $servicio->ejecutar($principal->fresh(), $complemento->fresh(), $this->gerencia);
            $this->fail('La sucursal no confirma lo que está en CEDIS.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('permiso', $e->errors());
        }

        $cedis = User::factory()->create();
        $cedis->givePermissionTo([Permission::findOrCreate('control_pedidos.cedis', 'web'), Permission::findOrCreate('control_pedidos.cedis.ver_todos', 'web')]);
        $integrado = $servicio->ejecutar($principal->fresh(), $complemento->fresh(), $cedis);
        $this->assertSame('cedis', $integrado->solicitud_preparacion_json['complemento_municipal']['integracion']['ubicacion']);
    }

    public function test_complemento_recorre_pago_auditoria_y_remision_propios_antes_de_integrarse(): void
    {
        foreach (['PENDIENTE_AUXILIAR' => 'AZUL_1'] as $fase => $codigo) {
            CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => $fase], ['codigo_interno' => $codigo, 'nombre_visual' => $fase, 'color_hex' => '#3B82F6', 'orden' => 2, 'activo' => true]);
        }
        $resguardo = $this->resguardoEnCustodia();
        $principal = $resguardo->pedido()->first();
        $complemento = $this->complemento($principal);
        $remisionPrincipal = $principal->fresh()->numero_remision;
        $enviar = app(\App\Services\ControlPedidos\Municipal\EnviarComplementoMunicipalAAuditoriaService::class);

        // Sin pago propio no se envía.
        try {
            $enviar->ejecutar($complemento->fresh(), $this->usuario);
            $this->fail('Sin pago no se envía a auditoría.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('pago', $e->errors());
        }

        app(\App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService::class)->handle($complemento->fresh(), [
            'monto' => 250, 'forma_pago' => 'efectivo', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ], UploadedFile::fake()->image('pago.jpg'), $this->usuario->id);
        $enviado = $enviar->ejecutar($complemento->fresh(), $this->usuario);
        $this->assertSame('PENDIENTE_AUXILIAR', $enviado->estatus->fase_ciclo);
        $this->assertTrue(app(\App\Services\ControlPedidos\ListarPedidosAuditoriaService::class)->ejecutar([], false)->contains('id', $complemento->id), 'El auxiliar ve el complemento en su bandeja');
        $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))->assertOk()
            ->assertJsonPath('resguardo.complementos_municipales.0.puede_integrar', false);

        $auxiliar = User::factory()->create();
        $validado = app(\App\Services\ControlPedidos\ValidarPagoPedidoBmaService::class)->ejecutar($enviado->fresh(), $auxiliar->id);
        $this->assertNotNull($validado['pedido']->pago_validado_at);
        app(\App\Services\ControlPedidos\GestionarRemisionPedidoBmaService::class)->actualizarNumeroRemision($complemento->fresh(), 'REM-COMP-1', $auxiliar->id);
        $this->assertSame('REM-COMP-1', $complemento->fresh()->numero_remision);

        // Pago y remisión del principal intactos.
        $this->assertSame($remisionPrincipal, $principal->fresh()->numero_remision);
        $this->assertEquals(1000, $principal->fresh()->total_mercancia);

        // Visibilidad: la sucursal lo ve en el detalle; el auxiliar lo vio en su lista al enviarse.
        $detalle = $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))->assertOk();
        $detalle->assertJsonPath('resguardo.complementos_municipales.0.id', $complemento->id)
            ->assertJsonPath('resguardo.complementos_municipales.0.pagado', true)
            ->assertJsonPath('resguardo.complementos_municipales.0.remisionado', true)
            ->assertJsonPath('resguardo.complementos_municipales.0.puede_integrar', true);

        $integrado = app(\App\Services\ControlPedidos\Municipal\IntegrarComplementoMunicipalService::class)->ejecutar($principal->fresh(), $complemento->fresh(), $this->gerencia);
        $this->assertSame('integrado', \App\Support\ControlPedidos\Municipal\ComplementosMunicipales::estado($integrado));
    }

    public function test_rutas_de_complemento_municipal_crean_envian_e_integran(): void
    {
        CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => 'PENDIENTE_AUXILIAR'], ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'PA', 'color_hex' => '#3B82F6', 'orden' => 2, 'activo' => true]);
        Storage::fake('public');
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $resguardo = $this->resguardoEnCustodia();
        $principal = $resguardo->pedido()->first();
        $principal->update(['numero_remision' => 'REM-HTTP']);
        $clave = (string) \Illuminate\Support\Str::uuid();
        $payload = fn () => [
            'solicitud_id' => $clave, 'folio_remision' => 'WIZ-HTTP', 'total_mercancia' => 120,
            'piezas' => [['descripcion' => 'Pieza', 'cantidad' => 1]],
            'evidencias' => [UploadedFile::fake()->create('piezas.pdf', 10, 'application/pdf')],
        ];

        // Quien no es la vendedora del pedido no agrega piezas.
        $this->actingAs($this->gerencia)->post(route('control_pedidos.municipal.complementos.store', $principal), $payload(), ['Accept' => 'application/json'])->assertStatus(403);

        $id = $this->actingAs($this->usuario)->post(route('control_pedidos.municipal.complementos.store', $principal), $payload(), ['Accept' => 'application/json'])
            ->assertOk()->json('complemento.id');
        $this->actingAs($this->usuario)->post(route('control_pedidos.municipal.complementos.store', $principal), $payload(), ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('complemento.id', $id);
        $this->assertSame(1, PedidoBma::query()->where('pedido_principal_id', $principal->id)->count());

        $complemento = PedidoBma::findOrFail($id);
        $this->actingAs($this->usuario)->postJson(route('control_pedidos.municipal.complementos.enviar', [$principal, $complemento]))
            ->assertUnprocessable()->assertJsonValidationErrors('pago');
        $this->actingAs($this->usuario)->postJson(route('control_pedidos.municipal.complementos.integrar', [$principal, $complemento]))
            ->assertUnprocessable();

        $this->pagarYRemisionar($complemento);
        $this->actingAs($this->gerencia)->postJson(route('control_pedidos.municipal.complementos.integrar', [$principal, $complemento]))->assertOk();
        $this->assertSame('integrado', \App\Support\ControlPedidos\Municipal\ComplementosMunicipales::estado($complemento->fresh()));
    }

    // ── Cambio de un envío municipal a recolección en sucursal ────────────────────────────────────────

    private function habilitarSucursales(array ...$pares): void
    {
        $almacenes = [];
        foreach ($pares as [$sucursal, $codigo]) {
            $tipo = CatalogoTipoAlmacen::query()->firstOrCreate(['nombre' => 'PDV'], ['activo' => true]);
            $almacen = Almacen::query()->firstOrCreate(['codigo' => $codigo], [
                'nombre' => $codigo, 'tipo_almacen_id' => $tipo->id, 'sucursal_id' => $sucursal->id, 'activo' => true, 'visible_en_pedidos' => true,
            ]);
            $almacenes[] = $almacen->id;
        }
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => \App\Services\ControlPedidos\PreparacionTiendaConfig::CLAVE_FLAG],
            ['valor' => json_encode(['activo' => true, 'almacenes_habilitados' => $almacenes])]
        );
        app(\App\Services\ControlPedidos\PreparacionTiendaConfig::class)->olvidarCache();
    }

    private function solicitarCambio(PedidoBma $pedido, array $extra = [], ?User $usuario = null): PedidoBma
    {
        Storage::fake('public');
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));

        return app(\App\Services\ControlPedidos\Municipal\SolicitarCambioMunicipalATiendaService::class)->ejecutar($pedido->fresh(), $usuario ?? $this->usuario, array_merge([
            'solicitud_id' => (string) \Illuminate\Support\Str::uuid(),
            'motivo' => 'El cliente pasará a recoger',
            'evidencias' => [UploadedFile::fake()->create('peticion.pdf', 10, 'application/pdf')],
        ], $extra));
    }

    private function aplicarCambio(PedidoBma $pedido, User $usuario): PedidoBma
    {
        return app(\App\Services\ControlPedidos\Municipal\AplicarCambioMunicipalATiendaService::class)->ejecutar($pedido->fresh(), $usuario);
    }

    private function caratula(PedidoBma $pedido, string $estado = 'GENERADA'): \App\Models\ControlPedidos\PedidoBmaCaratula
    {
        return \App\Models\ControlPedidos\PedidoBmaCaratula::query()->create([
            'pedido_bma_id' => $pedido->id, 'version' => 1, 'destinatario_nombre' => 'Persona ficticia', 'destinatario_telefono' => '3311111111', 'municipio_destino' => 'Terminal ficticia',
            'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id, 'modalidad_cobro' => 'PAGADO', 'ruta_pdf' => 'qa/caratula.pdf', 'hash_sha256' => str_repeat('a', 64), 'estado' => $estado,
            'generada_por_id' => $this->usuario->id, 'generada_at' => now(),
        ]);
    }

    public function test_solicitud_exige_motivo_y_evidencia_o_error_de_captura_y_es_idempotente(): void
    {
        $pedido = $this->pedido();
        foreach ([['motivo' => ''], ['evidencias' => []]] as $malo) {
            try {
                $this->solicitarCambio($pedido, $malo);
                $this->fail('Debe rechazar la solicitud incompleta.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        // Un error de captura sustituye la evidencia y queda registrado.
        $clave = (string) \Illuminate\Support\Str::uuid();
        $uno = $this->solicitarCambio($pedido, ['solicitud_id' => $clave, 'evidencias' => [], 'error_captura' => true]);
        $dos = $this->solicitarCambio($pedido, ['solicitud_id' => $clave, 'evidencias' => [], 'error_captura' => true]);
        $datos = $uno->solicitud_preparacion_json['cambio_a_tienda'];
        $this->assertSame('solicitado', $datos['estado']);
        $this->assertTrue($datos['error_captura']);
        $this->assertSame($uno->id, $dos->id);
        $this->assertSame(1, PedidoBmaHistorialEstado::query()->where('pedido_bma_id', $pedido->id)->where('accion', 'cambio_a_tienda_solicitado')->count());
    }

    public function test_solicitud_se_rechaza_sin_compra_tras_salida_a_terminal_y_con_piezas_sin_integrar(): void
    {
        $sinCompra = $this->pedido(['pago_validado_at' => null, 'pago_validado_por_id' => null, 'folio_remision' => null]);
        try {
            $this->solicitarCambio($sinCompra);
            $this->fail('Sin pago ni remisión se modifica el pedido.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('pedido', $e->errors());
        }

        $salio = $this->pedido();
        $salio->update(['solicitud_preparacion_json' => array_merge($salio->solicitud_preparacion_json, ['salida_terminal' => ['at' => now()->toIso8601String(), 'receptor' => 'Terminal ficticia']])]);
        try {
            $this->solicitarCambio($salio);
            $this->fail('Tras la salida a terminal no hay cambio ordinario.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(\App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal::MENSAJE_SALIO_A_TERMINAL, $e->errors()['pedido'][0]);
        }

        $conPiezas = $this->pedido(['numero_remision' => 'REM-PZ']);
        $this->complemento($conPiezas);
        try {
            $this->solicitarCambio($conPiezas);
            $this->fail('Primero se resuelven las piezas adicionales.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('pedido', $e->errors());
        }

        $historico = $this->pedido(['solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal']]);
        try {
            $this->solicitarCambio($historico);
            $this->fail('El recorrido anterior lo gestiona Control de pedidos.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('pedido', $e->errors());
        }
    }

    public function test_cambio_solicitado_detiene_la_salida_y_la_entrega_hasta_verificarse(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $pedido = $resguardo->pedido()->first();
        $this->solicitarCambio($pedido);
        $this->gerencia->givePermissionTo(PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR);

        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->fresh()->version, 'idempotency_key' => 'term:cambio', 'receptor' => 'Juan',
        ])->assertUnprocessable()->assertJsonValidationErrors('estado');

        $entrega = fn () => $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.entrega', $resguardo), [
            'version' => (int) $resguardo->fresh()->version, 'idempotency_key' => 'ent:cambio', 'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Cliente', 'metodo_validacion' => 'firma', 'firma' => UploadedFile::fake()->image('firma.png'),
        ]);
        $entrega()->assertUnprocessable();
        $this->assertSame(0, ResguardoPdvEntrega::query()->where('resguardo_id', $resguardo->id)->count());
    }

    public function test_cambio_en_sucursal_aprovecha_la_custodia_sin_traslados_ficticios_y_permite_la_entrega(): void
    {
        $resguardo = $this->resguardoEnCustodia();
        $pedido = $resguardo->pedido()->first();
        $caratula = $this->caratula($pedido);
        $this->solicitarCambio($pedido);
        $resguardos = ResguardoPdv::query()->count();
        $eventos = ResguardoPdvEvento::query()->count();

        // La vendedora no confirma hechos físicos; otra sucursal tampoco.
        foreach ([$this->usuario, $this->usuarioPdv($this->otraSucursal)] as $ajeno) {
            try {
                $this->aplicarCambio($pedido, $ajeno);
                $this->fail('Solo la sucursal que tiene el paquete lo verifica.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('permiso', $e->errors());
            }
        }

        $aplicado = $this->aplicarCambio($pedido, $this->gerencia);
        $this->assertSame('aplicado', $aplicado->solicitud_preparacion_json['cambio_a_tienda']['estado']);
        $this->assertSame('invalidada', strtolower($caratula->fresh()->estado));
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
        $this->assertSame($resguardos, ResguardoPdv::query()->count(), 'No se crea otro resguardo ni recepción');
        $this->assertSame($eventos, ResguardoPdvEvento::query()->count(), 'No se registran salidas ni recepciones nuevas');
        $this->assertSame($pedido->fresh()->numero_remision, $aplicado->numero_remision);
        $this->assertNotNull($aplicado->pago_validado_at);
        $this->assertSame($this->gerencia->id, $aplicado->solicitud_preparacion_json['cambio_a_tienda']['aplicado_por_id']);

        // Idempotente, nunca a terminal, y ahora sí se entrega al cliente.
        $this->assertSame($aplicado->id, $this->aplicarCambio($pedido, $this->gerencia)->id);
        $this->gerencia->givePermissionTo(PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR);
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.salida_terminal', $resguardo), [
            'version' => (int) $resguardo->fresh()->version, 'idempotency_key' => 'term:aplicado', 'receptor' => 'Juan',
        ])->assertUnprocessable();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.entrega', $resguardo), [
            'version' => (int) $resguardo->fresh()->version, 'idempotency_key' => 'ent:aplicado', 'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Cliente', 'metodo_validacion' => 'firma', 'firma' => UploadedFile::fake()->image('firma.png'),
        ])->assertOk();
    }

    public function test_cambio_con_el_paquete_en_cedis_lo_verifica_cedis_y_actualiza_el_destino_antes_del_primer_traslado(): void
    {
        $this->habilitarSucursales([$this->sucursal, 'PDV-A'], [$this->otraSucursal, 'PDV-B']);
        $pedido = $this->pedido();
        $solicitado = $this->solicitarCambio($pedido, ['sucursal_destino_id' => $this->otraSucursal->id]);
        $datos = $solicitado->solicitud_preparacion_json['cambio_a_tienda'];
        $this->assertFalse($datos['requiere_autorizacion_gerencia'], 'Antes del primer traslado solo se actualiza la instrucción');
        $this->assertSame($this->sucursal->id, $pedido->fresh()->sucursal_destino_id, 'Solicitar no cambia el destino');

        try {
            $this->aplicarCambio($pedido, $this->gerencia);
            $this->fail('La sucursal no verifica lo que está en CEDIS.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('permiso', $e->errors());
        }
        $cedis = User::factory()->create();
        $cedis->givePermissionTo([Permission::findOrCreate('control_pedidos.cedis', 'web'), Permission::findOrCreate('control_pedidos.cedis.ver_todos', 'web')]);
        $this->aplicarCambio($pedido, $cedis);

        $this->assertSame($this->otraSucursal->id, $pedido->fresh()->sucursal_destino_id);
        $this->assertSame(0, ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->count(), 'CEDIS aún no traslada');
    }

    public function test_otra_sucursal_con_el_traslado_hecho_exige_gerencia_y_no_se_aplica_aqui(): void
    {
        $this->habilitarSucursales([$this->sucursal, 'PDV-A'], [$this->otraSucursal, 'PDV-B']);
        $resguardo = $this->resguardoEnCustodia();
        $pedido = $resguardo->pedido()->first();
        $solicitado = $this->solicitarCambio($pedido, ['sucursal_destino_id' => $this->otraSucursal->id]);

        $this->assertTrue($solicitado->solicitud_preparacion_json['cambio_a_tienda']['requiere_autorizacion_gerencia']);
        try {
            $this->aplicarCambio($pedido, $this->gerencia);
            $this->fail('Otra sucursal con el traslado hecho necesita autorización y un nuevo traslado.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('cambio', $e->errors());
        }
        $this->assertSame($this->sucursal->id, $pedido->fresh()->sucursal_destino_id);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, $resguardo->fresh()->estado);
    }

    public function test_cambio_en_transito_no_se_aplica_hasta_recibirse(): void
    {
        $resguardo = $this->resguardoMunicipalPendiente();
        $pedido = $resguardo->pedido()->first();
        $this->solicitarCambio($pedido);

        try {
            $this->aplicarCambio($pedido, $this->gerencia);
            $this->fail('En tránsito se verifica al recibir.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('ubicacion', $e->errors());
        }
    }

    public function test_presentador_del_cambio_indica_quien_puede_solicitar_y_quien_verificar(): void
    {
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $resguardo = $this->resguardoEnCustodia();
        $pedido = $resguardo->pedido()->first();
        $presentar = fn (User $u) => \App\Support\ControlPedidos\Municipal\PresentadorCambioATienda::para($pedido->fresh(), $u);

        $this->assertTrue($presentar($this->usuario)['puede_solicitar']);
        $this->assertFalse($presentar($this->usuario)['puede_aplicar']);
        $this->assertNull($presentar($this->gerencia), 'Sin solicitud, el área no ve nada que verificar');

        $this->solicitarCambio($pedido);
        $ventas = $presentar($this->usuario);
        $this->assertFalse($ventas['puede_solicitar']);
        $this->assertFalse($ventas['puede_aplicar'], 'La vendedora no verifica hechos físicos');
        $this->assertSame('Solicitado, pendiente de verificar', $ventas['etiqueta']);
        $this->assertTrue($presentar($this->gerencia)['puede_aplicar']);
        $this->assertFalse($presentar($this->usuarioPdv($this->otraSucursal))['puede_aplicar']);

        $detalle = $this->actingAs($this->gerencia)->getJson(route('punto_venta.resguardos.show', $resguardo))->assertOk();
        $detalle->assertJsonPath('resguardo.cambio_a_tienda.puede_aplicar', true)->assertJsonPath('resguardo.cambio_a_tienda.estado', 'solicitado');

        $this->aplicarCambio($pedido, $this->gerencia);
        $this->assertSame('Verificado, recoge en sucursal', $presentar($this->gerencia)['etiqueta']);
        $this->assertFalse($presentar($this->gerencia)['puede_aplicar']);
    }

    public function test_rutas_del_cambio_a_recoleccion_en_sucursal(): void
    {
        Storage::fake('public');
        $this->usuario->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $resguardo = $this->resguardoEnCustodia();
        $pedido = $resguardo->pedido()->first();
        $datos = fn () => ['solicitud_id' => (string) \Illuminate\Support\Str::uuid(), 'motivo' => 'El cliente recoge', 'error_captura' => 1];

        $this->actingAs($this->gerencia)->postJson(route('control_pedidos.municipal.cambio_a_tienda.solicitar', $pedido), $datos())->assertForbidden();
        $this->actingAs($this->usuario)->postJson(route('control_pedidos.municipal.cambio_a_tienda.solicitar', $pedido), $datos())->assertOk()->assertJsonPath('estado', 'solicitado');
        $this->actingAs($this->usuario)->postJson(route('control_pedidos.municipal.cambio_a_tienda.aplicar', $pedido))->assertUnprocessable();
        $this->actingAs($this->gerencia)->postJson(route('control_pedidos.municipal.cambio_a_tienda.aplicar', $pedido))->assertOk()->assertJsonPath('estado', 'aplicado');
    }

    private function usuarioPdv(Sucursal $sucursal, bool $salidaTerminal = true): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(array_values(array_filter([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER_HISTORIAL_ENTREGAS,
            $salidaTerminal ? self::PERMISO : null,
        ])));
        $user->concederAccesoSucursal($sucursal, esPrincipal: true);

        return $user;
    }

    private function resguardoEnCustodia(): ResguardoPdv
    {
        $resguardo = $this->resguardoMunicipalPendiente();
        $folios = BultosEsperadosResguardoPdv::desdeResguardo($resguardo);
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.recepcion', $resguardo), $this->payloadRecepcion($resguardo, 'st:rec:'.$resguardo->id))->assertOk();
        $resguardo->refresh();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.pasar_recepcion', $resguardo), $this->payloadRecepcion($resguardo, 'st:pasar:'.$resguardo->id))->assertOk();
        $resguardo->refresh();
        $this->actingAs($this->gerencia)->put(route('punto_venta.resguardos.custodia', $resguardo), [
            'version' => (int) $resguardo->version,
            'idempotency_key' => 'st:custodia:'.$resguardo->id,
            'almacen_id' => $this->piso->id,
            'bultos' => [['folio' => $folios[0]['folio'], 'tipo' => ResguardoPdvBulto::TIPO_CAJA, 'condicion' => 'bueno', 'piezas' => 1]],
        ])->assertOk()->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_EN_CUSTODIA);

        return $resguardo->fresh();
    }

    private function resguardoMunicipalPendiente(): ResguardoPdv
    {
        $pedido = $this->pedido();
        app(MarcarEnviadoPedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);
        $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->firstOrFail();
        $this->assertSame('municipal', $resguardo->snapshot_json['origen']);

        return $resguardo;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadRecepcion(ResguardoPdv $resguardo, string $clave): array
    {
        return [
            'condicion' => 'bueno',
            'foto_paquete' => UploadedFile::fake()->image('llegada.jpg'),
            'folios' => array_column(BultosEsperadosResguardoPdv::desdeResguardo($resguardo), 'folio'),
            'version' => (int) $resguardo->version,
            'idempotency_key' => $clave,
        ];
    }

    private function pedido(array $extra = []): PedidoBma
    {
        $comercial = CatalogoPaqueteriaPedido::query()->where('categoria', CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL)->first();

        return PedidoBma::query()->create(array_merge([
            'folio' => 'PED-TER-'.uniqid(),
            'folio_remision' => 'REM-TER-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => CatalogoOrigenPedido::query()->where('nombre', 'Envío Foráneo')->value('id'),
            'almacen_id' => $this->cedis->id,
            'sucursal_destino_id' => $this->sucursal->id,
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 1.5,
            'catalogo_paqueteria_id' => $comercial->id,
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO)->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
            'empacado_at' => now(),
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'municipal', 'recorrido_fisico_municipal' => true],
        ], $extra));
    }

    private function seedCatalogos(): void
    {
        $now = now();
        foreach ([
            ['BORRADOR', 'BORRADOR', 1], ['AMARILLO', 'EN_CEDIS', 3],
            ['PENDIENTE_ENVIO', 'PENDIENTE_DE_ENVIO', 10], ['ENTREGADO', 'ENTREGADO', 8], ['ENVIADO', 'ENVIADO', 9],
        ] as [$codigo, $fase, $orden]) {
            CatalogoEstatusPedido::query()->firstOrCreate(['codigo_interno' => $codigo], [
                'nombre_visual' => $codigo, 'color_hex' => '#3B82F6', 'fase_ciclo' => $fase, 'orden' => $orden, 'activo' => true,
            ]);
        }
        CatalogoOrigenPedido::query()->firstOrCreate(['nombre' => 'Envío Foráneo'], ['requiere_logistica' => true, 'activo' => true]);
        DB::table('catalogo_bancos')->insertOrIgnore(['nombre' => 'BBVA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_listas_descuento')->insertOrIgnore(['nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_paqueterias_pedido')->insertOrIgnore(['nombre' => 'PAQ COMERCIAL', 'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_tipos_caja_pedido')->insertOrIgnore(['nombre' => 'CAJA TEST', 'peso_volumetrico' => 1, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_tipos_guia_pedido')->insertOrIgnore(['nombre' => 'Terrestre', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('catalogo_zonas_pedido')->insertOrIgnore(['nombre' => 'Zona 1', 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        $lista = DB::table('catalogo_listas_descuento')->value('id');
        DB::table('clientes')->insertOrIgnore([
            'numero_cliente' => 'TER-1', 'nombre' => 'Cliente', 'lista_actual_id' => $lista, 'vendedor_id' => $this->usuario->id,
            'monto_venta_actual' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
