<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\ImportarDocumentosEscalonamiento;
use App\Services\Escalonamiento\ReiniciarDocumentosPeriodoEscalonamiento;
use App\Services\Escalonamiento\SincronizarReglasPeriodosEscalonamiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParticipacionCatalogoEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_activar_participacion_en_catalogo_permite_reaplicar_misma_remision(): void
    {
        Storage::fake('local');

        $lista = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO TEST',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => false,
        ]);
        Cliente::create([
            'numero_cliente' => '9200',
            'nombre' => 'Cliente Participacion',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);
        $csv = $this->csv('participa.csv', [
            ['remision', 'P-1', '', '', '9200', '', 'MXN', '500.00', '2026-10-05', 'activo', ''],
        ]);

        $primera = $importar->previsualizar($periodo, $csv, null, 'remision');
        $this->assertSame('excluido', $primera->filas->first()->resultado);
        $importar->confirmar($primera->id, null);
        $this->assertSame(0, EscalonamientoMovimiento::count());

        $lista->update(['participa_escalonamiento' => true]);

        $segunda = $importar->previsualizar($periodo, $csv, null, 'remision');
        $this->assertSame('revision', $segunda->filas->first()->resultado);
        $importar->confirmar($segunda->id, null);

        $this->assertSame(1, EscalonamientoMovimiento::count());
        $this->assertEquals('500.00', (string) EscalonamientoMovimiento::first()->efecto);
    }

    public function test_reiniciar_periodo_permite_carga_limpia(): void
    {
        Storage::fake('local');

        $lista = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO REINICIO',
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        Cliente::create([
            'numero_cliente' => '9201',
            'nombre' => 'Cliente Reinicio',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);

        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        app(SincronizarReglasPeriodosEscalonamiento::class)->sincronizarPeriodosEditables();

        $importar = app(ImportarDocumentosEscalonamiento::class);
        $csv = $this->csv('reinicio.csv', [
            ['remision', 'R-1', '', '', '9201', '', 'MXN', '100.00', '2026-10-05', 'activo', ''],
        ]);
        $importacion = $importar->previsualizar($periodo, $csv, null, 'remision');
        $importar->confirmar($importacion->id, null);
        $this->assertSame(1, EscalonamientoMovimiento::count());

        app(ReiniciarDocumentosPeriodoEscalonamiento::class)->reiniciar($periodo);
        $this->assertSame(0, EscalonamientoMovimiento::count());

        $otra = $importar->previsualizar($periodo, $csv, null, 'remision');
        $this->assertSame('alta', $otra->filas->first()->resultado);
    }

    /**
     * @param  list<list<string>>  $filas
     */
    private function csv(string $nombre, array $filas): UploadedFile
    {
        $lineas = ['tipo,folio,serie,sucursal,numero_cliente,nombre,moneda,total,fecha,estado,remision_original'];
        foreach ($filas as $fila) {
            $lineas[] = implode(',', $fila);
        }

        return UploadedFile::fake()->createWithContent($nombre, implode("\n", $lineas));
    }
}
