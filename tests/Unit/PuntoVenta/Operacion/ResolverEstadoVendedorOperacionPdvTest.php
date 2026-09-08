<?php

namespace Tests\Unit\PuntoVenta\Operacion;

use App\Models\PuntoVenta\EquipoAsistenciaDiaPdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Services\PuntoVenta\Operacion\ResolverEstadoVendedorOperacionPdvService;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\EstadoVendedorOperacionPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolverEstadoVendedorOperacionPdvTest extends TestCase
{
    use RefreshDatabase;

    private ResolverEstadoVendedorOperacionPdvService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ResolverEstadoVendedorOperacionPdvService::class);
    }

    public function test_sin_jornada_ni_marca_es_no_activado(): void
    {
        $estado = $this->resolver->resolver(1, null, null, false, null, now());

        $this->assertSame(EstadoVendedorOperacionPdv::NoActivado, $estado);
    }

    public function test_marca_no_llego_tiene_prioridad(): void
    {
        $asistencia = new EquipoAsistenciaDiaPdv([
            'no_llego_at' => now(),
        ]);

        $estado = $this->resolver->resolver(1, null, null, false, $asistencia, now());

        $this->assertSame(EstadoVendedorOperacionPdv::NoLlego, $estado);
    }

    public function test_atencion_abierta_es_atendiendo(): void
    {
        $jornada = JornadaPdv::factory()->create([
            'estado' => EstadoJornadaPdv::Abierta,
        ]);

        $estado = $this->resolver->resolver(
            $jornada->sucursal_id,
            $jornada,
            null,
            true,
            null,
            now(),
        );

        $this->assertSame(EstadoVendedorOperacionPdv::Atendiendo, $estado);
    }

    public function test_cerrada_con_atencion_con_atencion_abierta_es_cierre_pendiente(): void
    {
        $jornada = JornadaPdv::factory()->cerradaConAtencion()->create();

        $estado = $this->resolver->resolver(
            $jornada->sucursal_id,
            $jornada,
            null,
            true,
            null,
            now(),
        );

        $this->assertSame(EstadoVendedorOperacionPdv::CierrePendiente, $estado);
    }

    public function test_jornada_abierta_disponible_recibe_turnos(): void
    {
        $jornada = JornadaPdv::factory()->create([
            'estado' => EstadoJornadaPdv::Abierta,
        ]);
        $intervalo = IntervaloOperativoPdv::factory()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $jornada->user_id,
            'sucursal_id' => $jornada->sucursal_id,
            'tipo' => TipoIntervaloOperativoPdv::Disponible,
            'fin_at' => null,
        ]);

        $estado = $this->resolver->resolver(
            $jornada->sucursal_id,
            $jornada,
            $intervalo,
            false,
            null,
            now(),
        );

        $this->assertSame(EstadoVendedorOperacionPdv::Disponible, $estado);
        $this->assertTrue($estado->recibeTurnos());
    }

    public function test_pausa_es_en_retencion(): void
    {
        $jornada = JornadaPdv::factory()->create([
            'estado' => EstadoJornadaPdv::Abierta,
        ]);
        $intervalo = IntervaloOperativoPdv::factory()->enPausa()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $jornada->user_id,
            'sucursal_id' => $jornada->sucursal_id,
            'fin_at' => null,
        ]);

        $estado = $this->resolver->resolver(
            $jornada->sucursal_id,
            $jornada,
            $intervalo,
            false,
            null,
            now(),
        );

        $this->assertSame(EstadoVendedorOperacionPdv::EnRetencion, $estado);

        $cronometro = $this->resolver->serializarCronometro($estado, $jornada, $intervalo);
        $this->assertSame('Tiempo en pausa', $cronometro['etiqueta'] ?? null);
    }

    public function test_cerrada_con_atencion_es_cierre_pendiente(): void
    {
        $jornada = JornadaPdv::factory()->cerradaConAtencion()->create();

        $estado = $this->resolver->resolver(
            $jornada->sucursal_id,
            $jornada,
            null,
            false,
            null,
            now(),
        );

        $this->assertSame(EstadoVendedorOperacionPdv::CierrePendiente, $estado);
    }

    public function test_jornada_cerrada_hoy_es_jornada_cerrada(): void
    {
        $jornada = JornadaPdv::factory()->cerrada()->create([
            'apertura_at' => now(),
            'cierre_at' => now(),
        ]);

        $estado = $this->resolver->resolver(
            $jornada->sucursal_id,
            $jornada,
            null,
            false,
            null,
            now(),
        );

        $this->assertSame(EstadoVendedorOperacionPdv::JornadaCerrada, $estado);
    }

    public function test_acciones_disponibles_por_estado(): void
    {
        $this->assertSame(
            ['activar', 'no_llego'],
            $this->resolver->accionesDisponibles(EstadoVendedorOperacionPdv::NoActivado),
        );
        $this->assertSame(
            ['cerrar_jornada'],
            $this->resolver->accionesDisponibles(EstadoVendedorOperacionPdv::Atendiendo),
        );
        $this->assertSame(
            ['cancelar_cierre_pendiente'],
            $this->resolver->accionesDisponibles(EstadoVendedorOperacionPdv::CierrePendiente),
        );
        $this->assertSame(
            ['reactivar'],
            $this->resolver->accionesDisponibles(EstadoVendedorOperacionPdv::JornadaCerrada),
        );
    }
}
