<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\Departamento;
use App\Models\Producto;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\InformarEntregaResguardoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\CrearResguardoManualPdvService;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\BultosEsperadosResguardoPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistroManualResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    private Cliente $cliente;

    private Departamento $origen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        $this->cliente = $this->crearCliente();
        $this->origen = Departamento::query()->firstOrCreate(
            ['nombre' => 'Aromas'],
            ['activo' => true, 'visible_origen_resguardo_pdv' => true]
        );
        Departamento::query()->firstOrCreate(
            ['nombre' => 'Bellaroma'],
            ['activo' => true, 'visible_origen_resguardo_pdv' => true]
        );
    }

    public function test_flag_apagado_niega_el_alta(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('punto_venta.resguardos.store'), $this->payloadAlta(), ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertSame(0, ResguardoPdv::query()->count());
    }

    public function test_sin_permiso_de_gerente_niega_el_alta(): void
    {
        $this->activarRegistroManual();

        $sinPermiso = User::factory()->create();
        $sinPermiso->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $sinPermiso->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->actingAs($sinPermiso)
            ->post(route('punto_venta.resguardos.store'), $this->payloadAlta(), ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_alta_manual_crea_resguardo_sin_pedido(): void
    {
        $this->activarRegistroManual();

        $this->actingAs($this->usuario)
            ->post(route('punto_venta.resguardos.store'), $this->payloadAlta([
                'envia_a_otra_persona' => true,
                'envia_otra_persona' => 'Persona autorizada',
                'observaciones' => 'Paquete histórico',
                'cantidad_piezas' => 4,
            ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('resguardo.estado', ResguardoPdv::ESTADO_PENDIENTE_RECEPCION)
            ->assertJsonPath('resguardo.snapshot_folio', 'REM-HIST-001');

        $resguardo = ResguardoPdv::query()->first();
        $this->assertNotNull($resguardo);
        $this->assertNull($resguardo->pedido_bma_id);
        $this->assertSame($this->cliente->id, $resguardo->cliente_id);
        $this->assertSame($this->sucursal->id, $resguardo->sucursal_id);
        $this->assertSame(2, $resguardo->cantidad_bultos_esperada);
        $this->assertSame(CrearResguardoManualPdvService::HANDOFF, $resguardo->snapshot_json['handoff'] ?? null);
        $this->assertTrue((bool) ($resguardo->snapshot_json['envia_a_otra_persona'] ?? false));
        $this->assertSame('Persona autorizada', $resguardo->snapshot_json['envia_otra_persona'] ?? null);
        $this->assertSame('Aromas', $resguardo->snapshot_json['origen_nombre'] ?? null);
        $this->assertSame(trim((string) $this->usuario->name), $resguardo->snapshot_json['registrado_por_nombre'] ?? null);
        $this->assertSame($this->origen->id, (int) ($resguardo->snapshot_json['departamento_id'] ?? 0));
        $this->assertSame('Paquete histórico', $resguardo->snapshot_json['observaciones'] ?? null);
        $this->assertSame(4, (int) ($resguardo->snapshot_json['cantidad_piezas'] ?? 0));
        $this->assertNotNull($resguardo->salida_cedis_at);

        $evento = ResguardoPdvEvento::query()->where('resguardo_id', $resguardo->id)->first();
        $this->assertSame(ResguardoPdvEvento::TIPO_REGISTRO_MANUAL_CREADO, $evento?->tipo_evento);
        $this->assertSame('pdv:man:test-1', $evento?->idempotency_key);

        $this->assertSame(2, ResguardoPdvEvidencia::query()->where('resguardo_id', $resguardo->id)->count());
        $this->assertTrue(
            ResguardoPdvEvidencia::query()
                ->where('resguardo_id', $resguardo->id)
                ->get()
                ->contains(fn (ResguardoPdvEvidencia $evidencia) => ($evidencia->metadata_json['uso'] ?? null) === ResguardoPdvEvidencia::USO_TICKET)
        );

        $ticket = ResguardoPdvEvidencia::query()
            ->where('resguardo_id', $resguardo->id)
            ->get()
            ->first(fn (ResguardoPdvEvidencia $evidencia) => ($evidencia->metadata_json['uso'] ?? null) === ResguardoPdvEvidencia::USO_TICKET);
        $this->assertNotNull($ticket);

        $detalle = $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.show', $resguardo))
            ->assertOk()
            ->json('resguardo.registro_manual');

        $this->assertNotNull($detalle);
        $this->assertSame('Aromas', $detalle['departamento_nombre'] ?? null);
        $this->assertSame(trim((string) $this->usuario->name), $detalle['registrado_por'] ?? null);
        $urlTicket = collect($detalle['evidencias'] ?? [])
            ->firstWhere('uso', ResguardoPdvEvidencia::USO_TICKET)['ruta_publica'] ?? null;
        $this->assertSame(
            route('punto_venta.resguardos.evidencias.show', ['resguardo' => $resguardo->id, 'evidencia' => $ticket->id]),
            $urlTicket
        );

        $this->actingAs($this->usuario)
            ->get($urlTicket)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $esperados = BultosEsperadosResguardoPdv::desdeResguardo($resguardo);
        $this->assertSame(4, array_sum(array_column($esperados, 'piezas')));
    }

    public function test_alta_manual_con_piezas_suma_cantidades(): void
    {
        $this->activarRegistroManual();
        $producto = $this->crearProducto();

        $this->actingAs($this->usuario)
            ->post(route('punto_venta.resguardos.store'), $this->payloadAlta([
                'idempotency_key' => 'pdv:man:piezas-1',
                'cantidad_piezas' => 99,
                'piezas' => [
                    ['producto_id' => $producto->id, 'cantidad' => 3],
                ],
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $resguardo = ResguardoPdv::query()->first();
        $this->assertSame(3, (int) ($resguardo?->snapshot_json['cantidad_piezas'] ?? 0));
        $this->assertSame($producto->id, (int) ($resguardo?->snapshot_json['piezas'][0]['producto_id'] ?? 0));
        $this->assertSame($producto->sku, $resguardo?->snapshot_json['piezas'][0]['sku'] ?? null);
    }

    public function test_reintento_idempotente_no_duplica(): void
    {
        $this->activarRegistroManual();
        $payload = $this->payloadAlta();

        $this->actingAs($this->usuario)
            ->post(route('punto_venta.resguardos.store'), $payload, ['Accept' => 'application/json'])
            ->assertCreated();

        $this->actingAs($this->usuario)
            ->post(route('punto_venta.resguardos.store'), $payload, ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame(1, ResguardoPdv::query()->count());
        $this->assertSame(1, ResguardoPdvEvento::query()->count());
        $this->assertSame(2, ResguardoPdvEvidencia::query()->count());
    }

    public function test_folio_abierto_duplicado_se_rechaza(): void
    {
        $this->activarRegistroManual();

        $this->actingAs($this->usuario)
            ->post(route('punto_venta.resguardos.store'), $this->payloadAlta(), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->actingAs($this->usuario)
            ->post(route('punto_venta.resguardos.store'), $this->payloadAlta([
                'idempotency_key' => 'pdv:man:test-2',
            ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folio']);
    }

    public function test_entrega_sin_pedido_omite_integracion_control_pedidos(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'pedido_bma_id' => null,
            'cliente_id' => $this->cliente->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'snapshot_folio' => 'REM-MAN-ENT',
            'version' => 2,
        ]);

        $entrega = ResguardoPdvEntrega::query()->create([
            'resguardo_id' => $resguardo->id,
            'pedido_bma_id' => null,
            'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Persona titular',
            'entregado_por_id' => $this->usuario->id,
            'entregado_at' => now(),
            'snapshot_json' => ['integracion_cp' => ['estado' => 'pendiente', 'intentos' => 0]],
            'idempotency_key' => 'pdv:ent:manual-1',
            'version' => 1,
        ]);

        $resultado = app(InformarEntregaResguardoPdvService::class)->ejecutar(
            $resguardo->fresh(),
            $entrega->fresh(),
            $this->usuario->id
        );

        $this->assertTrue($resultado);
        $this->assertSame('omitida', $entrega->fresh()->snapshot_json['integracion_cp']['estado'] ?? null);
        $this->assertNull($entrega->fresh()->snapshot_json['integracion_cp']['ultimo_error'] ?? null);
    }

    public function test_bandeja_expone_flag_de_registro_manual(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.index', [
                'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Resguardos/Index', false)
                ->where('operativa.registro_manual', false)
                ->where('permisos.recibir', true)
                ->where('catalogos.origenes_pedido', []));

        $this->activarRegistroManual();

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('operativa.registro_manual', true)
                ->has('catalogos.origenes_pedido'));
    }

    public function test_busqueda_de_productos_requiere_flag_y_permiso(): void
    {
        $producto = $this->crearProducto(['sku' => 'SKU-MAN-99', 'descripcion' => 'Reloj evidenciado']);

        $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.productos.buscar', ['q' => 'Reloj']))
            ->assertForbidden();

        $this->activarRegistroManual();

        $this->actingAs($this->usuario)
            ->getJson(route('punto_venta.resguardos.productos.buscar', ['q' => 'Reloj']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $producto->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payloadAlta(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => 'pdv:man:test-1',
            'cliente_id' => $this->cliente->id,
            'folio' => 'REM-HIST-001',
            'origen_id' => $this->origen->id,
            'cantidad_bultos_esperada' => 2,
            'envia_a_otra_persona' => false,
            'archivo_ticket' => UploadedFile::fake()->image('ticket.jpg'),
            'foto_paquete' => UploadedFile::fake()->image('paquete.jpg'),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function crearProducto(array $attrs = []): Producto
    {
        static $folio = 910000;
        $folio++;

        return Producto::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU'.$folio,
            'descripcion' => 'Producto resguardo '.$folio,
            'activo' => true,
        ], $attrs));
    }

    private function crearCliente(): Cliente
    {
        $listaId = CatalogoListaDescuento::query()->value('id');
        if ($listaId === null) {
            $listaId = CatalogoListaDescuento::query()->create([
                'nombre' => 'PUBLICO GENERAL',
                'activo' => true,
            ])->id;
        }

        return Cliente::query()->create([
            'numero_cliente' => '91001',
            'nombre' => 'Cliente resguardo histórico',
            'lista_actual_id' => $listaId,
            'monto_venta_actual' => 0,
        ]);
    }

    private function activarRegistroManual(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => RegistroManualResguardoPdvConfig::CLAVE],
            ['valor' => '1', 'tipo' => 'boolean', 'grupo' => 'PuntoVenta']
        );
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
