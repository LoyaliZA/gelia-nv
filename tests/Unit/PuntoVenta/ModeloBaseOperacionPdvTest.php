<?php

namespace Tests\Unit\PuntoVenta;

use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModeloBaseOperacionPdvTest extends TestCase
{
    use RefreshDatabase;

    public function test_migracion_crea_tablas_del_contrato(): void
    {
        foreach ([
            'pdv_jornadas',
            'pdv_intervalos_operativos',
            'pdv_sucursal_dias',
        ] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), $tabla);
        }

        $this->assertTrue(Schema::hasColumns('pdv_jornadas', [
            'user_id',
            'sucursal_id',
            'estado',
            'apertura_at',
            'cierre_at',
            'jornada_activa_marcador',
            'version',
        ]));

        $this->assertTrue(Schema::hasColumns('pdv_intervalos_operativos', [
            'jornada_id',
            'user_id',
            'sucursal_id',
            'tipo',
            'atencion_id',
            'inicio_at',
            'fin_at',
            'intervalo_abierto_marcador',
            'version',
        ]));

        $this->assertTrue(Schema::hasColumns('pdv_sucursal_dias', [
            'sucursal_id',
            'fecha_operativa',
            'hora_cierre',
            'acepta_altas',
            'cierre_manual_at',
            'cierre_automatico_invalidado',
            'ampliacion_hasta_at',
            'version',
        ]));
    }

    public function test_relaciones_con_fuentes_maestras_y_casts(): void
    {
        $sucursal = Sucursal::factory()->create();
        $vendedor = User::factory()->create();
        $gerencia = User::factory()->create();
        $apertura = now()->subHours(2);

        $jornada = JornadaPdv::factory()->create([
            'user_id' => $vendedor->id,
            'sucursal_id' => $sucursal->id,
            'estado' => EstadoJornadaPdv::Abierta,
            'apertura_at' => $apertura,
            'version' => 2,
        ]);

        $this->assertTrue($jornada->user->is($vendedor));
        $this->assertTrue($jornada->sucursal->is($sucursal));
        $this->assertTrue($sucursal->jornadasPdv()->whereKey($jornada->id)->exists());
        $this->assertTrue($vendedor->jornadasPdv()->whereKey($jornada->id)->exists());
        $this->assertInstanceOf(EstadoJornadaPdv::class, $jornada->estado);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $jornada->apertura_at);
        $this->assertFalse($jornada->apertura_at->equalTo($jornada->created_at));
        $this->assertSame('1', $jornada->jornada_activa_marcador);
        $this->assertSame(2, $jornada->version);

        $intervalo = IntervaloOperativoPdv::factory()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $vendedor->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => TipoIntervaloOperativoPdv::Disponible,
            'inicio_at' => now()->subHour(),
        ]);

        $this->assertTrue($intervalo->jornada->is($jornada));
        $this->assertTrue($jornada->intervalos()->whereKey($intervalo->id)->exists());
        $this->assertInstanceOf(TipoIntervaloOperativoPdv::class, $intervalo->tipo);
        $this->assertSame('1', $intervalo->intervalo_abierto_marcador);

        $turno = TurnoPdv::factory()->create(['sucursal_id' => $sucursal->id]);
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $vendedor->id,
            'inicio_at' => now()->subMinutes(20),
            'fin_at' => now()->subMinutes(5),
        ]);

        $intervaloAtencion = IntervaloOperativoPdv::factory()->cerrado()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $vendedor->id,
            'sucursal_id' => $sucursal->id,
            'tipo' => TipoIntervaloOperativoPdv::EnAtencion,
            'atencion_id' => $atencion->id,
            'inicio_at' => $atencion->inicio_at,
            'fin_at' => $atencion->fin_at,
        ]);

        $this->assertTrue($intervaloAtencion->atencion->is($atencion));
        $this->assertNull($intervaloAtencion->intervalo_abierto_marcador);

        $dia = SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'fecha_operativa' => now()->toDateString(),
            'hora_cierre' => '19:00:00',
            'acepta_altas' => true,
        ]);

        $this->assertTrue($dia->sucursal->is($sucursal));
        $this->assertTrue($sucursal->diasOperacionPdv()->whereKey($dia->id)->exists());
        $this->assertTrue($dia->acepta_altas);
    }

    public function test_jornada_abierta_y_cerrada_y_duracion_desde_timestamps_operativos(): void
    {
        $apertura = now()->subMinutes(90);
        $cierre = now()->subMinutes(15);

        $abierta = JornadaPdv::factory()->create([
            'estado' => EstadoJornadaPdv::Abierta,
            'apertura_at' => $apertura,
            'cierre_at' => null,
        ]);

        $cerrada = JornadaPdv::factory()->cerrada()->create([
            'apertura_at' => $apertura,
            'cierre_at' => $cierre,
        ]);

        $this->assertTrue($abierta->estaAbierta());
        $this->assertTrue($abierta->estaActiva());
        $this->assertFalse($cerrada->estaAbierta());
        $this->assertFalse($cerrada->estaActiva());
        $this->assertSame('1', $abierta->jornada_activa_marcador);
        $this->assertNull($cerrada->jornada_activa_marcador);
        $this->assertSame(4500, $cerrada->duracionSegundos());
        $this->assertNotSame($abierta->created_at->timestamp, $abierta->apertura_at->timestamp);
    }

    public function test_intervalo_abierto_cerrado_y_duracion_desde_timestamps_operativos(): void
    {
        $inicio = now()->subMinutes(40);
        $fin = now()->subMinutes(10);

        $abierto = IntervaloOperativoPdv::factory()->create([
            'tipo' => TipoIntervaloOperativoPdv::EnPausa,
            'inicio_at' => $inicio,
            'fin_at' => null,
        ]);

        $cerrado = IntervaloOperativoPdv::factory()->create([
            'tipo' => TipoIntervaloOperativoPdv::Disponible,
            'inicio_at' => $inicio,
            'fin_at' => $fin,
        ]);

        $this->assertTrue($abierto->estaAbierto());
        $this->assertFalse($cerrado->estaAbierto());
        $this->assertSame(1800, $cerrado->duracionSegundos());
        $this->assertNotSame($abierto->created_at->timestamp, $abierto->inicio_at->timestamp);
    }

    public function test_estados_de_jornada_validos_incluyen_cerrada_con_atencion(): void
    {
        $jornada = JornadaPdv::factory()->cerradaConAtencion()->create();

        $this->assertSame(EstadoJornadaPdv::CerradaConAtencion, $jornada->estado);
        $this->assertTrue($jornada->estaActiva());
        $this->assertFalse($jornada->estaAbierta());
        $this->assertSame('1', $jornada->jornada_activa_marcador);
    }

    public function test_impide_mas_de_una_jornada_activa_por_persona_y_sucursal(): void
    {
        $sucursal = Sucursal::factory()->create();
        $vendedor = User::factory()->create();

        JornadaPdv::factory()->create([
            'user_id' => $vendedor->id,
            'sucursal_id' => $sucursal->id,
            'estado' => EstadoJornadaPdv::Abierta,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        JornadaPdv::factory()->create([
            'user_id' => $vendedor->id,
            'sucursal_id' => $sucursal->id,
            'estado' => EstadoJornadaPdv::CerradaConAtencion,
        ]);
    }

    public function test_permite_varias_jornadas_cerradas_por_persona_y_sucursal(): void
    {
        $sucursal = Sucursal::factory()->create();
        $vendedor = User::factory()->create();

        JornadaPdv::factory()->cerrada()->create([
            'user_id' => $vendedor->id,
            'sucursal_id' => $sucursal->id,
            'apertura_at' => now()->subDays(2),
            'cierre_at' => now()->subDays(2)->addHours(8),
        ]);

        $segunda = JornadaPdv::factory()->cerrada()->create([
            'user_id' => $vendedor->id,
            'sucursal_id' => $sucursal->id,
            'apertura_at' => now()->subDay(),
            'cierre_at' => now()->subDay()->addHours(8),
        ]);

        $this->assertSame(2, JornadaPdv::query()
            ->where('user_id', $vendedor->id)
            ->where('sucursal_id', $sucursal->id)
            ->count());
        $this->assertNull($segunda->jornada_activa_marcador);
    }

    public function test_impide_mas_de_un_intervalo_abierto_por_persona_y_sucursal(): void
    {
        $jornada = JornadaPdv::factory()->create();

        IntervaloOperativoPdv::factory()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $jornada->user_id,
            'sucursal_id' => $jornada->sucursal_id,
            'tipo' => TipoIntervaloOperativoPdv::Disponible,
            'inicio_at' => now()->subMinutes(30),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        IntervaloOperativoPdv::factory()->enPausa()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $jornada->user_id,
            'sucursal_id' => $jornada->sucursal_id,
            'inicio_at' => now()->subMinutes(5),
        ]);
    }

    public function test_permite_intervalos_cerrados_solapados_en_tiempo_por_persona_y_sucursal(): void
    {
        $jornada = JornadaPdv::factory()->create();
        $inicio = now()->subHour();

        IntervaloOperativoPdv::factory()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $jornada->user_id,
            'sucursal_id' => $jornada->sucursal_id,
            'tipo' => TipoIntervaloOperativoPdv::Disponible,
            'inicio_at' => $inicio,
            'fin_at' => now()->subMinutes(30),
        ]);

        $segundo = IntervaloOperativoPdv::factory()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $jornada->user_id,
            'sucursal_id' => $jornada->sucursal_id,
            'tipo' => TipoIntervaloOperativoPdv::EnPausa,
            'inicio_at' => $inicio->copy()->addMinutes(10),
            'fin_at' => now()->subMinutes(5),
        ]);

        $this->assertNull($segundo->intervalo_abierto_marcador);
        $this->assertSame(2, IntervaloOperativoPdv::query()
            ->where('user_id', $jornada->user_id)
            ->where('sucursal_id', $jornada->sucursal_id)
            ->count());
    }

    public function test_registro_diario_unico_por_sucursal_y_fecha(): void
    {
        $sucursal = Sucursal::factory()->create();
        $fecha = now()->toDateString();

        SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'fecha_operativa' => $fecha,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'fecha_operativa' => $fecha,
        ]);
    }

    public function test_cierre_manual_invalida_automatico_y_deja_de_aceptar_altas(): void
    {
        $sucursal = Sucursal::factory()->create();
        $gerencia = User::factory()->create();
        $cierreManual = now()->setTime(19, 0);

        $dia = SucursalDiaOperacionPdv::factory()->create([
            'sucursal_id' => $sucursal->id,
            'fecha_operativa' => $cierreManual->toDateString(),
            'acepta_altas' => true,
            'cierre_automatico_invalidado' => false,
        ]);

        $dia->aplicaCierreManual($gerencia, $cierreManual);
        $dia->save();
        $dia->refresh();

        $this->assertFalse($dia->acepta_altas);
        $this->assertTrue($dia->cierre_automatico_invalidado);
        $this->assertTrue($dia->cierreManualPor->is($gerencia));
        $this->assertTrue($dia->cierre_manual_at->equalTo($cierreManual));
    }

    public function test_fk_exige_sucursal_existente_en_jornada(): void
    {
        $this->expectException(QueryException::class);

        JornadaPdv::query()->create([
            'user_id' => User::factory()->create()->id,
            'sucursal_id' => 999999,
            'estado' => EstadoJornadaPdv::Abierta,
            'apertura_at' => now(),
            'version' => 1,
        ]);
    }
}
