<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\ConfirmarDevolucionAnaquelService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\ResponderPesajePedidoBmaService;
use App\Services\ControlPedidos\SolicitarPesajePedidoBmaService;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use App\Services\PuntoVenta\Resguardos\CrearRecepcionEsperadaPdvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DefinicionesOperativasQ52Test extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->usuario = User::factory()->create();
        foreach ([
            CatalogoEstatusPedido::FASE_BORRADOR => ['BORRADOR_Q52', 1],
            CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE => ['PESAJE_Q52', 2],
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO => ['PESAJE_OK_Q52', 3],
            CatalogoEstatusPedido::FASE_ENVIADO => ['ENVIADO_Q52', 8],
        ] as $fase => [$codigo, $orden]) {
            CatalogoEstatusPedido::query()->firstOrCreate(
                ['fase_ciclo' => $fase],
                ['codigo_interno' => $codigo, 'nombre_visual' => $fase, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]
            );
        }
    }

    public function test_cedis_a_pdv_continua_sin_paqueteria_ni_guia_y_abre_recepcion(): void
    {
        Event::fake();
        $pedido = $this->pedidoListo(false);
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        $tipoCaja = DB::table('catalogo_tipos_caja_pedido')->insertGetId([
            'nombre' => 'Caja Q52',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pedido->update([
            'sucursal_destino_id' => $sucursal->id,
            'catalogo_paqueteria_id' => null,
            'catalogo_tipo_guia_id' => null,
            'peso_real_kg' => 1.2,
            'catalogo_tipo_caja_id' => $tipoCaja,
            'numero_cajas' => 1,
        ]);
        PedidoBmaCaja::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_tipo_caja_id' => $tipoCaja,
            'cantidad' => 1,
            'orden' => 1,
            'peso_real_kg' => 1.2,
            'peso_volumetrico_kg' => 1,
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
        ]);

        $fresco = $pedido->fresh(['almacen.tipoAlmacen', 'origen']);
        $this->assertTrue($fresco->esTrasladoInternoCedisAPdv());
        $this->assertFalse($fresco->ofreceRastreo());
        $this->assertFalse($fresco->prohibeSucursalDestino());
        $this->validar($fresco);

        $enviado = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_ENVIADO)->firstOrFail();
        $fresco->update(['catalogo_estatus_pedido_id' => $enviado->id]);
        $resguardo = app(CrearRecepcionEsperadaPdvService::class)->ejecutar($fresco->fresh(), $this->usuario->id);

        $this->assertSame(ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, $resguardo->estado);
        $this->assertSame($sucursal->id, $resguardo->sucursal_id);
        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->where('pedido_bma_id', $pedido->id)->count());
    }

    public function test_salida_pdv_con_logistica_conserva_paqueteria(): void
    {
        $pdv = Almacen::query()->create([
            'codigo' => 'PDV',
            'nombre' => 'PDV Q52',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);
        $pedido = $this->pedidoListo(true, $pdv->id);
        $pedido->update([
            'catalogo_paqueteria_id' => null,
            'peso_real_kg' => 1,
            'numero_cajas' => 1,
        ]);

        $fresco = $pedido->fresh(['almacen', 'origen']);
        $this->assertFalse($fresco->esTrasladoInternoCedisAPdv());
        $this->assertTrue($fresco->ofreceRastreo());

        try {
            $this->validar($fresco);
            $this->fail('La salida desde PDV sigue pidiendo paquetería.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('paquetería', $e->getMessage());
        }
    }

    public function test_solicitud_cedis_a_pdv_no_crea_tarea_de_tienda(): void
    {
        $pedido = $this->pedidoListo(false);
        $borrador = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_BORRADOR)->firstOrFail();
        $pedido->update([
            'sucursal_destino_id' => Sucursal::factory()->create(['activo' => true])->id,
            'catalogo_estatus_pedido_id' => $borrador->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO,
            'pesaje_respondido_at' => null,
            'consulta_cerrada_at' => null,
            'catalogo_paqueteria_id' => null,
        ]);

        app(SolicitarPesajePedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id);

        $this->assertSame(0, PedidoBmaTareaPreparacion::query()->where('pedido_bma_id', $pedido->id)->count());
        $this->assertSame(PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE, $pedido->fresh()->estatus_envio);
    }

    public function test_respuesta_local_sin_peso_conserva_piezas_y_evidencia(): void
    {
        Storage::fake('public');
        $paq = CatalogoPaqueteriaPedido::query()->create([
            'nombre' => 'Local Q52 '.uniqid(),
            'categoria' => CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL,
            'requiere_peso' => false,
            'requiere_caja' => false,
            'activo' => true,
        ]);
        $pedido = $this->pedidoListo(false);
        $pendiente = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE)->firstOrFail();
        $pedido->update([
            'catalogo_paqueteria_id' => $paq->id,
            'catalogo_estatus_pedido_id' => $pendiente->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
            'pesaje_respondido_at' => null,
            'consulta_cerrada_at' => null,
        ]);

        $respondido = app(ResponderPesajePedidoBmaService::class)->ejecutar($pedido->fresh(), $this->usuario->id, [], [
            'numero_cajas' => 2,
            'revisiones' => [[
                'descripcion_producto' => 'Pieza local',
                'estado_fisico' => 'bueno',
                'comentario' => 'Disponible',
                'client_uuid' => '22222222-2222-4222-8222-222222222222',
            ]],
            'evidencias_generales' => [UploadedFile::fake()->image('lote.jpg')],
        ]);

        $this->assertNull($respondido->peso_real_kg);
        $this->assertSame(2, $respondido->numero_cajas);
        $this->assertSame(1, $respondido->revisionesProducto()->count());
        $this->assertTrue($respondido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)->exists());
    }

    public function test_retorno_al_almacen_no_pide_anaquel_y_conserva_el_estado_historico(): void
    {
        Storage::fake('public');
        Permission::findOrCreate('control_pedidos.tienda.apartado.confirmar_devolucion', 'web');
        $this->usuario->givePermissionTo('control_pedidos.tienda.apartado.confirmar_devolucion');

        $tarea = $this->tarea();
        $cumplimiento = PedidoBmaCumplimientoFisico::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'estado' => PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE,
            'cantidad' => 1,
            'ubicacion' => null,
            'version' => 1,
        ]);

        $confirmado = app(ConfirmarDevolucionAnaquelService::class)->ejecutar(
            $tarea,
            $this->usuario,
            null,
            UploadedFile::fake()->image('retorno.jpg')
        );

        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL, $confirmado->estado);
        $this->assertSame('Devuelta al almacén', PedidoBmaCumplimientoFisico::LABELS[$confirmado->estado]);
        $this->assertNull($confirmado->ubicacion);
        $this->assertNotNull($confirmado->devuelta_at);
        $this->assertSame($this->usuario->id, $confirmado->devuelta_por_id);
        $this->assertNotSame(PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA, $confirmado->estado);
        $evento = PedidoBmaCumplimientoEvento::query()->where('pedido_bma_cumplimiento_fisico_id', $confirmado->id)->first();
        $this->assertFalse((bool) ($evento->datos['inventario_sincronizado'] ?? true));

        $historico = PedidoBmaCumplimientoFisico::query()->create([
            'pedido_bma_tarea_preparacion_id' => $this->tarea()->id,
            'estado' => PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL,
            'cantidad' => 1,
            'ubicacion' => 'Anaquel A',
            'version' => 2,
        ]);
        $otra = $historico->tarea;
        $conservado = app(ConfirmarDevolucionAnaquelService::class)->ejecutar(
            $otra,
            $this->usuario,
            null,
            UploadedFile::fake()->image('otra.jpg')
        );
        $this->assertSame('Anaquel A', $conservado->ubicacion);
        $this->assertSame(PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL, $conservado->estado);
        $this->assertSame($cumplimiento->id, $confirmado->id);
    }

    private function validar(PedidoBma $pedido): void
    {
        $validador = new class {
            use ValidacionCamposPedidoBma;

            public function check(PedidoBma $p): void
            {
                $this->validarCamposRequeridos($p);
            }
        };
        $validador->check($pedido->fresh());
    }

    private function pedidoListo(bool $salidaPdv, ?int $almacenId = null): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO)->firstOrFail();
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'Q52-'.uniqid(),
            'nombre' => 'Envío Q52 '.uniqid(),
            'requiere_logistica' => true,
            'activo' => true,
        ]);
        $almacen = $almacenId ?: Almacen::query()->create([
            'codigo' => ($salidaPdv ? 'PDV' : 'CEDIS-'.uniqid()),
            'nombre' => $salidaPdv ? 'PDV Q52' : 'CEDIS Q52',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ])->id;
        $pedido = PedidoBma::query()->create([
            'folio' => 'Q52-'.uniqid(),
            'folio_remision' => 'REM-Q52-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen,
            'cliente_id' => $this->clienteId(),
            'catalogo_estatus_pedido_id' => $estatus->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO,
            'total_mercancia' => 100,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 100,
            'aplica_seguro' => false,
            'es_resguardo' => false,
            'pesaje_respondido_at' => now(),
            'consulta_cerrada_at' => now(),
            'codigo_postal' => '86000',
            'domicilio_entrega' => 'Calle de prueba',
        ]);
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'q52/pedido.pdf',
            'nombre_original' => 'pedido.pdf',
            'mime_type' => 'application/pdf',
        ]);

        return $pedido->fresh();
    }

    private function tarea(): PedidoBmaTareaPreparacion
    {
        $pedido = $this->pedidoListo(false);
        $modalidad = CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA],
            ['nombre' => 'Recoge', 'activo' => true]
        );

        return PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $pedido->almacen_id,
            'area_responsable_codigo' => 'TIENDA',
            'estado' => PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            'solicitada_por_id' => $this->usuario->id,
            'solicitada_at' => now(),
            'version' => 1,
        ]);
    }

    private function clienteId(): int
    {
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista Q52 '.uniqid(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('clientes')->insertGetId([
            'numero_cliente' => substr(uniqid(), -8),
            'nombre' => 'Cliente Q52',
            'lista_actual_id' => $listaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
