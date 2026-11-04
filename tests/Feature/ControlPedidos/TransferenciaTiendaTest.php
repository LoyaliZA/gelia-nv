<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Departamento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\SeguimientoRecoleccionT02Service;
use App\Services\ControlPedidos\TransferenciaTiendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TransferenciaTiendaTest extends TestCase
{
    use RefreshDatabase;

    private User $cedis;
    private User $auxiliar;
    private User $vendedora;
    private Almacen $destino;
    private PedidoBma $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('public'); Storage::fake('local'); Notification::fake();
        $depto = Departamento::query()->create(['nombre' => 'Tienda T02', 'activo' => true]);
        $this->cedis = User::factory()->create(['departamento_id' => $depto->id]);
        $this->vendedora = User::factory()->create(['departamento_id' => $depto->id]);
        $this->auxiliar = User::factory()->create(['departamento_id' => $depto->id]);
        foreach (['control_pedidos.cedis', 'control_pedidos.cedis.empacar', 'control_pedidos.cedis.enviar'] as $permiso) $this->cedis->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        foreach (['control_pedidos.tienda.responder', 'pdv.resguardos.entregar', 'pdv.acceder'] as $permiso) $this->auxiliar->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $sucursal = Sucursal::factory()->create(['activo' => true]);
        $this->auxiliar->concederAccesoSucursal($sucursal, true);
        $this->destino = Almacen::query()->create(['codigo' => 'PDV', 'nombre' => 'Custodia destino', 'sucursal_id' => $sucursal->id, 'activo' => true]);
        $origen = Almacen::query()->create(['codigo' => 'CED-T02', 'nombre' => 'CEDIS origen', 'activo' => true]);
        $origenPedido = CatalogoOrigenPedido::query()->create(['codigo' => 'ORI-T02', 'nombre' => 'Tienda', 'requiere_logistica' => false, 'activo' => true]);
        foreach (['EN_CEDIS', 'PENDIENTE_DE_ENVIO', 'ENVIADO', 'ENTREGADO', 'PENDIENTE_AUXILIAR'] as $indice => $fase) CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => $fase], ['codigo_interno' => $fase, 'nombre_visual' => $fase, 'color_hex' => '#999999', 'orden' => $indice, 'activo' => true]);
        CatalogoModalidadPreparacionPedido::query()->firstOrCreate(['codigo' => 'RECOGE_TIENDA_TRANSFERENCIA'], ['nombre' => 'Transferencia', 'activo' => true]);
        $lista = DB::table('catalogo_listas_descuento')->insertGetId(['nombre' => 'Lista T02', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $cliente = Cliente::query()->create(['numero_cliente' => 'T02001', 'nombre' => 'Cliente T02', 'lista_actual_id' => $lista, 'vendedor_id' => $this->vendedora->id]);
        $this->pedido = PedidoBma::query()->create(['folio' => 'T02-PRUEBA', 'fecha' => now()->toDateString(), 'vendedor_id' => $this->vendedora->id,
            'cliente_id' => $cliente->id, 'departamento_operacion_id' => $depto->id, 'origen_id' => $origenPedido->id,
            'almacen_id' => $origen->id, 'sucursal_destino_id' => $sucursal->id,
            'catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('EN_CEDIS')->id,
            'consulta_cerrada_at' => now(), 'pago_validado_at' => now(), 'numero_remision' => 'REM-T02',
            'total_mercancia' => 100, 'total_a_cobrar' => 100, 'costo_envio' => 0, 'saldo_a_favor' => 0, 'costo_seguro' => 0,
            'solicitud_preparacion_json' => ['version' => 1, 'modalidad_entrega' => 'tienda', 'area_revision' => 'control_pedidos',
                'codigo_modalidad' => 'RECOGE_TIENDA_TRANSFERENCIA', 'cobertura_tienda' => ['tipo' => 'transferencia'], 'atencion_tienda' => 'no']]);
        $this->pedido->documentos()->create(['tipo' => PedidoBmaDocumento::TIPO_REMISION, 'ruta_archivo' => 'remision.pdf', 'nombre_original' => 'remision.pdf', 'mime_type' => 'application/pdf', 'tamano_bytes' => 100, 'orden' => 1, 'activo' => true]);
    }

    private function operar(string $accion, User $actor, array $datos = []): array
    {
        $version = $this->pedido->fresh()->solicitud_preparacion_json['transferencia_tienda']['version'] ?? 0;
        return app(TransferenciaTiendaService::class)->ejecutar($this->pedido, $actor, $accion, array_merge(['version' => $version], $datos));
    }

    private function preparar(): array
    {
        return $this->operar('preparar', $this->cedis, ['bultos' => [['foto_bulto' => UploadedFile::fake()->image('bolsa.jpg')]], 'observaciones' => 'Pedido completo, bolsa identificada.']);
    }

    public function test_recorrido_separa_preparacion_salida_recepcion_custodia_y_entrega(): void
    {
        $origen = $this->pedido->almacen_id;
        $this->assertSame('preparada', $this->preparar()['estado']);
        $this->assertSame(0, ResguardoPdv::query()->count());
        $this->assertSame('en_traslado', $this->operar('salida', $this->cedis)['estado']);
        $this->assertNull(ResguardoPdv::query()->first()->custodia_confirmada_at);
        $this->assertSame('recibida', $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true])['estado']);
        $this->assertSame('en_custodia', $this->operar('custodia', $this->auxiliar, ['almacen_id' => $this->destino->id, 'ubicacion' => 'Recepción, anaquel B, REM-T02'])['estado']);
        $this->assertSame($origen, $this->pedido->fresh()->almacen_id);
        $this->assertSame($this->destino->id, ResguardoPdv::query()->first()->almacen_id);
        $this->assertSame('entregada', $this->operar('entregar', $this->auxiliar, ['receptor' => 'Cliente titular', 'foto' => UploadedFile::fake()->image('entrega.jpg')])['estado']);
        $this->assertSame('ENTREGADO', $this->pedido->fresh()->estatus->fase_ciclo);
        $this->assertSame('entregada', $this->operar('entregar', $this->auxiliar, ['receptor' => 'Cliente titular', 'foto' => UploadedFile::fake()->image('reintento.jpg')])['estado']);
        $this->assertSame(1, \App\Models\PuntoVenta\ResguardoPdvEntrega::query()->count());
        $this->assertSame(0, \App\Models\PuntoVenta\ResguardoPdvEvidencia::query()->where('tipo', 'firma')->count());
        $this->assertSame(1, \App\Models\PuntoVenta\ResguardoPdvEvidencia::query()->where('tipo', 'foto')->count());
        $this->assertSame(0, \App\Models\SaldosAFavor\PedidoBmaPago::query()->count());
    }

    public function test_no_prepara_sin_ingreso_verificado(): void
    {
        $this->pedido->update(['pago_validado_at' => null]);
        $this->expectException(ValidationException::class);
        $this->preparar();
    }

    public function test_no_recibe_antes_de_la_salida(): void
    {
        $this->preparar();
        $this->expectException(ValidationException::class);
        $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
    }

    public function test_otra_sucursal_no_puede_recibir(): void
    {
        $this->preparar(); $this->operar('salida', $this->cedis);
        $ajena = User::factory()->create();
        $ajena->givePermissionTo(Permission::findOrCreate('control_pedidos.tienda.responder', 'web'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->operar('recibir', $ajena, ['bolsas_verificadas' => true]);
    }

    public function test_version_obsoleta_no_registra_custodia(): void
    {
        $this->preparar(); $this->operar('salida', $this->cedis); $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
        $this->expectException(ValidationException::class);
        app(TransferenciaTiendaService::class)->ejecutar($this->pedido, $this->auxiliar, 'custodia', ['version' => 0, 'almacen_id' => $this->destino->id, 'ubicacion' => 'B']);
    }

    public function test_veinte_dias_solo_avisan_gestion_sin_liberar_pago_ni_custodia(): void
    {
        $this->preparar(); $this->operar('salida', $this->cedis); $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true]);
        $this->operar('custodia', $this->auxiliar, ['almacen_id' => $this->destino->id, 'ubicacion' => 'B']);
        $json = $this->pedido->fresh()->solicitud_preparacion_json;
        $json['remision_tienda']['emitida_at'] = now()->subDays(21)->toIso8601String();
        $this->pedido->update(['solicitud_preparacion_json' => $json]);
        $this->assertSame(1, app(SeguimientoRecoleccionT02Service::class)->ejecutar());
        $this->assertSame(0, app(SeguimientoRecoleccionT02Service::class)->ejecutar());
        $this->assertSame('pendiente_gestion', $this->pedido->fresh()->solicitud_preparacion_json['seguimiento_recoleccion']['estado']);
        $this->assertNotNull($this->pedido->fresh()->pago_validado_at);
        $this->assertNull($this->pedido->fresh()->cancelado_at);
        $this->assertSame(ResguardoPdv::ESTADO_EN_CUSTODIA, ResguardoPdv::query()->first()->estado);
        $this->assertFalse(ResguardoPdv::query()->first()->entrega_bloqueada);
    }

    public function test_desde_pdv_se_conserva_procedencia_y_se_recibe_en_la_misma_sucursal(): void
    {
        foreach (['control_pedidos.tienda.ver', 'control_pedidos.tienda.responder'] as $permiso) $this->cedis->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
        $json = $this->pedido->solicitud_preparacion_json;
        $json['area_revision'] = 'preparacion_tienda';
        $this->pedido->update(['almacen_id' => $this->destino->id, 'solicitud_preparacion_json' => $json]);
        \App\Models\ControlPedidos\PedidoBmaTareaPreparacion::query()->create(['pedido_bma_id' => $this->pedido->id,
            'catalogo_modalidad_preparacion_id' => CatalogoModalidadPreparacionPedido::query()->where('codigo', 'RECOGE_TIENDA_TRANSFERENCIA')->value('id'),
            'almacen_id' => $this->destino->id, 'estado' => 'RESPONDIDA', 'area_responsable_codigo' => 'TIENDA',
            'asignada_a_id' => $this->cedis->id, 'solicitada_por_id' => $this->vendedora->id, 'version' => 1]);
        $this->assertFalse($this->pedido->fresh()->esTrasladoInternoAPdv());
        $this->preparar();
        $this->assertSame('en_traslado', $this->operar('salida', $this->cedis)['estado']);
        $this->assertSame('recibida', $this->operar('recibir', $this->auxiliar, ['bolsas_verificadas' => true])['estado']);
        $this->assertSame($this->destino->id, $this->pedido->fresh()->almacen_id);
    }

    public function test_city_center_se_identifica_como_ramificacion_t03(): void
    {
        $this->pedido->sucursalDestino->update(['nombre' => 'City Center']);
        $resumen = app(TransferenciaTiendaService::class)->resumen($this->pedido->fresh(), $this->cedis);
        $this->assertSame('T-03', $resumen['derivacion']);
        $this->assertFalse($resumen['puede_preparar']);
    }

    public function test_la_emision_de_remision_se_conserva_al_sustituir_pdf(): void
    {
        $this->pedido->update(['catalogo_estatus_pedido_id' => CatalogoEstatusPedido::porFase('PENDIENTE_AUXILIAR')->id]);
        $service = app(\App\Services\ControlPedidos\GestionarRemisionPedidoBmaService::class);
        $service->subir($this->pedido, UploadedFile::fake()->create('primera.pdf', 10, 'application/pdf'), $this->auxiliar->id, now()->subDays(3)->toDateString());
        $fecha = $this->pedido->fresh()->solicitud_preparacion_json['remision_tienda']['emitida_at'];
        $service->subir($this->pedido->fresh(), UploadedFile::fake()->create('sustituta.pdf', 10, 'application/pdf'), $this->auxiliar->id, now()->toDateString());
        $this->assertSame($fecha, $this->pedido->fresh()->solicitud_preparacion_json['remision_tienda']['emitida_at']);
    }
}
