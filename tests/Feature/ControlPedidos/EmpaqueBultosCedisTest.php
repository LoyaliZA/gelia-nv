<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaBultoEmpaque;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\ControlPedidos\RevertirEmpacadoPedidoBmaService;
use App\Services\PuntoVenta\Resguardos\ConsultaBandejasResguardoPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\CantidadBultosEsperadaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorBultosEmpaqueCedisPdv;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmpaqueBultosCedisTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->usuario = User::factory()->create();
        $this->seedCatalogosMinimos();
        $this->sucursal = Sucursal::factory()->create(['activo' => true]);
        Permission::findOrCreate('control_pedidos.cedis');
        $this->usuario->givePermissionTo('control_pedidos.cedis');
    }

    public function test_mostrador_sin_bultos_no_empaca(): void
    {
        $pedido = $this->crearPedidoMostradorEnCedis();

        $this->expectException(\InvalidArgumentException::class);
        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'complementos.estatus']),
            $this->usuario->id,
            []
        );
    }

    public function test_empacar_mostrador_con_dos_bultos_persiste_evidencias(): void
    {
        $pedido = $this->crearPedidoMostradorEnCedis();

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'complementos.estatus']),
            $this->usuario->id,
            $this->payloadBultos($pedido->id, 2)
        );

        $pedido = $pedido->fresh(['bultosEmpaque.documentos']);
        $this->assertSame(2, $pedido->bultosEmpaque->count());
        $this->assertSame(2, (int) $pedido->numero_cajas);
        $this->assertSame(4, PedidoBmaDocumento::query()
            ->where('pedido_bma_id', $pedido->id)
            ->whereIn('tipo', [
                PedidoBmaDocumento::TIPO_EVIDENCIA_BULTO_EMPAQUE,
                PedidoBmaDocumento::TIPO_EVIDENCIA_TICKET_BULTO_EMPAQUE,
            ])
            ->count());
        $this->assertSame(2, CantidadBultosEsperadaResguardoPdv::desdePedido($pedido));
        $this->assertNotNull($pedido->empacado_at);
    }

    public function test_foraneo_empaca_sin_bultos(): void
    {
        $pedido = $this->crearPedidoForaneoEnCedis();

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'complementos.estatus']),
            $this->usuario->id,
            []
        );

        $pedido = $pedido->fresh();
        $this->assertNotNull($pedido->empacado_at);
        $this->assertSame(0, PedidoBmaBultoEmpaque::query()->where('pedido_bma_id', $pedido->id)->count());
    }

    public function test_revertir_empaque_elimina_bultos_y_archivos(): void
    {
        $pedido = $this->crearPedidoMostradorEnCedis();

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'complementos.estatus']),
            $this->usuario->id,
            $this->payloadBultos($pedido->id, 1)
        );

        $ruta = PedidoBmaDocumento::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_BULTO_EMPAQUE)
            ->value('ruta_archivo');
        $this->assertNotNull($ruta);
        Storage::disk('public')->assertExists($ruta);

        app(RevertirEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'bultosEmpaque']),
            $this->usuario->id
        );

        $this->assertSame(0, PedidoBmaBultoEmpaque::query()->where('pedido_bma_id', $pedido->id)->count());
        Storage::disk('public')->assertMissing($ruta);
    }

    public function test_marcar_empacado_http_acepta_bultos_solo_como_archivos(): void
    {
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);

        $pedido = $this->crearPedidoMostradorEnCedis();
        $bultos = $this->payloadBultos($pedido->id, 1);

        $this->actingAs($this->usuario)
            ->post(route('control_pedidos.cedis.marcar_empacado', $pedido->id), [
                'bultos_por_pedido' => $bultos,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $pedido = $pedido->fresh(['bultosEmpaque']);
        $this->assertSame(1, $pedido->bultosEmpaque->count());
        $this->assertNotNull($pedido->empacado_at);
    }

    public function test_grupo_empaque_registra_bultos_por_pedido(): void
    {
        $principal = $this->crearPedidoMostradorEnCedis(['folio' => 'PED-GRP-P']);
        $complemento = $this->crearPedidoMostradorEnCedis([
            'folio' => 'PED-GRP-C1',
            'pedido_principal_id' => $principal->id,
        ]);

        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $principal->fresh(['estatus', 'paqueteria', 'origen', 'complementos.estatus', 'complementos.origen']),
            $this->usuario->id,
            $this->payloadBultos($principal->id, 1) + $this->payloadBultos($complemento->id, 2)
        );

        $this->assertSame(1, PedidoBmaBultoEmpaque::query()->where('pedido_bma_id', $principal->id)->count());
        $this->assertSame(2, PedidoBmaBultoEmpaque::query()->where('pedido_bma_id', $complemento->id)->count());
    }

    public function test_resguardo_por_recibir_incluye_bultos_empaque_cedis(): void
    {
        $this->activarModuloPdv();
        Permission::findOrCreate(PuntoVentaModulo::PERMISO_ACCEDER, 'web');
        Permission::findOrCreate(PuntoVentaModulo::PERMISO_RESGUARDOS_VER, 'web');
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
        app(\App\Services\PuntoVenta\AlcancePdv::class)->establecerSucursalActiva($this->usuario, $this->sucursal->id);

        $pedido = $this->crearPedidoMostradorEnCedis();
        app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'complementos.estatus']),
            $this->usuario->id,
            $this->payloadBultos($pedido->id, 1)
        );

        app(MarcarEnviadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'paqueteria', 'origen', 'cajas']),
            $this->usuario->id
        );

        $resguardo = ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->firstOrFail();
        $this->assertSame(1, (int) $resguardo->cantidad_bultos_esperada);

        $pedido = $pedido->fresh(['bultosEmpaque.documentos']);
        $serializado = SerializadorBultosEmpaqueCedisPdv::desdePedido($pedido);
        $this->assertCount(1, $serializado);
        $this->assertNotNull($serializado[0]['foto_bulto']['url'] ?? null);
        $this->assertNotNull($serializado[0]['foto_ticket']['url'] ?? null);

        $listado = app(ConsultaBandejasResguardoPdvService::class)->listar($this->usuario, [
            'bandeja' => 'por_recibir',
        ]);
        $item = collect($listado->items())->firstWhere('id', $resguardo->id);
        $this->assertNotNull($item);
        $this->assertCount(1, $item['bultos_empaque_cedis']);
    }

    private function activarModuloPdv(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
    }

    /**
     * @return array<int, list<array{foto_bulto: UploadedFile, foto_ticket: UploadedFile}>>
     */
    private function payloadBultos(int $pedidoId, int $cantidad): array
    {
        $filas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $filas[] = [
                'foto_bulto' => UploadedFile::fake()->image("bulto-{$pedidoId}-{$i}.jpg"),
                'foto_ticket' => UploadedFile::fake()->image("ticket-{$pedidoId}-{$i}.jpg"),
            ];
        }

        return [$pedidoId => $filas];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearPedidoMostradorEnCedis(array $overrides = []): PedidoBma
    {
        return $this->crearPedidoEnCedis(array_merge([
            'origen_id' => $this->origenMostrador()->id,
            'catalogo_paqueteria_id' => null,
            'sucursal_destino_id' => $this->sucursal->id,
            'numero_cajas' => null,
            'costo_envio' => 0,
        ], $overrides));
    }

    private function crearPedidoForaneoEnCedis(): PedidoBma
    {
        return $this->crearPedidoEnCedis([
            'origen_id' => $this->origenForaneo()->id,
            'catalogo_paqueteria_id' => $this->paqueteriaLocalRegionalId(),
            'sucursal_destino_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearPedidoEnCedis(array $overrides = []): PedidoBma
    {
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);

        $pedido = PedidoBma::query()->create(array_merge([
            'folio' => 'PED-BUL-'.uniqid(),
            'folio_remision' => 'REM-BUL-001',
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenForaneo()->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 1.5,
            'catalogo_paqueteria_id' => $this->paqueteriaLocalRegionalId(),
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'catalogo_estatus_pedido_id' => $enCedis->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ], $overrides));

        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'test/remision.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 0,
        ]);

        return $pedido;
    }

    private function origenForaneo(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::query()->firstOrCreate(
            ['nombre' => 'Envío Foráneo'],
            ['requiere_logistica' => true, 'activo' => true]
        );
    }

    private function origenMostrador(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::query()->firstOrCreate(
            ['nombre' => 'Mostrador'],
            ['requiere_logistica' => false, 'activo' => true]
        );
    }

    private function paqueteriaLocalRegionalId(): int
    {
        return (int) DB::table('catalogo_paqueterias_pedido')
            ->where('categoria', 'local_regional')
            ->value('id');
    }

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::query()->exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'AZUL ①', 'color_hex' => '#3B82F6', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 2],
                ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'AMARILLO', 'color_hex' => '#EAB308', 'fase_ciclo' => 'EN_CEDIS', 'orden' => 3],
                ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'color_hex' => '#A855F7', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 7],
                ['codigo_interno' => 'PENDIENTE_ENVIO', 'nombre_visual' => 'Pendiente de envío', 'color_hex' => '#0EA5E9', 'fase_ciclo' => 'PENDIENTE_DE_ENVIO', 'orden' => 10],
                ['codigo_interno' => 'ENTREGADO', 'nombre_visual' => 'Entregado', 'color_hex' => '#10B981', 'fase_ciclo' => 'ENTREGADO', 'orden' => 8],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
            ] as $row) {
                CatalogoEstatusPedido::query()->create(array_merge($row, ['activo' => true]));
            }
        }

        $this->origenMostrador();
        $this->origenForaneo();

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

        if (! DB::table('catalogo_paqueterias_pedido')->where('categoria', 'local_regional')->exists()) {
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

        if (! DB::table('almacenes')->exists()) {
            DB::table('almacenes')->insert([
                'codigo' => 'VTA',
                'nombre' => 'CEDIS',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
