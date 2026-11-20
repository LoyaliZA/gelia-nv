<?php

namespace Tests\Feature\PuntoVenta;

use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReconciliarColaTurnosPdvTest extends TestCase
{
    use RefreshDatabase;

    public function test_encola_matchmaker_solo_para_sucursales_con_turnos_esperando(): void
    {
        Queue::fake();
        $conCola = Sucursal::factory()->create();
        $sinCola = Sucursal::factory()->create();
        $reciente = Sucursal::factory()->create();

        $crear = fn (Sucursal $s, string $estado, $alta) => TurnoPdv::factory()->create([
            'sucursal_id' => $s->id, 'estado' => $estado, 'alta_at' => $alta,
            'atencion_actual_id' => null, 'es_demo' => false,
        ]);
        $crear($conCola, TurnoPdv::ESTADO_EN_COLA, now()->subMinutes(5));
        $crear($conCola, TurnoPdv::ESTADO_EN_COLA, now()->subMinutes(3));
        $crear($sinCola, TurnoPdv::ESTADO_ASIGNADO, now()->subMinutes(5));
        $crear($reciente, TurnoPdv::ESTADO_EN_COLA, now()->subSeconds(10));

        $this->artisan('pdv:reconciliar-cola-turnos')->assertSuccessful();

        Queue::assertPushed(EjecutarMatchmakerTurnosPdvJob::class, 1);
        Queue::assertPushed(
            EjecutarMatchmakerTurnosPdvJob::class,
            fn ($job) => $job->sucursalId === $conCola->id && $job->origenDisparador === 'reconciliacion'
        );
    }
}
