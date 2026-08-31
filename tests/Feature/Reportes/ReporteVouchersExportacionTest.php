<?php

namespace Tests\Feature\Reportes;

use App\Jobs\GenerarReportePagosPedidosPdfJob;
use App\Models\Reportes\ReportePagosPedidosExportacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Pruebas HTTP de exportación vouchers (requieren esquema MySQL; en SQLite PHPUnit se omiten).
 */
class ReporteVouchersExportacionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite :memory: no ejecuta migraciones con information_schema.');
        }
    }

    public function test_solicitar_exportacion_vouchers_encola_job_y_guarda_tipo_reporte(): void
    {
        Bus::fake();

        Permission::findOrCreate('reportes.pagos_pedidos.exportar_csv');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('reportes.pagos_pedidos.exportar_csv');

        $response = $this->actingAs($usuario)->postJson(route('reportes.pagos_pedidos.exportar.solicitar'), [
            'tipo_reporte' => 'vouchers',
            'formato' => 'csv_resumen',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['job_id', 'exportacion']);

        $jobId = $response->json('job_id');
        Bus::assertDispatched(GenerarReportePagosPedidosPdfJob::class);

        $exportacion = ReportePagosPedidosExportacion::query()->findOrFail($jobId);
        $this->assertSame('vouchers', $exportacion->tipo_reporte);
        $this->assertSame(ReportePagosPedidosExportacion::ESTADO_PENDING, $exportacion->estado);
        $this->assertSame('csv_resumen', $exportacion->formato);
    }

    public function test_descarga_exportacion_vouchers_completada(): void
    {
        Storage::fake('local');
        Permission::findOrCreate('reportes.pagos_pedidos.exportar_csv');

        $usuario = User::factory()->create();
        $usuario->givePermissionTo('reportes.pagos_pedidos.exportar_csv');

        $path = 'reportes_pagos_pedidos/vouchers_test.csv';
        Storage::disk('local')->put($path, "Pedido,Monto\nG-1,100");

        $id = Str::uuid()->toString();
        ReportePagosPedidosExportacion::query()->create([
            'id' => $id,
            'user_id' => $usuario->id,
            'titulo' => 'Vouchers test',
            'formato' => 'csv_resumen',
            'tipo_reporte' => 'vouchers',
            'estado' => ReportePagosPedidosExportacion::ESTADO_COMPLETED,
            'nombre_archivo' => 'vouchers_test.csv',
            'ruta_archivo' => $path,
            'filtros' => ['tipo_reporte' => 'vouchers', 'formato' => 'csv_resumen'],
            'expira_at' => now()->addDay(),
            'completed_at' => now(),
        ]);

        $this->actingAs($usuario)->get(
            route('reportes.pagos_pedidos.exportar.descargar', ['exportacion' => $id])
        )->assertOk();
    }
}
