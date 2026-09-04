<?php

namespace Tests\Unit\PuntoVenta;

use App\Models\PuntoVenta\ContadorFolioTurnoPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use App\Services\PuntoVenta\Turnos\GenerarFolioTurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class GenerarFolioTurnoPdvTest extends TestCase
{
    use RefreshDatabase;

    private GenerarFolioTurnoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GenerarFolioTurnoService::class);
    }

    public function test_genera_folio_secuencial_con_formato_aprobado(): void
    {
        $sucursal = Sucursal::factory()->create();

        $primero = $this->service->ejecutar($sucursal, TurnoPdv::SERVICIO_VENTAS);
        $segundo = $this->service->ejecutar($sucursal, TurnoPdv::SERVICIO_VENTAS);

        $this->assertSame('V-0001', $primero->folio);
        $this->assertSame('V-0002', $segundo->folio);
        $this->assertSame(1, $primero->secuencia);
        $this->assertSame(2, $segundo->secuencia);
        $this->assertSame(TurnoPdv::SERVICIO_VENTAS, $primero->servicio);
        $this->assertSame($sucursal->id, $primero->sucursalId);
    }

    public function test_multiples_generaciones_producen_folios_unicos(): void
    {
        $sucursal = Sucursal::factory()->create();
        $folios = [];

        for ($i = 0; $i < 25; $i++) {
            $folios[] = $this->service->ejecutar($sucursal, TurnoPdv::SERVICIO_VENTAS)->folio;
        }

        $this->assertCount(25, array_unique($folios));
        $this->assertSame('V-0025', end($folios));
    }

    public function test_aisla_contador_por_sucursal(): void
    {
        $sucursalA = Sucursal::factory()->create();
        $sucursalB = Sucursal::factory()->create();

        $folioA = $this->service->ejecutar($sucursalA, TurnoPdv::SERVICIO_VENTAS);
        $folioB = $this->service->ejecutar($sucursalB, TurnoPdv::SERVICIO_VENTAS);

        $this->assertSame('V-0001', $folioA->folio);
        $this->assertSame('V-0001', $folioB->folio);

        $this->assertSame(2, ContadorFolioTurnoPdv::query()->count());
        $this->assertSame(
            $folioA->fechaOperativa,
            ContadorFolioTurnoPdv::query()->where('sucursal_id', $sucursalA->id)->value('fecha_operativa')->toDateString()
        );
    }

    public function test_aisla_contador_por_fecha_operativa_en_zona_horaria(): void
    {
        $sucursal = Sucursal::factory()->create();
        $zona = config('app.timezone');

        $antesDeMedianoche = Carbon::parse('2026-09-04 23:30:00', $zona);
        $despuesDeMedianoche = Carbon::parse('2026-09-05 00:30:00', $zona);

        $folioDiaUno = $this->service->ejecutar($sucursal, TurnoPdv::SERVICIO_VENTAS, $antesDeMedianoche);
        $folioDiaDos = $this->service->ejecutar($sucursal, TurnoPdv::SERVICIO_VENTAS, $despuesDeMedianoche);

        $this->assertSame('2026-09-04', $folioDiaUno->fechaOperativa);
        $this->assertSame('2026-09-05', $folioDiaDos->fechaOperativa);
        $this->assertSame('V-0001', $folioDiaUno->folio);
        $this->assertSame('V-0001', $folioDiaDos->folio);
    }

    public function test_continua_secuencia_existente_en_contador(): void
    {
        $sucursal = Sucursal::factory()->create();
        $fecha = now()->toDateString();

        ContadorFolioTurnoPdv::query()->create([
            'sucursal_id' => $sucursal->id,
            'fecha_operativa' => $fecha,
            'servicio' => TurnoPdv::SERVICIO_VENTAS,
            'ultimo_numero' => 17,
            'version' => 1,
        ]);

        $resultado = $this->service->ejecutar($sucursal, TurnoPdv::SERVICIO_VENTAS);

        $this->assertSame('V-0018', $resultado->folio);
        $this->assertSame(18, $resultado->secuencia);
    }

    public function test_rollback_de_transaccion_padre_no_consume_folio(): void
    {
        $sucursal = Sucursal::factory()->create();

        try {
            DB::transaction(function () use ($sucursal): void {
                $this->service->ejecutar($sucursal, TurnoPdv::SERVICIO_VENTAS);
                throw new RuntimeException('fallo simulado');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, ContadorFolioTurnoPdv::query()->count());

        $resultado = $this->service->ejecutar($sucursal, TurnoPdv::SERVICIO_VENTAS);

        $this->assertSame('V-0001', $resultado->folio);
        $this->assertSame(1, ContadorFolioTurnoPdv::query()->value('ultimo_numero'));
    }

    public function test_rechaza_servicio_no_soportado(): void
    {
        $sucursal = Sucursal::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->ejecutar($sucursal, 'caja');
    }
}
