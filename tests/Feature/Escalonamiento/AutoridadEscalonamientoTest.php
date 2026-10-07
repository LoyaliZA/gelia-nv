<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use App\Services\Escalonamiento\RegistrarMovimiento;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use App\Services\Solicitudes\EscalonamientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoridadEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoListaDescuento $bronce;

    private CatalogoListaDescuento $plata;

    protected function setUp(): void
    {
        parent::setUp();
        app(EscalonamientoAutoridadConfig::class)->guardar(true);

        $this->bronce = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO BRONCE',
            'monto_requerido' => 500,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $this->plata = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
    }

    public function test_registrar_movimiento_publica_monto_y_lista_en_cliente(): void
    {
        $cliente = Cliente::create([
            'numero_cliente' => '9901',
            'nombre' => 'Autoridad Movimiento',
            'lista_actual_id' => $this->bronce->id,
            'monto_venta_actual' => 0,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 11);

        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'R-1',
            'serie' => 'A',
            'sucursal' => '01',
            'total' => '1200.00',
            'efecto' => '1200.00',
            'fecha_emision' => '2026-11-05',
        ]);

        $cliente->refresh();
        $this->assertEquals(1200.0, (float) $cliente->monto_venta_actual);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertSame($this->plata->id, EscalonamientoResumenCliente::first()->lista_vigente_id);
    }

    public function test_evaluar_compra_cliente_usa_acumulado_del_modulo(): void
    {
        $cliente = Cliente::create([
            'numero_cliente' => '9902',
            'nombre' => 'Autoridad Cotizacion',
            'lista_actual_id' => $this->bronce->id,
            'monto_venta_actual' => 50,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 11);

        app(RegistrarMovimiento::class)->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'R-2',
            'total' => '800.00',
            'efecto' => '800.00',
            'fecha_emision' => '2026-11-06',
        ]);

        $cliente->refresh();
        $this->assertEquals(800.0, (float) $cliente->monto_venta_actual);

        $resultado = app(EscalonamientoService::class)->evaluarCompraCliente(
            $cliente,
            100.0,
            $this->plata->id,
            [$this->bronce, $this->plata],
        );

        $this->assertEquals(800.0, $resultado['monto_historico']);
        $this->assertEquals(900.0, $resultado['total_proyectado_bruto']);
    }

    public function test_aplicar_beneficios_no_cambia_lista_de_cliente_gobernado(): void
    {
        $cliente = Cliente::create([
            'numero_cliente' => '9903',
            'nombre' => 'Autoridad Solicitud',
            'lista_actual_id' => $this->bronce->id,
            'monto_venta_actual' => 0,
        ]);
        app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 11);

        $solicitud = new \App\Models\SolicitudTag([
            'cliente_id' => $cliente->id,
            'catalogo_lista_descuento_id' => $this->plata->id,
        ]);
        $solicitud->setRelation('cliente', $cliente);

        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud);

        $cliente->refresh();
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
    }
}
