<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteConsignatario;
use App\Models\ClienteConsignatarioVersion;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\User;
use App\Services\ControlPedidos\FusionarSolicitudPreparacionPedido;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use App\Support\ControlPedidos\Conversion\ConsultaPedidoYaCerradaAlgunaVez;
use App\Support\ControlPedidos\Conversion\ConversionModalidadEnCursoPedidoBma;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConversionMotorATest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshIsolatedDatabase;
    }

    private User $ventas;

    public function refreshDatabase(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->refreshIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        Storage::fake('public');
        Cache::flush();
        $this->ventas = User::factory()->create();
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR],
            ['codigo_interno' => 'M02_BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#64748B', 'orden' => 1, 'activo' => true]
        );
    }

    public function test_reserva_es_exclusiva_por_instancia_y_token_ajeno_no_renueva_ni_suelta(): void
    {
        $pedido = $this->pedidoTienda();
        $this->actingAs($this->ventas);
        $a = (string) Str::uuid();
        $b = (string) Str::uuid();

        $this->postJson(route('control_pedidos.conversion_modalidad.reserva.abrir', $pedido), ['instancia_id' => $a])
            ->assertOk()
            ->assertJson(['ok' => true, 'reserva_activa' => true, 'ttl_segundos' => 45]);

        $this->postJson(route('control_pedidos.conversion_modalidad.reserva.abrir', $pedido), ['instancia_id' => $b])
            ->assertStatus(409)
            ->assertJson(['codigo' => CodigosConversionModalidad::CODIGO_CONVERSION_EN_CURSO]);

        $this->postJson(route('control_pedidos.conversion_modalidad.reserva.latido', $pedido), ['instancia_id' => $b])
            ->assertStatus(409);
        $this->deleteJson(route('control_pedidos.conversion_modalidad.reserva.soltar', $pedido), ['instancia_id' => $b])
            ->assertOk()
            ->assertJson(['ok' => false, 'reserva_activa' => true]);
        $this->assertTrue(ConversionModalidadEnCursoPedidoBma::activa((int) $pedido->id));

        $this->postJson(route('control_pedidos.conversion_modalidad.reserva.latido', $pedido), ['instancia_id' => $a])
            ->assertOk();
        $this->deleteJson(route('control_pedidos.conversion_modalidad.reserva.soltar', $pedido), ['instancia_id' => $a])
            ->assertOk()
            ->assertJson(['ok' => true, 'reserva_activa' => false]);
    }

    public function test_consulta_reabierta_sigue_cerrada_alguna_vez_por_historial(): void
    {
        $pedido = $this->pedidoTienda();
        $this->assertFalse(ConsultaPedidoYaCerradaAlgunaVez::es($pedido));
        PedidoBmaHistorialEstado::query()->create([
            'pedido_bma_id' => $pedido->id,
            'usuario_id' => $this->ventas->id,
            'accion' => AccionesHistorialPedidoBma::CIERRE_CONSULTA,
            'estatus_nuevo_id' => $pedido->catalogo_estatus_pedido_id,
            'comentarios' => 'Cierre histórico',
        ]);
        $pedido->update(['consulta_cerrada_at' => null]);
        $this->assertTrue(ConsultaPedidoYaCerradaAlgunaVez::es($pedido->fresh(['historial'])));
    }

    public function test_cierre_con_reserva_activa_devuelve_422_y_tras_confirmar_el_cierre_tienda_es_409(): void
    {
        $pedido = $this->pedidoTienda();
        $this->actingAs($this->ventas);
        $instancia = (string) Str::uuid();
        $this->postJson(route('control_pedidos.conversion_modalidad.reserva.abrir', $pedido), ['instancia_id' => $instancia])->assertOk();

        $this->postJson(route('control_pedidos.cerrar_consulta', $pedido), [
            'modalidad_esperada' => 'tienda',
            'version_consulta' => 0,
        ])->assertStatus(422)->assertJson(['codigo' => CodigosConversionModalidad::CODIGO_CONVERSION_EN_CURSO]);

        $this->confirmar($pedido, $instancia)->assertOk()->assertJsonPath('modalidad_entrega', 'municipal');

        $this->postJson(route('control_pedidos.cerrar_consulta', $pedido), [])
            ->assertStatus(409)
            ->assertJson(['codigo' => CodigosConversionModalidad::CODIGO_CONTEXTO_OBSOLETO]);
        $this->postJson(route('control_pedidos.cerrar_consulta', $pedido), [
            'modalidad_esperada' => 'tienda',
            'version_consulta' => (int) $pedido->fresh()->historial()->max('id'),
        ])->assertStatus(409)->assertJson(['codigo' => CodigosConversionModalidad::CODIGO_CONTEXTO_OBSOLETO]);
    }

    public function test_confirmacion_idempotente_no_duplica_y_consulta_historica_bloquea(): void
    {
        $pedido = $this->pedidoTienda();
        $this->actingAs($this->ventas);
        $instancia = (string) Str::uuid();
        $this->postJson(route('control_pedidos.conversion_modalidad.reserva.abrir', $pedido), ['instancia_id' => $instancia])->assertOk();
        $clave = 'm02-idem-1';
        $this->confirmar($pedido, $instancia, $clave)->assertOk();
        $version = (int) $pedido->fresh()->solicitud_preparacion_json['conversion_modalidad']['conversion_version'];
        $this->confirmar($pedido->fresh(), $instancia, $clave, 0, 0)
            ->assertOk()
            ->assertJsonPath('conversion_version', $version);
        $this->assertSame(1, (int) $pedido->fresh()->solicitud_preparacion_json['conversion_modalidad']['conversion_version']);

        $cerrado = $this->pedidoTienda(['folio' => 'M02-CER']);
        PedidoBmaHistorialEstado::query()->create([
            'pedido_bma_id' => $cerrado->id,
            'usuario_id' => $this->ventas->id,
            'accion' => AccionesHistorialPedidoBma::CIERRE_CONSULTA,
            'estatus_nuevo_id' => $cerrado->catalogo_estatus_pedido_id,
        ]);
        $otra = (string) Str::uuid();
        $this->postJson(route('control_pedidos.conversion_modalidad.reserva.abrir', $cerrado), ['instancia_id' => $otra])
            ->assertStatus(422)
            ->assertJson(['codigo' => CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA]);
    }

    public function test_fusionar_pinnea_modalidad_y_conserva_conversion_tras_confirmar(): void
    {
        $fusionar = app(FusionarSolicitudPreparacionPedido::class);
        $actual = [
            'modalidad_entrega' => 'municipal',
            'conversion_modalidad' => ['estado' => 'confirmada', 'destino' => 'municipal', 'conversion_version' => 1],
            'expediente' => ['consignatario_id' => 1],
        ];
        $salida = $fusionar->ejecutar($actual, ['modalidad_entrega' => 'tienda']);
        $this->assertSame('municipal', $salida['modalidad_entrega']);
        $this->assertSame('confirmada', $salida['conversion_modalidad']['estado']);
    }

    public function test_herencia_sin_consulta_queda_lista_para_iniciar(): void
    {
        $pedido = $this->pedidoTienda();
        $this->actingAs($this->ventas);
        $instancia = (string) Str::uuid();
        $this->postJson(route('control_pedidos.conversion_modalidad.reserva.abrir', $pedido), ['instancia_id' => $instancia])->assertOk();
        $this->confirmar($pedido, $instancia)
            ->assertOk()
            ->assertJsonPath('herencia.caso', CodigosConversionModalidad::CASO_SIN_CONSULTA)
            ->assertJsonPath('herencia.procedencia', CodigosConversionModalidad::PROCEDENCIA_NUEVA);
        $json = $pedido->fresh()->solicitud_preparacion_json;
        $this->assertArrayNotHasKey('enviada_at', $json['consulta_municipal'] ?? []);
        $this->assertSame('nueva', $json['consulta_municipal']['procedencia'] ?? null);
        $this->assertArrayNotHasKey('revision_disponibilidad', $json);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedidoTienda(array $extra = []): PedidoBma
    {
        $lista = CatalogoListaDescuento::query()->firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
        $cliente = Cliente::query()->create([
            'numero_cliente' => 'M02-'.uniqid(),
            'nombre' => 'Cliente conversion',
            'lista_actual_id' => $lista->id,
            'vendedor_id' => $this->ventas->id,
            'monto_venta_actual' => 0,
        ]);
        $estatus = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR);

        return PedidoBma::query()->create(array_merge([
            'folio' => 'M02-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->ventas->id,
            'cliente_id' => $cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'revision_registro' => 0,
            'solicitud_preparacion_json' => ['modalidad_entrega' => 'tienda'],
        ], $extra));
    }

    private function confirmar(PedidoBma $pedido, string $instancia, string $clave = 'm02-clave', int $revision = 0, int $version = 0)
    {
        $consignatario = ClienteConsignatario::query()->create(['cliente_id' => $pedido->cliente_id, 'activo' => true]);
        ClienteConsignatarioVersion::query()->create([
            'consignatario_id' => $consignatario->id,
            'nombre' => 'Persona consignataria',
            'telefono' => '3311111111',
            'vigente' => true,
            'version' => 1,
        ]);
        $paqueteria = CatalogoPaqueteriaPedido::query()->firstOrCreate(
            ['nombre' => 'Transporte M02'],
            [
                'sin_logistica' => false,
                'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
                'activo' => true,
                'permite_costo_diferido' => true,
                'habilitado_envio_municipio' => true,
                'permite_por_cobrar' => false,
                'reglas_municipio_pendientes' => false,
            ]
        );

        return $this->post(route('control_pedidos.conversion_modalidad.confirmar', $pedido), [
            'destino' => 'municipal',
            'instancia_id' => $instancia,
            'motivo' => 'La persona cliente pidió envío a municipio.',
            'error_captura' => false,
            'evidencias' => [UploadedFile::fake()->image('peticion.jpg')],
            'catalogo_paqueteria_id' => $paqueteria->id,
            'consignatario_id' => $consignatario->id,
            'leyenda_terminal' => 'PAGADO',
            'costo_transporte' => 'conocido',
            'costo_envio' => '150.00',
            'revision_esperada' => $revision,
            'conversion_version' => $version,
            'clave_idempotencia' => $clave,
        ], ['Accept' => 'application/json']);
    }
}
