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
