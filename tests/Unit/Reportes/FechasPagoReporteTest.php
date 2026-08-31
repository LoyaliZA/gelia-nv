<?php

namespace Tests\Unit\Reportes;

use App\Support\Reportes\FechasPagoReporte;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class FechasPagoReporteTest extends TestCase
{
    public function test_formatear_sin_fecha_devuelve_sin_informacion(): void
    {
        $this->assertSame(FechasPagoReporte::SIN_INFORMACION, FechasPagoReporte::formatear(null));
    }

    public function test_reportado_posteriormente_cuando_reporte_es_dia_despues(): void
    {
        $pago = Carbon::parse('2026-08-29 14:00:00');
        $reportada = Carbon::parse('2026-08-30 09:00:00');

        $this->assertTrue(FechasPagoReporte::reportadoPosteriormente($pago, $reportada));
    }

    public function test_reportado_posteriormente_falso_mismo_dia(): void
    {
        $pago = Carbon::parse('2026-08-29 14:00:00');
        $reportada = Carbon::parse('2026-08-29 18:00:00');

        $this->assertFalse(FechasPagoReporte::reportadoPosteriormente($pago, $reportada));
    }

    public function test_reportado_posteriormente_falso_sin_fecha_pago(): void
    {
        $this->assertFalse(FechasPagoReporte::reportadoPosteriormente(null, Carbon::now()));
    }

    public function test_iso8601_no_inventa_fecha(): void
    {
        $this->assertNull(FechasPagoReporte::iso8601(null));
    }
}
