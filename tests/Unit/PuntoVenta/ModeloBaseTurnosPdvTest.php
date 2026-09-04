<?php

namespace Tests\Unit\PuntoVenta;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\PuntoVenta\ContadorFolioTurnoPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\PuntoVenta\TurnoPdvProrroga;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModeloBaseTurnosPdvTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_crea_tablas_del_contrato(): void
    {
        foreach ([
            'pdv_turnos',
            'pdv_turno_atenciones',
            'pdv_turno_prorrogas',
            'pdv_turno_eventos',
            'pdv_contadores_folio',
        ] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), $tabla);
        }

        $this->assertTrue(Schema::hasColumns('pdv_turnos', [
            'sucursal_id',
            'cliente_id',
            'folio',
            'servicio',
            'origen',
            'estado',
            'snapshot_nombre_llamado',
            'alta_at',
            'atencion_actual_id',
            'version',
        ]));
    }

    public function test_relaciones_con_fuentes_maestras_y_casts(): void
    {
        $sucursal = Sucursal::factory()->create();
        $recepcion = User::factory()->create();
        $vendedor = User::factory()->create();
        $cliente = $this->crearCliente();
        $alta = now()->subMinutes(10);

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'cliente_id' => $cliente->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'snapshot_nombre_llamado' => $cliente->nombre,
            'snapshot_cliente_nombre' => $cliente->nombre,
            'snapshot_json' => ['cliente_id' => $cliente->id],
            'alta_at' => $alta,
            'alta_por_id' => $recepcion->id,
            'version' => 2,
        ]);

        $this->assertTrue($turno->sucursal->is($sucursal));
        $this->assertTrue($turno->cliente->is($cliente));
        $this->assertTrue($turno->altaPor->is($recepcion));
        $this->assertTrue($sucursal->turnosPdv()->whereKey($turno->id)->exists());
        $this->assertTrue($cliente->turnosPdv()->whereKey($turno->id)->exists());
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $turno->alta_at);
        $this->assertFalse($turno->alta_at->equalTo($turno->created_at));
        $this->assertSame(['cliente_id' => $cliente->id], $turno->snapshot_json);
        $this->assertSame(2, $turno->version);

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $vendedor->id,
            'numero_secuencia' => 1,
            'inicio_at' => now()->subMinutes(5),
        ]);

        $turno->update(['atencion_actual_id' => $atencion->id]);
        $turno->refresh();

        $this->assertTrue($turno->atencionActual->is($atencion));
        $this->assertTrue($turno->atenciones()->whereKey($atencion->id)->exists());
        $this->assertTrue($atencion->user->is($vendedor));

        $prorroga = TurnoPdvProrroga::query()->create([
            'atencion_id' => $atencion->id,
            'referencia_inicio_at' => $atencion->inicio_at,
            'alertado_at' => now(),
            'snapshot_json' => ['umbral_minutos' => 20],
        ]);

        $evento = TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'atencion_id' => $atencion->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_ASIGNADO,
            'estado_anterior' => TurnoPdv::ESTADO_EN_COLA,
            'estado_nuevo' => TurnoPdv::ESTADO_ASIGNADO,
            'actor_id' => $recepcion->id,
            'ocurrido_at' => $atencion->inicio_at,
            'snapshot_json' => ['user_id' => $vendedor->id],
            'idempotency_key' => 'evt-asignado-1',
        ]);

        $this->assertTrue($atencion->prorroga->is($prorroga));
        $this->assertTrue($turno->eventos()->whereKey($evento->id)->exists());
        $this->assertTrue($evento->actor->is($recepcion));
    }

    public function test_permite_multiples_atenciones_sobre_el_mismo_turno(): void
    {
        $turno = TurnoPdv::factory()->create([
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
        ]);
        $vendedor1 = User::factory()->create();
        $vendedor2 = User::factory()->create();

        $primera = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $vendedor1->id,
            'numero_secuencia' => 1,
            'inicio_at' => now()->subHours(2),
            'fin_at' => now()->subHours(1)->subMinutes(30),
            'motivo_cierre' => 'venta',
        ]);

        $segunda = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $vendedor2->id,
            'numero_secuencia' => 2,
            'inicio_at' => now()->subMinutes(20),
            'es_transferencia' => false,
        ]);

        $this->assertSame(2, $turno->atenciones()->count());
        $this->assertSame($turno->folio, $turno->fresh()->folio);
        $this->assertTrue($primera->fin_at->lessThan($segunda->inicio_at));
    }

    public function test_permite_turno_visitante_sin_cliente(): void
    {
        $turno = TurnoPdv::factory()->visitante()->create([
            'snapshot_nombre_llamado' => 'Ana Visitante',
        ]);

        $this->assertNull($turno->cliente_id);
        $this->assertSame('Ana Visitante', $turno->snapshot_nombre_llamado);
        $this->assertNotNull($turno->sucursal_id);
    }

    public function test_unicidad_contador_por_sucursal_fecha_y_servicio(): void
    {
        $sucursal = Sucursal::factory()->create();
        $fecha = now()->toDateString();

        ContadorFolioTurnoPdv::query()->create([
            'sucursal_id' => $sucursal->id,
            'fecha_operativa' => $fecha,
            'servicio' => TurnoPdv::SERVICIO_VENTAS,
            'ultimo_numero' => 3,
            'version' => 1,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        ContadorFolioTurnoPdv::query()->create([
            'sucursal_id' => $sucursal->id,
            'fecha_operativa' => $fecha,
            'servicio' => TurnoPdv::SERVICIO_VENTAS,
            'ultimo_numero' => 4,
            'version' => 1,
        ]);
    }

    public function test_unicidad_folio_por_sucursal(): void
    {
        $sucursal = Sucursal::factory()->create();

        TurnoPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'folio' => 'V-0001',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        TurnoPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'folio' => 'V-0001',
        ]);
    }

    public function test_idempotency_key_de_eventos_es_unica(): void
    {
        $turno = TurnoPdv::factory()->create();

        TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_ALTA,
            'estado_nuevo' => TurnoPdv::ESTADO_EN_COLA,
            'ocurrido_at' => now(),
            'idempotency_key' => 'turno-alta-1',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_ALTA,
            'estado_nuevo' => TurnoPdv::ESTADO_EN_COLA,
            'ocurrido_at' => now(),
            'idempotency_key' => 'turno-alta-1',
        ]);
    }

    public function test_fk_exige_sucursal_existente(): void
    {
        $this->expectException(QueryException::class);

        TurnoPdv::query()->create([
            'sucursal_id' => 999999,
            'folio' => 'V-9999',
            'servicio' => TurnoPdv::SERVICIO_VENTAS,
            'origen' => TurnoPdv::ORIGEN_RECEPCION,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'snapshot_nombre_llamado' => 'Persona prueba',
            'alta_at' => now(),
            'version' => 1,
        ]);
    }

    private function crearCliente(): Cliente
    {
        $listaId = CatalogoListaDescuento::query()->value('id');
        if ($listaId === null) {
            $listaId = CatalogoListaDescuento::query()->create([
                'nombre' => 'PUBLICO GENERAL PDV TURNOS',
            ])->id;
        }

        return Cliente::query()->create([
            'numero_cliente' => (string) fake()->unique()->numerify('91###'),
            'nombre' => 'Cliente turno PDV',
            'lista_actual_id' => $listaId,
            'monto_venta_actual' => 0,
        ]);
    }
}
