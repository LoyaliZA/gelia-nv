<?php

namespace Tests\Feature\SaldosAFavor;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafComprobanteCaja;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EndurecerSaldosAFavorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cliente $cliente;

    private SafMotivo $motivo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $lista = CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );

        $this->user = User::factory()->create();
        foreach ([
            'saldos_favor.ver',
            'saldos_favor.generar',
            'saldos_favor.revisar',
            'saldos_favor.ajustar',
            'saldos_favor.caja',
        ] as $perm) {
            Permission::findOrCreate($perm);
            $this->user->givePermissionTo($perm);
        }

        $this->cliente = Cliente::create([
            'numero_cliente' => '90100',
            'nombre' => 'Cliente Endurecer SAF',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);

        $this->motivo = SafMotivo::firstOrCreate(
            ['codigo' => 'pago_de_mas'],
            [
                'nombre' => 'Cliente depositó de más',
                'categoria' => 'diferencias_pago',
                'requiere_detalle' => false,
                'activo' => true,
                'orden' => 1,
            ]
        );

        Sucursal::firstOrCreate(
            ['codigo' => 'CENTRO'],
            ['nombre' => 'Bellaroma Centro', 'activo' => true]
        );
    }

    public function test_generar_sin_motivo_falla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('motivo');

        app(GenerarCreditoSafService::class)->handle([
            'cliente_id' => $this->cliente->id,
            'monto' => 100,
            'generado_por_id' => $this->user->id,
        ]);
    }

    public function test_http_generar_sin_motivo_devuelve_422(): void
    {
        $this->actingAs($this->user)
            ->post(route('saldos_favor.generar'), [
                'cliente_id' => $this->cliente->id,
                'monto' => 50,
                'canal_origen' => 'bellaroma',
                'evidencias' => [UploadedFile::fake()->image('comp.jpg')],
            ])
            ->assertSessionHasErrors('saf_motivo_id');
    }

    public function test_doble_generacion_mismo_pedido_y_monto_rechazada(): void
    {
        $estatus = CatalogoEstatusPedido::firstOrCreate(
            ['codigo_interno' => 'BORRADOR'],
            [
                'nombre_visual' => 'Borrador',
                'color_hex' => '#94a3b8',
                'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
                'orden' => 1,
                'activo' => true,
            ]
        );
        $pedido = PedidoBma::create([
            'folio' => 'BMA-DUP-'.uniqid(),
            'vendedor_id' => $this->user->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 1000,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 1000,
            'fecha' => now()->toDateString(),
        ]);

        $svc = app(GenerarCreditoSafService::class);
        $svc->handle([
            'cliente_id' => $this->cliente->id,
            'monto' => 80,
            'saf_motivo_id' => $this->motivo->id,
            'pedido_bma_id' => $pedido->id,
            'generado_por_id' => $this->user->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ya existe un saldo a favor');

        $svc->handle([
            'cliente_id' => $this->cliente->id,
            'monto' => 80,
            'saf_motivo_id' => $this->motivo->id,
            'pedido_bma_id' => $pedido->id,
            'generado_por_id' => $this->user->id,
        ]);
    }

    public function test_publico_general_rechazado(): void
    {
        $lista = CatalogoListaDescuento::first();
        $anonimo = Cliente::create([
            'numero_cliente' => '99990',
            'nombre' => 'Público General',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('público general');

        app(GenerarCreditoSafService::class)->handle([
            'cliente_id' => $anonimo->id,
            'monto' => 10,
            'saf_motivo_id' => $this->motivo->id,
            'generado_por_id' => $this->user->id,
        ]);
    }

    public function test_revisar_pago_desde_bandeja_saf(): void
    {
        $estatus = CatalogoEstatusPedido::firstOrCreate(
            ['codigo_interno' => 'BORRADOR'],
            [
                'nombre_visual' => 'Borrador',
                'color_hex' => '#94a3b8',
                'fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR,
                'orden' => 1,
                'activo' => true,
            ]
        );
        $pedido = PedidoBma::create([
            'folio' => 'BMA-PAG-'.uniqid(),
            'vendedor_id' => $this->user->id,
            'cliente_id' => $this->cliente->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'total_mercancia' => 500,
            'costo_envio' => 0,
            'aplica_seguro' => false,
            'costo_seguro' => 0,
            'saldo_a_favor' => 0,
            'total_a_cobrar' => 500,
            'fecha' => now()->toDateString(),
        ]);
        $pago = PedidoBmaPago::create([
            'pedido_bma_id' => $pedido->id,
            'numero_exhibicion' => 1,
            'monto' => 500,
            'forma_pago' => 'transferencia',
            'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
            'capturado_por_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('saldos_favor.pagos.revisar', $pago), [
                'estado_revision' => 'confirmado',
            ])
            ->assertRedirect();

        $this->assertSame(PedidoBmaPago::REVISION_CONFIRMADO, $pago->fresh()->estado_revision);
    }

    public function test_caja_aplica_vincula_comprobante_en_movimiento(): void
    {
        app(GenerarCreditoSafService::class)->handle([
            'cliente_id' => $this->cliente->id,
            'monto' => 200,
            'saf_motivo_id' => $this->motivo->id,
            'canal_origen' => 'punto_venta',
            'generado_por_id' => $this->user->id,
        ]);

        $credito = SafCredito::where('cliente_id', $this->cliente->id)->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('saldos_favor.caja.aplicar'), [
                'cliente_id' => $this->cliente->id,
                'sucursal' => 'Bellaroma Centro',
                'caja' => '02',
                'perfil_impresion' => '80mm',
                'referencia_venta' => 'T-100',
                'items' => [
                    ['saf_credito_id' => $credito->id, 'monto' => 75],
                ],
            ])
            ->assertRedirect();

        $comp = SafComprobanteCaja::query()->latest('id')->first();
        $this->assertNotNull($comp);
        $this->assertSame('Bellaroma Centro', $comp->sucursal);
        $this->assertSame('02', $comp->caja);
        $this->assertNotNull($comp->logo_key);

        $this->assertDatabaseHas('saf_movimientos', [
            'tipo' => SafMovimiento::TIPO_APLICACION,
            'saf_credito_id' => $credito->id,
            'saf_comprobante_caja_id' => $comp->id,
        ]);

        $this->assertDatabaseHas('saf_impresion_preferencias', [
            'user_id' => $this->user->id,
            'sucursal' => 'Bellaroma Centro',
            'caja' => '02',
        ]);
    }

    public function test_imprimir_comprobante_html_termico_con_logo_departamento(): void
    {
        $depto = \App\Models\Departamento::query()->create([
            'nombre' => 'Bellaroma Test',
            'activo' => true,
            'logo_key_claro' => 'bellaroma_logo_negro',
            'logo_key_oscuro' => 'bellaroma_logo_blanco',
        ]);
        $this->user->departamentos()->sync([$depto->id]);
        $this->user->update(['departamento_id' => $depto->id]);

        $cuenta = \App\Models\SaldosAFavor\SafCuenta::query()->firstOrCreate(
            ['cliente_id' => $this->cliente->id],
            ['moneda' => 'MXN']
        );

        $comp = SafComprobanteCaja::create([
            'folio' => 'SAF-PDV-TEST001',
            'cliente_id' => $this->cliente->id,
            'saf_cuenta_id' => $cuenta->id,
            'sucursal' => 'Centro',
            'caja' => '01',
            'saldo_anterior' => 100,
            'monto_aplicado' => 40,
            'saldo_restante' => 60,
            'creditos_detalle' => [['folio' => 'SAF-1', 'canal_origen' => 'manual', 'monto' => 40]],
            'estado' => SafComprobanteCaja::ESTADO_PENDIENTE_FIRMA,
            'perfil_impresion' => '80mm',
            'generado_por_id' => $this->user->id,
            'departamento_id' => $depto->id,
            'logo_key' => 'bellaroma_logo_negro',
            'aplicado_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('saldos_favor.caja.imprimir', [$comp, 'autoprint' => 0]));

        $response->assertOk();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('content-type'));
        $html = $response->getContent();
        $this->assertStringContainsString('size: 80mm auto', $html);
        $this->assertStringContainsString('Aplicación de saldo a favor', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('BELLAROMA</strong>', $html);

        $pdf = $this->actingAs($this->user)
            ->get(route('saldos_favor.caja.descargar', [$comp, 'perfil' => '80mm']));

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringContainsString($comp->folio, (string) $pdf->headers->get('content-disposition'));
    }

    public function test_generacion_manual_http_requiere_evidencia(): void
    {
        $this->actingAs($this->user)
            ->post(route('saldos_favor.generar'), [
                'cliente_id' => $this->cliente->id,
                'monto' => 25,
                'saf_motivo_id' => $this->motivo->id,
                'canal_origen' => 'bellaroma',
            ])
            ->assertSessionHasErrors('evidencias');
    }
}
