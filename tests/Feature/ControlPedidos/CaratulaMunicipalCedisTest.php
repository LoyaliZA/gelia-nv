<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteConsignatario;
use App\Models\ClienteConsignatarioVersion;
use App\Models\ClienteDocumento;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\ControlPedidos\Municipal\PresentadorCaratulaMunicipalCedis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CaratulaMunicipalCedisTest extends TestCase
{
    use RefreshDatabase;

    private User $cedis;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->cedis = $this->usuario(['control_pedidos.cedis', 'control_pedidos.cedis.ver_todos']);
    }

    /** @param list<string> $permisos */
    private function usuario(array $permisos): User
    {
        $user = User::factory()->create();
        foreach ($permisos as $permiso) {
            $user->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
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
        $cliente = Cliente::query()->create([
            'numero_cliente' => 'CC-'.$n, 'nombre' => 'Cliente', 'lista_actual_id' => $lista->id,
            'vendedor_id' => $vendedor->id, 'monto_venta_actual' => 0,
        ]);
        $estatus = CatalogoEstatusPedido::query()->firstOrCreate(
            ['codigo_interno' => 'CC_CEDIS'],
            ['nombre_visual' => 'En CEDIS', 'color_hex' => '#64748B', 'fase_ciclo' => CatalogoEstatusPedido::FASE_EN_CEDIS, 'orden' => 20, 'activo' => true]
        );
        $paqueteria = CatalogoPaqueteriaPedido::query()->firstOrCreate(
            ['nombre' => 'Taxi Macuspana'],
            ['categoria' => 'local_regional', 'activo' => true]
        );
        $expediente = [];
        if ($consignatario) {
            $c = ClienteConsignatario::query()->create(['cliente_id' => $cliente->id, 'activo' => true]);
            $v = ClienteConsignatarioVersion::query()->create([
                'consignatario_id' => $c->id, 'nombre' => 'Jesus Garcia Ramirez', 'telefono' => '9933831337',
                'vigente' => true, 'version' => 1, 'creado_por_id' => $vendedor->id,
            ]);
            $expediente = ['expediente' => [
                'consignatario_id' => $c->id, 'version_datos_id' => $v->id, 'documento_id' => null,
                'nombre' => 'Jesus Garcia Ramirez', 'telefono' => '9933831337',
            ]];
        }
        $pedido = new PedidoBma(array_merge([
            'folio' => 'CC-'.$n, 'vendedor_id' => $vendedor->id, 'cliente_id' => $cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id, 'fecha' => now()->toDateString(),
            'total_mercancia' => 100, 'catalogo_paqueteria_id' => $paqueteria->id,
            'solicitud_preparacion_json' => array_merge([
                'modalidad_entrega' => 'municipal', 'recorrido_fisico_municipal' => true,
                'finanzas_municipales' => ['costo_transporte' => 'conocido', 'leyenda_terminal' => 'PAGADO'],
            ], $expediente, $json),
        ], $atributos));
        $pedido->save();

        return $pedido->fresh(['paqueteria', 'estatus', 'cliente']);
    }

    private function identificacion(PedidoBma $pedido, string $nombre = 'ine.jpg'): ClienteDocumento
    {
        $id = (int) $pedido->solicitud_preparacion_json['expediente']['consignatario_id'];
        $archivo = UploadedFile::fake()->image($nombre, 400, 250);
        $ruta = $archivo->store('clientes/expediente/'.$pedido->cliente_id.'/'.$id, 'local');

        return ClienteDocumento::query()->create([
            'consignatario_id' => $id,
            'tipo' => ClienteDocumento::TIPO_IDENTIFICACION,
            'ruta' => $ruta,
            'nombre_original' => $nombre,
            'mime_type' => $archivo->getMimeType() ?: 'image/jpeg',
            'tamano_bytes' => $archivo->getSize() ?: 100,
            'hash_sha256' => hash('sha256', 'ine'),
            'vigente' => true,
            'version' => 1,
            'creado_por_id' => $pedido->vendedor_id,
        ]);
    }

    public function test_presentador_resuelve_nombre_aunque_el_snapshot_venga_vacio(): void
    {
        $pedido = $this->pedido(['expediente' => [
            'consignatario_id' => null, 'nombre' => '', 'telefono' => '',
        ]]);
        $id = ClienteConsignatario::query()->where('cliente_id', $pedido->cliente_id)->value('id');
        $json = $pedido->solicitud_preparacion_json;
        $json['expediente'] = ['consignatario_id' => $id, 'nombre' => '', 'telefono' => ''];
        $pedido->solicitud_preparacion_json = $json;
        $pedido->save();

        $resumen = PresentadorCaratulaMunicipalCedis::para($pedido->fresh());

        $this->assertSame('Jesus Garcia Ramirez', $resumen['nombre']);
        $this->assertSame('9933831337', $resumen['telefono']);
        $this->assertTrue($resumen['tiene_consignatario']);
        $this->assertFalse($resumen['tiene_identificacion']);
        $this->assertSame('PAGADO', $resumen['leyenda']);
    }

    public function test_pdf_sin_identificacion_es_carta_con_nombre_telefono_y_transporte(): void
    {
        $pedido = $this->pedido([], ['sucursal_destino_id' => null]);

        $respuesta = $this->actingAs($this->cedis)
            ->get(route('control_pedidos.cedis.caratula_municipal', $pedido).'?variante=sin_identificacion');

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertDatabaseHas('pedido_bma_historial_estados', [
            'pedido_bma_id' => $pedido->id,
            'accion' => 'descarga_caratula',
        ]);
        $this->assertSame(0, \App\Models\ControlPedidos\PedidoBmaCaratula::query()->count());

        $html = view('control_pedidos.caratula_municipal_cedis', [
            'caratula' => [
                'destinatario_nombre' => 'Jesus Garcia Ramirez',
                'destinatario_telefono' => '9933831337',
                'transporte' => 'TRANSPORTE Taxi Macuspana',
                'modalidad_cobro' => 'PAGADO',
                'variante' => 'sin_identificacion',
                'identificacion_data_uri' => null,
            ],
        ])->render();
        $this->assertSame(2, substr_count($html, 'JESUS GARCIA RAMIREZ'));
        $this->assertStringContainsString('CEL: 9933831337', $html);
        $this->assertStringContainsString('TRANSPORTE Taxi Macuspana', $html);
        $this->assertStringContainsString('PAGADO', $html);
        $this->assertStringNotContainsString('GELIA', $html);
    }

    public function test_pdf_con_identificacion_incluye_la_imagen_y_no_exige_sucursal_destino(): void
    {
        $pedido = $this->pedido([], ['sucursal_destino_id' => null]);
        $this->identificacion($pedido);

        $respuesta = $this->actingAs($this->cedis)
            ->get(route('control_pedidos.cedis.caratula_municipal', $pedido).'?variante=con_identificacion');

        $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertGreaterThan(8000, strlen($respuesta->getContent()));
        $this->assertDatabaseHas('pedido_bma_historial_estados', [
            'pedido_bma_id' => $pedido->id,
            'accion' => 'descarga_caratula',
        ]);
    }

    public function test_sin_archivo_de_identificacion_responde_422(): void
    {
        $pedido = $this->pedido();

        $this->actingAs($this->cedis)
            ->getJson(route('control_pedidos.cedis.caratula_municipal', $pedido).'?variante=con_identificacion')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Este consignatario no tiene archivo de identificación.']);
    }

    public function test_sin_consignatario_vigente_responde_422(): void
    {
        $pedido = $this->pedido([], [], false);

        $this->actingAs($this->cedis)
            ->getJson(route('control_pedidos.cedis.caratula_municipal', $pedido).'?variante=sin_identificacion')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'El pedido no tiene un consignatario vigente.']);
    }

    public function test_pedido_no_municipal_responde_422(): void
    {
        $pedido = $this->pedido(['modalidad_entrega' => 'nacional', 'recorrido_fisico_municipal' => false]);

        $this->actingAs($this->cedis)
            ->getJson(route('control_pedidos.cedis.caratula_municipal', $pedido).'?variante=sin_identificacion')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Este pedido no es un envío municipal.']);
    }

    public function test_sin_permiso_cedis_responde_403(): void
    {
        $pedido = $this->pedido();
        $ajeno = $this->usuario([]);

        $this->actingAs($ajeno)
            ->get(route('control_pedidos.cedis.caratula_municipal', $pedido).'?variante=sin_identificacion')
            ->assertForbidden();
    }

    public function test_sin_visibilidad_cedis_responde_403(): void
    {
        $pedido = $this->pedido();
        $sinAlcance = $this->usuario(['control_pedidos.cedis']);

        $this->actingAs($sinAlcance)
            ->get(route('control_pedidos.cedis.caratula_municipal', $pedido).'?variante=sin_identificacion')
            ->assertForbidden();
    }

    public function test_no_exige_permiso_de_generar_caratula_de_tienda(): void
    {
        $pedido = $this->pedido([], ['sucursal_destino_id' => Sucursal::factory()->create(['activo' => true])->id]);

        $this->actingAs($this->cedis)
            ->get(route('control_pedidos.cedis.caratula_municipal', $pedido).'?variante=sin_identificacion')
            ->assertOk();
    }
}
