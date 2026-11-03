<?php

namespace Tests\Feature\Mobile;

use App\Models\Activo;
use App\Models\ActivoAsignacion;
use App\Models\Almacen;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\CatalogoPuesto;
use App\Models\CatalogoTipoActivo;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Conversacion;
use App\Models\ConversacionParticipante;
use App\Models\Departamento;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Models\RhColaborador;
use App\Models\SolicitudTag;
use App\Models\SolicitudTraspaso;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Support\RefreshDatabaseSafe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileOperacionAmpliadaTest extends TestCase
{
    use RefreshDatabaseSafe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ThrottleRequests::class]);
    }

    public function test_pedidos_respeta_el_alcance_de_la_persona_vendedora(): void
    {
        $propia = $this->usuario(['control_pedidos.ver_listado']);
        $ajena = User::factory()->create();
        $pedido = $this->pedido($propia, 'PED-PROPIO');
        $this->pedido($ajena, 'PED-AJENO');

        $this->con($propia)
            ->getJson('/api/v1/mobile/pedidos?q=PED-')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.folio', 'PED-PROPIO')
            ->assertJsonPath('data.0.cliente.numero_cliente', $pedido->cliente->numero_cliente);

        $this->con($propia)
            ->getJson('/api/v1/mobile/pedidos/'.$pedido->id)
            ->assertOk()
            ->assertJsonPath('data.folio', 'PED-PROPIO')
            ->assertJsonPath('data.estatus.fase_ciclo', CatalogoEstatusPedido::FASE_BORRADOR);

        $ajenoId = PedidoBma::query()->where('folio', 'PED-AJENO')->value('id');
        $this->con($propia)
            ->getJson('/api/v1/mobile/pedidos/'.$ajenoId)
            ->assertForbidden();

        $this->con($this->usuario([]))
            ->getJson('/api/v1/mobile/pedidos')
            ->assertForbidden();
    }

    public function test_rh_lista_colaboradores_solo_con_permiso(): void
    {
        $usuario = $this->usuario(['rh.ver']);
        $departamento = Departamento::query()->create(['nombre' => 'Operación '.Str::random(4), 'activo' => true]);
        $puesto = CatalogoPuesto::query()->create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'PUE-'.Str::random(4),
            'nombre' => 'Auxiliar '.Str::random(4),
            'activo' => true,
        ]);
        RhColaborador::query()->create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'COL-100',
            'nombre' => 'Alex',
            'apellido_paterno' => 'Ruiz',
            'departamento_id' => $departamento->id,
            'catalogo_puesto_id' => $puesto->id,
            'salario_base' => 12000,
            'activo' => true,
            'registrado_por_id' => $usuario->id,
        ]);

        $this->con($usuario)
            ->getJson('/api/v1/mobile/rh/colaboradores?q=Alex')
            ->assertOk()
            ->assertJsonPath('data.0.folio', 'COL-100')
            ->assertJsonPath('data.0.nombre_completo', 'Alex Ruiz')
            ->assertJsonPath('data.0.compensacion.salario_base', '12000.00');

        $this->con($this->usuario([]))
            ->getJson('/api/v1/mobile/rh/colaboradores')
            ->assertForbidden();

        $this->con($usuario)
            ->getJson('/api/v1/mobile/rh/horas-extra')
            ->assertForbidden();
    }

    public function test_inventario_queda_en_las_sucursales_operables(): void
    {
        $usuario = $this->usuario(['almacenes.inventarios.ver']);
        $propia = $this->almacenDe($usuario, 'PRO');
        $ajena = Almacen::query()->create([
            'codigo' => 'AJE',
            'nombre' => 'Almacén ajeno',
            'activo' => true,
            'sucursal_id' => Sucursal::factory()->create()->id,
        ]);
        $visible = $this->producto('SKU-PRO', 'Aroma visible');
        $oculto = $this->producto('SKU-AJE', 'Aroma oculto');
        Inventario::query()->create([
            'producto_id' => $visible->id,
            'almacen_id' => $propia->id,
            'existencia' => 8,
            'apartado' => 1,
        ]);
        Inventario::query()->create([
            'producto_id' => $oculto->id,
            'almacen_id' => $ajena->id,
            'existencia' => 4,
            'apartado' => 0,
        ]);
        ProductoCosto::query()->create([
            'producto_id' => $visible->id,
            'almacen_id' => $propia->id,
            'costo' => 80,
            'precio_venta' => 150,
        ]);

        $this->con($usuario)
            ->getJson('/api/v1/mobile/almacenes')
            ->assertOk()
            ->assertJsonPath('data.0.codigo', 'PRO');

        $this->con($usuario)
            ->getJson('/api/v1/mobile/inventarios?q=Aroma')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.producto.sku', 'SKU-PRO')
            ->assertJsonPath('data.0.disponible', '7.000')
            ->assertJsonPath('data.0.costo', '80.00');

        $this->con($usuario)
            ->getJson('/api/v1/mobile/almacenes/'.$ajena->id)
            ->assertNotFound();
    }

    public function test_activos_se_aislan_por_departamento_y_la_firma_queda_registrada(): void
    {
        $usuario = $this->usuario(['activos.ver']);
        $propio = Departamento::query()->create(['nombre' => 'Sistemas '.Str::random(4), 'activo' => true]);
        $ajeno = Departamento::query()->create(['nombre' => 'Ventas '.Str::random(4), 'activo' => true]);
        $usuario->departamentos()->attach($propio->id);
        $visible = $this->activo($propio, $usuario, 'ACT-1');
        $oculto = $this->activo($ajeno, $usuario, 'ACT-2');
        $asignacion = ActivoAsignacion::query()->create([
            'activo_id' => $visible->id,
            'user_id' => $usuario->id,
            'asignado_por_id' => $usuario->id,
            'fecha_inicio' => now()->toDateString(),
            'activa' => true,
            'firmado' => false,
        ]);

        $this->con($usuario)
            ->getJson('/api/v1/mobile/activos')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.folio', 'ACT-1');

        $this->con($usuario)
            ->getJson('/api/v1/mobile/activos/'.$oculto->id)
            ->assertNotFound();

        $this->con($usuario)
            ->getJson('/api/v1/mobile/activos/resolver-codigo?codigo=ACT-1')
            ->assertOk()
            ->assertJsonPath('data.id', $visible->id);

        $this->con($usuario)
            ->getJson('/api/v1/mobile/activos/asignaciones/pendientes-firma')
            ->assertOk()
            ->assertJsonPath('data.0.id', $asignacion->id)
            ->assertJsonPath('data.0.firmado', false);

        $this->con($usuario)
            ->postJson('/api/v1/mobile/activos/asignaciones/'.$asignacion->id.'/firmar', [
                'firma' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            ])
            ->assertOk()
            ->assertJsonPath('data.firmado', true)
            ->assertJsonMissingPath('data.firma_ruta');

        $this->assertTrue($asignacion->fresh()->firmado);
    }

    public function test_solicitudes_y_traspasos_no_muestran_registros_ajenos(): void
    {
        $propia = $this->usuario(['solicitudes.ver_listado', 'traspasos.ver_listado']);
        $ajena = User::factory()->create();
        $solicitud = $this->solicitud($propia);
        $this->solicitud($ajena);
        $traspaso = $this->traspaso($propia, $solicitud->cliente);
        $this->traspaso($ajena, $solicitud->cliente);

        $this->con($propia)
            ->getJson('/api/v1/mobile/solicitudes')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $solicitud->id);

        $this->con($propia)
            ->getJson('/api/v1/mobile/traspasos')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.folio', $traspaso->folio);

        $ajenaSolicitud = SolicitudTag::query()->where('vendedor_id', $ajena->id)->value('id');
        $this->con($propia)
            ->getJson('/api/v1/mobile/solicitudes/'.$ajenaSolicitud)
            ->assertNotFound();
    }

    public function test_mensajeria_solo_deja_leer_conversaciones_propias(): void
    {
        $propia = $this->usuario([]);
        $otra = User::factory()->create(['password' => 'secret123']);
        $conversacion = Conversacion::query()->create([
            'tipo' => Conversacion::TIPO_DIRECTO,
            'creado_por' => $propia->id,
        ]);
        ConversacionParticipante::query()->create([
            'conversacion_id' => $conversacion->id,
            'user_id' => $propia->id,
            'rol' => ConversacionParticipante::ROL_ADMIN,
        ]);

        $this->con($propia)
            ->getJson('/api/v1/mobile/mensajeria/conversaciones')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversacion->id);

        $this->con($propia)
            ->postJson('/api/v1/mobile/mensajeria/conversaciones/'.$conversacion->id.'/mensajes', [
                'contenido' => 'Hola desde el móvil',
            ])
            ->assertCreated()
            ->assertJsonPath('data.contenido', 'Hola desde el móvil');

        $this->con($otra)
            ->getJson('/api/v1/mobile/mensajeria/conversaciones/'.$conversacion->id.'/mensajes')
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $permisos
     */
    private function usuario(array $permisos): User
    {
        $usuario = User::factory()->create(['password' => 'secret123']);
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        if ($permisos !== []) {
            $usuario->givePermissionTo($permisos);
        }

        return $usuario;
    }

    private function con(User $usuario): static
    {
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/v1/mobile/login', [
            'login' => $usuario->email,
            'password' => 'secret123',
            'device_uuid' => (string) Str::uuid(),
        ])->assertOk()->json('access_token');
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->withHeader('Accept', 'application/json');
    }

    private function pedido(User $vendedor, string $folio): PedidoBma
    {
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista '.Str::random(4),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cliente = Cliente::query()->create([
            'numero_cliente' => (string) random_int(100000, 999999),
            'nombre' => 'Cliente '.$folio,
            'lista_actual_id' => $listaId,
            'vendedor_id' => $vendedor->id,
        ]);
        $origen = CatalogoOrigenPedido::query()->create([
            'nombre' => 'Mostrador '.Str::random(4),
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $estatus = CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR],
            [
                'codigo_interno' => 'BORRADOR',
                'nombre_visual' => 'Borrador',
                'activo' => true,
                'orden' => 1,
            ],
        );

        return PedidoBma::query()->create([
            'folio' => $folio,
            'fecha' => now()->toDateString(),
            'vendedor_id' => $vendedor->id,
            'cliente_id' => $cliente->id,
            'origen_id' => $origen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
            'prioridad_md' => false,
        ]);
    }

    private function almacenDe(User $usuario, string $codigo): Almacen
    {
        $sucursal = Sucursal::factory()->create();
        $usuario->concederAccesoSucursal($sucursal, true);

        return Almacen::query()->create([
            'codigo' => $codigo,
            'nombre' => 'Almacén '.$codigo,
            'activo' => true,
            'sucursal_id' => $sucursal->id,
        ]);
    }

    private function producto(string $sku, string $descripcion): Producto
    {
        return Producto::query()->create([
            'uuid' => (string) Str::uuid(),
            'sku' => $sku,
            'descripcion' => $descripcion,
            'folio' => random_int(100000, 999999),
            'activo' => true,
        ]);
    }

    private function activo(Departamento $departamento, User $usuario, string $folio): Activo
    {
        $tipo = CatalogoTipoActivo::query()->create([
            'nombre' => 'Equipo '.Str::random(4),
            'slug' => 'equipo-'.Str::lower(Str::random(6)),
            'categoria' => 'tecnologico',
            'activo' => true,
        ]);

        return Activo::query()->create([
            'folio' => $folio,
            'consulta_token' => (string) Str::uuid(),
            'catalogo_tipo_activo_id' => $tipo->id,
            'departamento_id' => $departamento->id,
            'nombre' => 'Laptop '.$folio,
            'estado' => 'disponible',
            'registrado_por_id' => $usuario->id,
        ]);
    }

    private function solicitud(User $vendedor): SolicitudTag
    {
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista '.Str::random(4),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cliente = Cliente::query()->create([
            'numero_cliente' => (string) random_int(100000, 999999),
            'nombre' => 'Cliente solicitud '.Str::random(3),
            'lista_actual_id' => $listaId,
        ]);
        $proceso = CatalogoProceso::query()->create([
            'nombre' => 'Tag '.Str::random(4),
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        $estado = CatalogoEstadoSolicitud::query()->create([
            'nombre' => 'Pendiente '.Str::random(4),
            'activo' => true,
        ]);

        return SolicitudTag::query()->create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'catalogo_proceso_id' => $proceso->id,
            'catalogo_estado_solicitud_id' => $estado->id,
            'monto_cotizado' => 250,
        ]);
    }

    private function traspaso(User $vendedor, Cliente $cliente): SolicitudTraspaso
    {
        $almacen = Almacen::query()->create([
            'codigo' => 'TR-'.Str::upper(Str::random(4)),
            'nombre' => 'Origen traspaso',
            'activo' => true,
        ]);
        $estado = CatalogoEstadoSolicitud::query()->create([
            'nombre' => 'Estado '.Str::random(4),
            'activo' => true,
        ]);

        return SolicitudTraspaso::query()->create([
            'folio' => 'TRA-'.Str::upper(Str::random(6)),
            'vendedor_id' => $vendedor->id,
            'cliente_id' => $cliente->id,
            'almacen_origen_id' => $almacen->id,
            'catalogo_estado_solicitud_id' => $estado->id,
            'total_piezas' => 2,
        ]);
    }

}
