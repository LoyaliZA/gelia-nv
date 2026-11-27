<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCancelacionOperativa;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafPedidoAplicacion;
use App\Models\User;
use App\Services\ControlPedidos\Nacional\Multienvio\ConfirmarMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\DetectarHechosPreviosMultienvio;
use App\Services\ControlPedidos\Nacional\Multienvio\MultienvioNacionalException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\ControlPedidos\Concerns\EscenarioMultienvioNacional;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Orden de comprobaciones de Confirmar (el de la tarea 6): autorización, estado terminal/no configurando,
 * revisión obsoleta, elegibilidad y, al final, hechos previos. La revisión obsoleta solo gana en `configurando`.
 */
class MultienvioNacionalHechosPreviosTest extends TestCase
{
    use EscenarioMultienvioNacional;
    use RefreshDatabase;

    private User $ventas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        Storage::fake('public');
        Storage::disk('public')->put('pedidos_bma/soporte.pdf', 'PDF');
        $this->ventas = User::factory()->create();
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'MEV-PR', 'nombre_visual' => 'Pesaje respondido', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
    }

    /** @return array<string, array{0: callable, 1: list<string>}> */
    public static function casos(): array
    {
        return [
            'pago validado' => [fn (self $t, PedidoBma $p, array $c) => $p->update(['pago_validado_at' => now()]), ['pago_validado']],
            'remisión' => [fn (self $t, PedidoBma $p, array $c) => $p->update(['numero_remision' => 'R-1']), ['remision']],
            'remisión como PDF vigente sin número' => [fn (self $t, PedidoBma $p, array $c) => $p->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_REMISION, 'ruta_archivo' => 'pedidos_bma/remision.pdf', 'nombre_original' => 'remision.pdf', 'activo' => true,
            ]), ['remision']],
            'guía PDF vigente' => [fn (self $t, PedidoBma $p, array $c) => $p->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_GUIA, 'ruta_archivo' => 'pedidos_bma/guia.pdf', 'nombre_original' => 'guia.pdf', 'activo' => true,
            ]), ['guia']],
            'guía subida' => [fn (self $t, PedidoBma $p, array $c) => $p->update(['guia_subida_at' => now()]), ['guia']],
            'rastreo de una caja' => [fn (self $t, PedidoBma $p, array $c) => PedidoBmaCaja::query()->whereKey($c[2]->id)
                ->update(['numero_rastreo' => 'TRK-CAJA']), ['guia']],
            'clave JSON de hecho posterior' => [fn (self $t, PedidoBma $p, array $c) => $p->update([
                'solicitud_preparacion_json' => array_merge($p->solicitud_preparacion_json, ['cancelacion_nacional' => ['solicitada' => true]]),
            ]), ['complemento']],
            'aplicación de saldo a favor' => [function (self $t, PedidoBma $p, array $c) {
                // Sin armar crédito/cuenta/cliente: basta la fila para el hecho (FK diferida; la transacción de prueba se revierte).
                DB::statement('PRAGMA defer_foreign_keys = ON');
                SafPedidoAplicacion::query()->forceCreate([
                    'pedido_bma_id' => $p->id, 'saf_credito_id' => 9999, 'monto' => 10, 'estado' => SafPedidoAplicacion::ESTADO_RESERVADO,
                ]);
            }, ['cierre_o_saf']],
            'rastreo' => [fn (self $t, PedidoBma $p, array $c) => $p->update(['numero_rastreo' => 'TRK-1']), ['guia']],
            'caja recolectada' => [fn (self $t, PedidoBma $p, array $c) => PedidoBmaCaja::query()->whereKey($c[1]->id)
                ->update(['estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA]), ['salida_parcial']],
            'empacada' => [fn (self $t, PedidoBma $p, array $c) => $p->update(['empacado_at' => now()]), ['empacada']],
            'pago capturado sin validar' => [fn (self $t, PedidoBma $p, array $c) => PedidoBmaPago::query()->forceCreate([
                'pedido_bma_id' => $p->id, 'numero_exhibicion' => 1, 'monto' => 100, 'forma_pago' => 'transferencia',
            ]), ['pago_capturado']],
            'saldo a favor' => [fn (self $t, PedidoBma $p, array $c) => $p->update(['saldo_a_favor' => 1000]), ['cierre_o_saf']],
            'cierre de pago' => [fn (self $t, PedidoBma $p, array $c) => PedidoBmaCierrePago::query()->forceCreate([
                'pedido_bma_id' => $p->id, 'version' => 1, 'validado_at' => now(), 'validado_por_id' => $t->ventas->id,
                'monto_venta' => 1500, 'total_pedido' => 1500, 'total_a_cobrar' => 1500, 'pagos_validos' => 1500,
                'diferencia' => 0, 'tolerancia_aplicada' => 0, 'estado_cobertura' => 'cubierto',
            ]), ['cierre_o_saf']],
            'cancelación activa' => [fn (self $t, PedidoBma $p, array $c) => PedidoBmaCancelacionOperativa::query()->forceCreate([
                'pedido_bma_id' => $p->id, 'estado' => PedidoBmaCancelacionOperativa::ESTADO_SOLICITADA, 'motivo' => 'cliente',
            ]), ['cancelacion']],
            'complemento hijo' => [fn (self $t, PedidoBma $p, array $c) => $t->hijoComplemento($p), ['complemento']],
        ];
    }

    #[DataProvider('casos')]
    public function test_cada_hecho_previo_regulariza_sin_mover_nada(callable $hecho, array $esperadas): void
    {
        [$origen, $grupo, $cajas] = $this->escenarioConBorrador();
        $hecho($this, $origen, $cajas);
        $antes = $this->foto($origen);

        $r = $this->confirmar($grupo->fresh());

        $this->assertSame(MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE, $r->estado);
        $this->assertEqualsCanonicalizing($esperadas, $r->regularizacion_json['hechos']);
        $this->assertSame($this->ventas->id, $r->regularizacion_json['solicitado_por_id']);
        $this->assertNotEmpty($r->regularizacion_json['solicitado_at']);
        $this->assertSame($grupo->revision_consulta, $r->regularizacion_json['revision']);
        $this->assertNotEmpty($r->regularizacion_json['motivo']);
        $this->assertNull($r->activado_at);

        $antes['grupo'][0]['estado'] = MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE;
        $this->assertSame($antes, $this->foto($origen));
        $this->assertSame($antes['pedidos'], PedidoBma::query()->count());
    }

    public function test_pedido_limpio_no_tiene_hechos_previos(): void
    {
        [$origen] = $this->escenarioConBorrador();

        $hechos = app(DetectarHechosPreviosMultienvio::class)->ejecutar($origen);

        $this->assertEqualsCanonicalizing(
            ['pago_capturado', 'pago_validado', 'cierre_o_saf', 'remision', 'guia', 'empacada', 'salida_parcial', 'cancelacion', 'complemento'],
            array_keys($hechos)
        );
        $this->assertSame([], array_keys(array_filter($hechos)));
        $this->assertFalse(DetectarHechosPreviosMultienvio::hayHechos($hechos));
    }

    public function test_repetir_la_confirmacion_devuelve_el_grupo_sin_cambios(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $origen->update(['numero_remision' => 'R-1']);
        $primero = $this->confirmar($grupo->fresh());
        $antes = $this->foto($origen);

        $segundo = $this->confirmar($grupo->fresh());

        $this->assertSame($primero->regularizacion_json, $segundo->regularizacion_json);
        $this->assertSame($antes, $this->foto($origen));
    }

    public function test_usuario_ajeno_no_puede_regularizar(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $origen->update(['numero_remision' => 'R-1']);
        $ajeno = User::factory()->create();

        try {
            app(ConfirmarMultienvio::class)->ejecutar($grupo->fresh(), $ajeno->id);
            $this->fail('Debió negar.');
        } catch (AuthorizationException) {
            $this->assertSame(MultienvioNacional::ESTADO_CONFIGURANDO, $grupo->fresh()->estado);
            $this->assertNull($grupo->fresh()->regularizacion_json);
        }
    }

    public function test_revision_obsoleta_gana_sobre_hechos_previos_solo_en_configurando(): void
    {
        [$origen, $grupo] = $this->escenarioConBorrador();
        $origen->update(['numero_remision' => 'R-1']);

        try {
            app(ConfirmarMultienvio::class)->ejecutar($grupo->fresh(), $this->ventas->id, 'otra-revision');
            $this->fail('Debió lanzar revisión obsoleta.');
        } catch (MultienvioNacionalException $e) {
            $this->assertSame(MultienvioNacionalException::revisionObsoleta()->getMessage(), $e->getMessage());
        }
        $this->assertSame(MultienvioNacional::ESTADO_CONFIGURANDO, $grupo->fresh()->estado);
        $this->assertNull($grupo->fresh()->regularizacion_json);

        // Ya en regularización pendiente, una revisión distinta se ignora y se devuelve el grupo.
        $this->confirmar($grupo->fresh());
        $r = app(ConfirmarMultienvio::class)->ejecutar($grupo->fresh(), $this->ventas->id, 'otra-revision');
        $this->assertSame(MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE, $r->estado);
    }

    public function hijoComplemento(PedidoBma $principal): void
    {
        $this->pedidoNacional(['pedido_principal_id' => $principal->id, 'folio_remision' => 'HIJO-'.uniqid()]);
    }
}
