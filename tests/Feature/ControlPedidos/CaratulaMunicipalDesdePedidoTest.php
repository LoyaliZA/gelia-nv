<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Cliente;
use App\Models\ClienteConsignatario;
use App\Models\ClienteConsignatarioVersion;
use App\Models\CatalogoListaDescuento;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\Municipal\GenerarCaratulaMunicipalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CaratulaMunicipalDesdePedidoTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->sucursal = Sucursal::factory()->create(['activo' => true]);
        $this->usuario = $this->usuario(['control_pedidos.tienda.generar_caratula', 'control_pedidos.tienda.regenerar_caratula', 'control_pedidos.tienda.imprimir_caratula'], $this->sucursal);
    }

    /** @param list<string> $permisos */
    private function usuario(array $permisos, ?Sucursal $sucursal): User
    {
        $user = User::factory()->create();
        foreach ($permisos as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        if ($sucursal) {
            $user->concederAccesoSucursal($sucursal, esPrincipal: true);
        }

        return $user;
    }

    /** @param array<string, mixed> $json @param array<string, mixed> $atributos */
    private function pedido(array $json = [], array $atributos = [], bool $consignatario = true): PedidoBma
    {
        static $n = 0;
        $n++;
        $vendedor = User::factory()->create();
        $lista = CatalogoListaDescuento::query()->firstOrCreate(['nombre' => 'PUBLICO GENERAL'], ['monto_requerido' => 0, 'activo' => true]);
        $cliente = Cliente::query()->create(['numero_cliente' => 'CM-'.$n, 'nombre' => 'Cliente', 'lista_actual_id' => $lista->id, 'vendedor_id' => $vendedor->id, 'monto_venta_actual' => 0]);
        $estatus = CatalogoEstatusPedido::query()->firstOrCreate(['codigo_interno' => 'CM_BASE'], ['nombre_visual' => 'B', 'color_hex' => '#64748B', 'fase_ciclo' => 'BORRADOR', 'orden' => 1, 'activo' => true]);
        $paqueteria = CatalogoPaqueteriaPedido::query()->firstOrCreate(['nombre' => 'Transporte local CM'], ['categoria' => 'local_regional', 'activo' => true]);
        $expediente = [];
        if ($consignatario) {
            $c = ClienteConsignatario::query()->create(['cliente_id' => $cliente->id, 'activo' => true]);
            $v = ClienteConsignatarioVersion::query()->create(['consignatario_id' => $c->id, 'nombre' => 'María Receptora', 'telefono' => '2221112233', 'vigente' => true, 'version' => 1, 'creado_por_id' => $vendedor->id]);
            $expediente = ['expediente' => ['consignatario_id' => $c->id, 'version_datos_id' => $v->id, 'documento_id' => null, 'nombre' => 'María Receptora', 'telefono' => '2221112233']];
        }
        $pedido = new PedidoBma(array_merge([
            'folio' => 'CM-'.$n, 'vendedor_id' => $vendedor->id, 'cliente_id' => $cliente->id, 'catalogo_estatus_pedido_id' => $estatus->id,
            'fecha' => now()->toDateString(), 'total_mercancia' => 100, 'sucursal_destino_id' => $this->sucursal->id, 'catalogo_paqueteria_id' => $paqueteria->id,
            'solicitud_preparacion_json' => array_merge([
                'modalidad_entrega' => 'municipal', 'recorrido_fisico_municipal' => true,
                'finanzas_municipales' => ['costo_transporte' => 'conocido', 'leyenda_terminal' => 'PAGADO'],
            ], $expediente, $json),
        ], $atributos));
        $pedido->save();

        return $pedido->fresh();
    }

    public function test_genera_caratula_con_datos_del_pedido_sin_tarea(): void
    {
        $pedido = $this->pedido();
        $c = app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $this->usuario);

        $this->assertSame(PedidoBmaCaratula::ESTADO_GENERADA, $c->estado);
        $this->assertNull($c->pedido_bma_tarea_preparacion_id);
        $this->assertSame($pedido->id, $c->pedido_bma_id);
        $this->assertSame(1, $c->version);
        $this->assertSame('María Receptora', $c->destinatario_nombre);
        $this->assertSame('2221112233', $c->destinatario_telefono);
        $this->assertSame('Transporte local CM', $c->municipio_destino);
        $this->assertSame($this->usuario->id, $c->generada_por_id);
        $this->assertNotNull($c->hash_sha256);
        Storage::disk('local')->assertExists($c->ruta_pdf);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($c->ruta_pdf)), $c->hash_sha256);
        $this->assertDatabaseHas('pedido_bma_historial_estados', ['pedido_bma_id' => $pedido->id, 'accion' => 'caratula_generada']);
    }

    public function test_municipio_destino_del_pedido_tiene_precedencia_sobre_el_transporte(): void
    {
        $pedido = $this->pedido(['municipio_destino' => 'Tehuacán']);
        $c = app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $this->usuario);

        $this->assertSame('Tehuacán', $c->municipio_destino);
    }

    public function test_sin_municipio_el_pdf_lleva_el_nombre_de_la_terminal(): void
    {
        $pedido = $this->pedido();
        $c = app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $this->usuario);

        $this->assertSame('Transporte local CM', $c->municipio_destino);
        $this->assertStringContainsString('%PDF', Storage::disk('local')->get($c->ruta_pdf));
    }

    public function test_sin_paqueteria_rechaza(): void
    {
        $pedido = $this->pedido([], ['catalogo_paqueteria_id' => null]);
        $this->expectException(ValidationException::class);
        app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $this->usuario);
    }

    public function test_regenerar_invalida_la_anterior_y_sube_version(): void
    {
        $pedido = $this->pedido();
        $servicio = app(GenerarCaratulaMunicipalService::class);
        $v1 = $servicio->ejecutar($pedido, $this->usuario);

        try {
            $servicio->ejecutar($pedido, $this->usuario);
            $this->fail('Debe pedir motivo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('motivo_regeneracion', $e->errors());
        }

        $v2 = $servicio->ejecutar($pedido, $this->usuario, 'Teléfono corregido');
        $this->assertSame(2, $v2->version);
        $this->assertSame('Teléfono corregido', $v2->motivo_regeneracion);
        $this->assertSame(PedidoBmaCaratula::ESTADO_INVALIDADA, $v1->fresh()->estado);
        $this->assertSame(PedidoBmaCaratula::ESTADO_GENERADA, $v2->estado);
        $this->assertDatabaseHas('pedido_bma_historial_estados', ['pedido_bma_id' => $pedido->id, 'accion' => 'caratula_regenerada']);
    }

    public function test_regenerar_exige_permiso_de_regeneracion(): void
    {
        $pedido = $this->pedido();
        app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $this->usuario);
        $solo = $this->usuario(['control_pedidos.tienda.generar_caratula'], $this->sucursal);

        $this->expectException(\RuntimeException::class);
        app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $solo, 'Motivo');
    }

    public function test_modalidad_cobro_sale_del_pedido(): void
    {
        $pagado = $this->pedido();
        $porCobrar = $this->pedido(['finanzas_municipales' => ['costo_transporte' => 'conocido', 'leyenda_terminal' => 'POR_COBRAR']]);
        $servicio = app(GenerarCaratulaMunicipalService::class);

        $this->assertSame('PAGADO', $servicio->ejecutar($pagado, $this->usuario)->modalidad_cobro);
        $this->assertSame('POR_COBRAR', $servicio->ejecutar($porCobrar, $this->usuario)->modalidad_cobro);
    }

    public function test_sin_consignatario_vigente_rechaza(): void
    {
        $pedido = $this->pedido(consignatario: false);
        $this->expectException(ValidationException::class);
        app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $this->usuario);
    }

    public function test_rechaza_consignatario_sin_version_vigente(): void
    {
        $pedido = $this->pedido();
        ClienteConsignatarioVersion::query()->update(['vigente' => false]);
        $this->expectException(ValidationException::class);
        app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $this->usuario);
    }

    public function test_rechaza_pedido_que_no_es_recorrido_nuevo_o_esta_cancelado(): void
    {
        $servicio = app(GenerarCaratulaMunicipalService::class);
        $legado = $this->pedido(['recorrido_fisico_municipal' => false]);
        $cancelado = $this->pedido([], ['cancelado_at' => now()]);

        foreach ([$legado, $cancelado] as $pedido) {
            try {
                $servicio->ejecutar($pedido, $this->usuario);
                $this->fail('Debió rechazar.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, PedidoBmaCaratula::query()->count());
    }

    public function test_exige_permiso_y_sucursal_del_destino(): void
    {
        $pedido = $this->pedido();
        $sinPermiso = $this->usuario([], $this->sucursal);
        $otra = $this->usuario(['control_pedidos.tienda.generar_caratula'], Sucursal::factory()->create(['activo' => true]));

        foreach ([$sinPermiso, $otra] as $u) {
            try {
                app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $u);
                $this->fail('Debió rechazar.');
            } catch (\RuntimeException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, PedidoBmaCaratula::query()->count());
    }

    public function test_municipal_con_tarea_vieja_sigue_usando_su_flujo(): void
    {
        $pedido = $this->pedido(['recorrido_fisico_municipal' => false]);
        $this->expectException(ValidationException::class);
        app(GenerarCaratulaMunicipalService::class)->ejecutar($pedido, $this->usuario);
    }

    public function test_endpoints_generan_regeneran_y_descargan(): void
    {
        $pedido = $this->pedido();
        $this->actingAs($this->usuario)
            ->post(route('control_pedidos.tienda.municipal.caratula.generar', $pedido))
            ->assertRedirect()->assertSessionHas('success')->assertSessionMissing('error');
        $c = PedidoBmaCaratula::query()->sole();

        $this->actingAs($this->usuario)
            ->get(route('control_pedidos.tienda.municipal.caratula.pdf', [$pedido, $c]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->usuario)
            ->post(route('control_pedidos.tienda.municipal.caratula.regenerar', $pedido), ['motivo_regeneracion' => 'Dato corregido'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(2, PedidoBmaCaratula::query()->max('version'));

        $ajeno = $this->usuario(['control_pedidos.tienda.imprimir_caratula'], Sucursal::factory()->create(['activo' => true]));
        $this->actingAs($ajeno)->get(route('control_pedidos.tienda.municipal.caratula.pdf', [$pedido, $c]))->assertForbidden();
    }

    public function test_falla_de_base_de_datos_no_muestra_texto_sql(): void
    {
        $pedido = $this->pedido();
        \Illuminate\Support\Facades\Schema::rename('pedido_bma_caratulas', 'pedido_bma_caratulas_fuera');

        $respuesta = $this->actingAs($this->usuario)
            ->post(route('control_pedidos.tienda.municipal.caratula.generar', $pedido))
            ->assertRedirect();
        $mensaje = (string) session('error');
        $this->assertSame('No se pudo generar la carátula. Intente de nuevo; si continúa, avise a Sistemas.', $mensaje);
        $this->assertStringNotContainsString('SQLSTATE', $mensaje);
        $this->assertStringNotContainsString('pedido_bma_caratulas', (string) $respuesta->getContent());
    }

    public function test_errores_propios_del_servicio_siguen_visibles(): void
    {
        $pedido = $this->pedido();
        $otra = $this->usuario(['control_pedidos.tienda.generar_caratula'], Sucursal::factory()->create(['activo' => true]));

        $this->actingAs($otra)
            ->post(route('control_pedidos.tienda.municipal.caratula.generar', $pedido))
            ->assertRedirect()
            ->assertSessionHas('error', 'Solo el personal de la sucursal que recibe el envío genera la carátula.');
    }
}
