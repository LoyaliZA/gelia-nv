<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\Excepciones\AcumuladoNegativoException;
use App\Services\Escalonamiento\RegistrarMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrarMovimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_misma_remision_no_suma_dos_veces_ni_toca_el_monto_del_cliente(): void
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => '8801',
            'nombre' => 'Cliente Remision',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 250,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $datos = [
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'remision',
            'folio' => 'A-100',
            'serie' => 'MTY',
            'sucursal' => '01',
            'total' => '1500.00',
            'efecto' => '1500.00',
            'fecha_emision' => '2026-10-03',
        ];

        $primero = app(RegistrarMovimiento::class)->registrar($datos);
        $segundo = app(RegistrarMovimiento::class)->registrar($datos);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, DocumentoVenta::count());
        $this->assertSame(1, EscalonamientoMovimiento::count());
        $this->assertEquals('1500.00', (string) EscalonamientoResumenCliente::first()->acumulado);
        $this->assertSame($lista->id, EscalonamientoResumenCliente::first()->clasificacion_mes_id);
        $cliente->refresh();
        $this->assertEquals(250.0, (float) $cliente->monto_venta_actual);
        $this->assertSame($lista->id, $cliente->lista_actual_id);
    }

    public function test_rechaza_un_efecto_que_dejaria_el_acumulado_negativo(): void
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => '8802',
            'nombre' => 'Cliente Devolucion',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 400,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);

        $this->expectException(AcumuladoNegativoException::class);

        try {
            app(RegistrarMovimiento::class)->registrar([
                'periodo_id' => $periodo->id,
                'cliente_id' => $cliente->id,
                'tipo' => 'devolucion',
                'folio' => 'D-1',
                'total' => '100.00',
                'efecto' => '-100.00',
                'fecha_emision' => '2026-10-04',
            ]);
        } finally {
            $this->assertSame(0, DocumentoVenta::count());
            $this->assertSame(0, EscalonamientoMovimiento::count());
            $this->assertEquals(400.0, (float) $cliente->fresh()->monto_venta_actual);
        }
    }
}
