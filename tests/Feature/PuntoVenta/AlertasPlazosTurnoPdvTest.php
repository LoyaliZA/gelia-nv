<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\AtencionEsperaProximoVencer;
use App\Events\PuntoVenta\AtencionProrrogaProximoVencer;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Services\PuntoVenta\Turnos\AlertaEsperaProximoVencerTurnoPdvService;
use App\Services\PuntoVenta\Turnos\AlertaProrrogaProximoVencerTurnoPdvService;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use App\Support\PuntoVenta\Alertas\CatalogoAlertasTurnosPdv;
use App\Support\PuntoVenta\Broadcast\PdvRealtimeMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AlertasPlazosTurnoPdvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PlazosTurnosPdvConfig::CLAVE],
            ['valor' => json_encode((new PlazosTurnosPdvConfig)->configuracionInicialAprobada())],
        );
    }

    public function test_espera_proximo_vencer_emite_evento_realtime_sucursal(): void
    {
        Event::fake([AtencionEsperaProximoVencer::class]);

        $sucursal = Sucursal::factory()->create();
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'folio' => 'V-0500',
        ]);
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'inicio_at' => now()->subMinutes(4),
            'atencion_inicio_at' => null,
        ]);

        $emitido = app(AlertaEsperaProximoVencerTurnoPdvService::class)
            ->ejecutar($atencion->id, now());

        $this->assertTrue($emitido);
        Event::assertDispatched(AtencionEsperaProximoVencer::class);

        $evento = TurnoPdvEvento::query()
            ->where('tipo_evento', TurnoPdvEvento::TIPO_ESPERA_PROXIMO_VENCER)
            ->first();
        $this->assertNotNull($evento);

        $mapper = new PdvRealtimeMapper;
        $transmisiones = $mapper->transmisiones(new AtencionEsperaProximoVencer(
            $turno,
            $atencion,
            $evento,
            (int) $sucursal->id,
        ));

        $this->assertCount(1, $transmisiones);
        $this->assertSame('sucursal', $transmisiones[0]['envelope']['audiencia']);
        $this->assertSame(
            CatalogoAlertasTurnosPdv::PRIORIDAD_CRITICA,
            CatalogoAlertasTurnosPdv::prioridadDe(TurnoPdvEvento::TIPO_ESPERA_PROXIMO_VENCER),
        );
    }

    public function test_prorroga_proximo_vencer_emite_evento_realtime_sucursal(): void
    {
        Event::fake([AtencionProrrogaProximoVencer::class]);

        $sucursal = Sucursal::factory()->create();
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'folio' => 'V-0600',
        ]);
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'atencion_inicio_at' => now()->subMinutes(18),
            'fin_at' => null,
        ]);

        $emitido = app(AlertaProrrogaProximoVencerTurnoPdvService::class)
            ->ejecutar($atencion->id, now());

        $this->assertTrue($emitido);
        Event::assertDispatched(AtencionProrrogaProximoVencer::class);
    }
}
