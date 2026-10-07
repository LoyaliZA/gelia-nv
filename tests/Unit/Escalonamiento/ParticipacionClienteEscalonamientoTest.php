<?php

namespace Tests\Unit\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoReglaVersion;
use App\Services\Escalonamiento\ParticipacionClienteEscalonamiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipacionClienteEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_periodo_abierto_usa_catalogo_vigente_aunque_el_snapshot_diga_lo_contrario(): void
    {
        $lista = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO UNIT',
            'monto_requerido' => 500,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);

        $cliente = Cliente::create([
            'numero_cliente' => '9900',
            'nombre' => 'Cliente Catalogo',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);

        $version = EscalonamientoReglaVersion::create([
            'snapshot' => [[
                'id' => $lista->id,
                'nombre' => $lista->nombre,
                'monto_requerido' => '500.00',
                'activo' => true,
                'participa_escalonamiento' => false,
            ]],
        ]);

        $periodo = EscalonamientoPeriodo::create([
            'anio' => 2026,
            'mes' => 10,
            'zona_horaria' => 'America/Mexico_City',
            'estado' => EscalonamientoPeriodo::ESTADO_ABIERTO,
            'escalonamiento_regla_version_id' => $version->id,
        ]);

        $participa = app(ParticipacionClienteEscalonamiento::class)->clienteParticipa($periodo, $cliente);

        $this->assertTrue($participa);
    }
}
