<?php

namespace Tests\Unit\Reportes;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Departamento;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Notifications\AlertaPedidoBma;
use App\Services\Reportes\PagosPedidos\ConfirmarExhibicionAdminReportePagosService;
use App\Services\Reportes\PagosPedidos\ConfirmarPedidoAdminReportePagosService;
use App\Services\Reportes\PagosPedidos\ListarReportePagosPedidosService;
use App\Services\Reportes\PagosPedidos\ReportarErrorAdminReportePagosService;
use App\Support\Reportes\AdminEstadoReportePagosPedidos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminReportePagosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Super Admin');
        Role::findOrCreate('Administrador');
    }

    private function seedMinimo(): void
    {
        $now = now();
        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente Test',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function estatus(): CatalogoEstatusPedido
    {
        return CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => 'validado'],
            [
                'codigo_interno' => 'validado_admin_test',
                'nombre_visual' => 'Validado',
                'color_hex' => '#64748B',
                'activo' => true,
                'orden' => 1,
            ]
        );
    }

    private function adminUsuario(): User
    {
        Permission::findOrCreate('reportes.pagos_pedidos.ver');

        $usuario = User::factory()->create();
        $usuario->assignRole('Administrador');
        $usuario->givePermissionTo('reportes.pagos_pedidos.ver');

        return $usuario;
    }

    private function crearPedido(User $vendedor): PedidoBma
    {
        $this->seedMinimo();

        return PedidoBma::query()->create([
            'folio' => 'ADM-'.uniqid(),
            'fecha' => now()->toDateString(),
            'cliente_id' => DB::table('clientes')->value('id'),
            'vendedor_id' => $vendedor->id,
            'catalogo_estatus_pedido_id' => $this->estatus()->id,
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 1000,
            'numero_cajas' => 1,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO,
            'es_resguardo' => false,
        ]);
    }

    private function crearCierre(
        PedidoBma $pedido,
        User $validador,
        ?int $departamentoId = null,
    ): PedidoBmaCierrePago {
        return PedidoBmaCierrePago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'version' => 1,
            'estado' => PedidoBmaCierrePago::ESTADO_VIGENTE,
            'origen' => PedidoBmaCierrePago::ORIGEN_FLUJO,
            'pedido_fecha' => $pedido->fecha,
            'validado_at' => now(),
            'validado_por_id' => $validador->id,
            'monto_venta' => 1000,
            'monto_envio' => 0,
            'monto_seguro' => 0,
            'total_pedido' => 1000,
            'saf_aplicado' => 0,
            'total_a_cobrar' => 1000,
            'pagos_validos' => 1000,
            'diferencia' => 0,
            'excedente' => 0,
            'tolerancia_aplicada' => 0.44,
            'estado_cobertura' => 'cubierto',
            'folio_snapshot' => $pedido->folio,
            'cliente_id' => $pedido->cliente_id,
            'vendedor_id' => $pedido->vendedor_id,
            'departamento_id' => $departamentoId,
        ]);
    }

    private function crearItem(
        PedidoBmaCierrePago $cierre,
        int $numeroExhibicion,
        string $adminEstado = AdminEstadoReportePagosPedidos::PENDIENTE,
    ): PedidoBmaCierrePagoItem {
        $pago = PedidoBmaPago::query()->create([
            'pedido_bma_id' => $cierre->pedido_bma_id,
            'numero_exhibicion' => $numeroExhibicion,
            'monto' => 500,
            'forma_pago' => 'transferencia',
            'estado_revision' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura' => true,
            'ruta_archivo' => 'pagos/test/voucher.jpg',
            'nombre_original' => 'voucher.jpg',
            'mime_type' => 'image/jpeg',
            'capturado_por_id' => $cierre->validado_por_id,
        ]);

        return PedidoBmaCierrePagoItem::query()->create([
            'pedido_bma_cierre_pago_id' => $cierre->id,
            'pedido_bma_pago_id' => $pago->id,
            'numero_exhibicion' => $numeroExhibicion,
            'monto_snapshot' => 500,
            'estado_revision_snapshot' => PedidoBmaPago::REVISION_VERIFICADO,
            'activo_para_cobertura_snapshot' => true,
            'ruta_archivo_snapshot' => 'pagos/test/voucher.jpg',
            'admin_estado' => $adminEstado,
        ]);
    }

    public function test_confirmar_exhibicion_cambia_admin_estado(): void
    {
        $admin = $this->adminUsuario();
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin);
        $item = $this->crearItem($cierre, 1);

        $resultado = app(ConfirmarExhibicionAdminReportePagosService::class)->ejecutar(
            $admin,
            $cierre->id,
            $item->id,
        );

        $item->refresh();
        $this->assertSame(AdminEstadoReportePagosPedidos::CONFIRMADO, $item->admin_estado);
        $this->assertSame($admin->id, $item->admin_confirmado_por_id);
        $this->assertNotNull($item->admin_confirmado_at);
        $this->assertSame(AdminEstadoReportePagosPedidos::CONFIRMADO, $resultado['item']['admin_estado']);
    }

    public function test_confirmar_exhibicion_es_idempotente(): void
    {
        $admin = $this->adminUsuario();
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin);
        $item = $this->crearItem($cierre, 1, AdminEstadoReportePagosPedidos::CONFIRMADO);
        $item->update([
            'admin_confirmado_por_id' => $admin->id,
            'admin_confirmado_at' => now()->subHour(),
        ]);

        $servicio = app(ConfirmarExhibicionAdminReportePagosService::class);
        $resultado = $servicio->ejecutar($admin, $cierre->id, $item->id);

        $this->assertSame(AdminEstadoReportePagosPedidos::CONFIRMADO, $resultado['item']['admin_estado']);
        $this->assertSame(1, $item->fresh()->admin_confirmado_por_id);
    }

    public function test_confirmar_pedido_solo_pendientes(): void
    {
        $admin = $this->adminUsuario();
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin);
        $confirmado = $this->crearItem($cierre, 1, AdminEstadoReportePagosPedidos::CONFIRMADO);
        $confirmado->update(['admin_confirmado_por_id' => $admin->id, 'admin_confirmado_at' => now()]);
        $pendiente = $this->crearItem($cierre, 2);

        app(ConfirmarPedidoAdminReportePagosService::class)->ejecutar($admin, $cierre->id);

        $this->assertSame(
            AdminEstadoReportePagosPedidos::CONFIRMADO,
            $pendiente->fresh()->admin_estado,
        );
        $this->assertSame(
            AdminEstadoReportePagosPedidos::CONFIRMADO,
            $confirmado->fresh()->admin_estado,
        );
    }

    public function test_reportar_error_exige_comentario_minimo(): void
    {
        $admin = $this->adminUsuario();
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin);
        $item = $this->crearItem($cierre, 1);
        Storage::fake('public');
        $evidencia = UploadedFile::fake()->image('error.jpg');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('10 caracteres');

        app(ReportarErrorAdminReportePagosService::class)->ejecutar(
            $admin,
            $cierre->id,
            ReportarErrorAdminReportePagosService::ALCANCE_EXHIBICION,
            'corto',
            $evidencia,
            $item->id,
        );
    }

    public function test_reportar_error_exhibicion_guarda_estado_y_evidencia(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = $this->adminUsuario();
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin);
        $item = $this->crearItem($cierre, 1);
        $evidencia = UploadedFile::fake()->image('evidencia.jpg');

        app(ReportarErrorAdminReportePagosService::class)->ejecutar(
            $admin,
            $cierre->id,
            ReportarErrorAdminReportePagosService::ALCANCE_EXHIBICION,
            'El monto no coincide con el voucher adjunto.',
            $evidencia,
            $item->id,
        );

        $item->refresh();
        $this->assertSame(AdminEstadoReportePagosPedidos::CON_ERROR, $item->admin_estado);
        $this->assertSame('El monto no coincide con el voucher adjunto.', $item->admin_error_comentario);
        $this->assertNotNull($item->admin_error_evidencia_ruta);
        Storage::disk('public')->assertExists($item->admin_error_evidencia_ruta);
    }

    public function test_reportar_error_notifica_vendedora_y_auxiliar_departamento(): void
    {
        Storage::fake('public');
        Notification::fake();

        Permission::findOrCreate('control_pedidos.auditar');

        $depto = Departamento::create(['nombre' => 'Depto admin pagos '.uniqid(), 'activo' => true]);
        $vendedor = User::factory()->create(['departamento_id' => $depto->id]);
        $auxiliar = User::factory()->create(['departamento_id' => $depto->id]);
        $auxiliar->departamentos()->sync([$depto->id]);
        $auxiliar->givePermissionTo('control_pedidos.auditar');

        $admin = $this->adminUsuario();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin, $depto->id);
        $item = $this->crearItem($cierre, 1);
        $evidencia = UploadedFile::fake()->image('evidencia.jpg');

        app(ReportarErrorAdminReportePagosService::class)->ejecutar(
            $admin,
            $cierre->id,
            ReportarErrorAdminReportePagosService::ALCANCE_EXHIBICION,
            'Referencia bancaria incorrecta en el comprobante.',
            $evidencia,
            $item->id,
        );

        Notification::assertSentTo($vendedor, AlertaPedidoBma::class);
        Notification::assertSentTo($auxiliar, AlertaPedidoBma::class);
        Notification::assertNotSentTo($admin, AlertaPedidoBma::class);
    }

    public function test_resumen_pedido_segun_exhibiciones(): void
    {
        $admin = $this->adminUsuario();
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin);

        $this->assertSame(
            AdminEstadoReportePagosPedidos::PENDIENTE,
            AdminEstadoReportePagosPedidos::resumenPedido($cierre, collect()),
        );

        $pendiente = $this->crearItem($cierre, 1);
        $this->crearItem($cierre, 2);
        $items = $cierre->fresh('items')->items;
        $this->assertSame(
            AdminEstadoReportePagosPedidos::PENDIENTE,
            AdminEstadoReportePagosPedidos::resumenPedido($cierre, $items),
        );

        $pendiente->update(['admin_estado' => AdminEstadoReportePagosPedidos::CONFIRMADO]);
        $items = $cierre->fresh('items')->items;
        $this->assertSame('parcial', AdminEstadoReportePagosPedidos::resumenPedido($cierre, $items));

        $items->each(fn (PedidoBmaCierrePagoItem $item) => $item->update([
            'admin_estado' => AdminEstadoReportePagosPedidos::CONFIRMADO,
        ]));
        $items = $cierre->fresh('items')->items;
        $this->assertSame(
            AdminEstadoReportePagosPedidos::CONFIRMADO,
            AdminEstadoReportePagosPedidos::resumenPedido($cierre, $items),
        );

        $items->first()->update(['admin_estado' => AdminEstadoReportePagosPedidos::CON_ERROR]);
        $items = $cierre->fresh('items')->items;
        $this->assertSame(
            AdminEstadoReportePagosPedidos::CON_ERROR,
            AdminEstadoReportePagosPedidos::resumenPedido($cierre, $items),
        );
    }

    public function test_conteo_exhibiciones_admin_en_payload_cierre(): void
    {
        $admin = $this->adminUsuario();
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin);
        $this->crearItem($cierre, 1, AdminEstadoReportePagosPedidos::CONFIRMADO);
        $this->crearItem($cierre, 2);
        $this->crearItem($cierre, 3, AdminEstadoReportePagosPedidos::CON_ERROR);

        $payload = AdminEstadoReportePagosPedidos::payloadCierre($cierre->fresh('items'));

        $this->assertSame(3, $payload['admin_exhibiciones_total']);
        $this->assertSame(2, $payload['admin_exhibiciones_revisadas']);
        $this->assertSame(1, $payload['admin_exhibiciones_pendientes']);
        $this->assertSame(AdminEstadoReportePagosPedidos::CON_ERROR, $payload['admin_resumen']);
        $this->assertSame('Pedido con error', $payload['admin_resumen_label']);
    }

    public function test_metadata_revision_cierre_confirmado(): void
    {
        $admin = $this->adminUsuario();
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin);
        $item = $this->crearItem($cierre, 1, AdminEstadoReportePagosPedidos::CONFIRMADO);
        $item->update([
            'admin_confirmado_por_id' => $admin->id,
            'admin_confirmado_at' => now(),
        ]);

        $payload = AdminEstadoReportePagosPedidos::payloadCierre($cierre->fresh([
            'items.adminConfirmadoPor',
        ]));

        $this->assertSame(AdminEstadoReportePagosPedidos::CONFIRMADO, $payload['admin_resumen']);
        $this->assertSame($admin->id, $payload['admin_revisado_por']['id']);
        $this->assertNotNull($payload['admin_revisado_at']);
    }

    public function test_metadata_revision_cierre_error_pedido(): void
    {
        $admin = $this->adminUsuario();
        $vendedor = User::factory()->create();
        $pedido = $this->crearPedido($vendedor);
        $cierre = $this->crearCierre($pedido, $admin);
        $cierre->update([
            'admin_pedido_error_reportado_por_id' => $admin->id,
            'admin_pedido_error_reportado_at' => now(),
            'admin_pedido_error_comentario' => 'Error de prueba',
        ]);

        $payload = AdminEstadoReportePagosPedidos::payloadCierre($cierre->fresh([
            'adminPedidoErrorReportadoPor',
            'items',
        ]));

        $this->assertSame(AdminEstadoReportePagosPedidos::CON_ERROR, $payload['admin_resumen']);
        $this->assertSame($admin->id, $payload['admin_revisado_por']['id']);
        $this->assertNotNull($payload['admin_revisado_at']);
    }

    public function test_filtro_pendiente_excluye_confirmados_y_errores(): void
    {
        Permission::findOrCreate('reportes.pagos_pedidos.ver');
        $admin = $this->adminUsuario();

        $vendedor = User::factory()->create();
        $pedidoPendiente = $this->crearPedido($vendedor);
        $cierrePendiente = $this->crearCierre($pedidoPendiente, $admin);
        $this->crearItem($cierrePendiente, 1);

        $pedidoConfirmado = $this->crearPedido($vendedor);
        $cierreConfirmado = $this->crearCierre($pedidoConfirmado, $admin);
        $this->crearItem($cierreConfirmado, 1, AdminEstadoReportePagosPedidos::CONFIRMADO);

        $pedidoError = $this->crearPedido($vendedor);
        $cierreError = $this->crearCierre($pedidoError, $admin);
        $this->crearItem($cierreError, 1, AdminEstadoReportePagosPedidos::CON_ERROR);

        $resultado = app(ListarReportePagosPedidosService::class)->ejecutar($admin, [
            'estado_cierre' => 'vigente',
            'tipo_reporte' => 'pedido',
            'estado_admin' => AdminEstadoReportePagosPedidos::PENDIENTE,
            'page' => 1,
        ]);

        $folios = collect($resultado['grupos'])
            ->flatMap(fn (array $grupo) => collect($grupo['pedidos'])->pluck('folio'))
            ->values()
            ->all();

        $this->assertContains($pedidoPendiente->folio, $folios);
        $this->assertNotContains($pedidoConfirmado->folio, $folios);
        $this->assertNotContains($pedidoError->folio, $folios);
    }
}
