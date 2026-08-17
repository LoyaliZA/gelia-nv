<?php

namespace Tests\Feature\Solicitudes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\HistorialMontoCliente;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Clientes\RegistrarHistorialMontoClienteService;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MontoSolicitudVsCargaMasivaTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoListaDescuento $listaPg;
    private CatalogoListaDescuento $listaBronce;
    private CatalogoListaDescuento $listaPlata;
    private CatalogoProceso $proceso;
    private User $vendedor;
    private int $idPendiente;
    private int $idRespondida;
    private int $idIncorrecta;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta'] as $estado) {
            CatalogoEstadoSolicitud::create(['nombre' => $estado, 'activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->idPendiente = (int) CatalogoEstadoSolicitud::idDe('Pendiente');
        $this->idRespondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        $this->idIncorrecta = (int) CatalogoEstadoSolicitud::idDe('Incorrecta');

        $this->listaPg = CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
        ]);
        $this->listaBronce = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO BRONCE',
            'monto_requerido' => 0.01,
            'activo' => true,
        ]);
        $this->listaPlata = CatalogoListaDescuento::create([
            'nombre' => 'MAYOREO PLATA',
            'monto_requerido' => 5104,
            'activo' => true,
        ]);

        $this->proceso = CatalogoProceso::create([
            'nombre' => 'CAMBIO DE LISTA',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        $this->vendedor = User::factory()->create(['name' => 'Vendedora Test']);
    }

    public function test_aprobar_no_suma_monto_precargado(): void
    {
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, 6000, $this->listaPlata->id);

        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud);

        $cliente->refresh();
        $this->assertEquals(1000.0, (float) $cliente->monto_venta_actual);
        $this->assertSame($this->listaPlata->id, $cliente->lista_actual_id);
        $this->assertSame(0, HistorialMontoCliente::count());
    }

    public function test_vencer_no_resta_historial_si_nunca_se_sumo(): void
    {
        Notification::fake();
        User::factory()->create();
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, 6000, $this->listaPlata->id, $this->idRespondida);
        $solicitud->created_at = now()->subHours(25);
        $solicitud->save();
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud);

        Artisan::call('pagos:rechazar-vencidos');

        $cliente->refresh();
        $solicitud->refresh();
        $this->assertEquals(1000.0, (float) $cliente->monto_venta_actual);
        $this->assertSame($this->listaBronce->id, $cliente->lista_actual_id);
        $this->assertSame($this->idIncorrecta, (int) $solicitud->catalogo_estado_solicitud_id);
        $this->assertSame(0, HistorialMontoCliente::count());
    }

    public function test_vencer_solo_pela_la_capa_de_la_solicitud(): void
    {
        $cliente = $this->cliente(13000);
        $solicitud = $this->solicitud($cliente, 3000, $this->listaPlata->id, $this->idRespondida);
        $solicitud->update(['monto_aplicado_al_cliente' => 3000]);

        app(AjustarMontoPorSolicitudService::class)->revertirBeneficios($solicitud);

        $this->assertEquals(10000.0, (float) $cliente->fresh()->monto_venta_actual);
        $this->assertEquals(0.0, (float) $solicitud->fresh()->monto_aplicado_al_cliente);
    }

    public function test_carga_sin_la_remision_no_deja_que_el_vencimiento_coma_historial(): void
    {
        $cliente = $this->cliente(13000);
        $solicitud = $this->solicitud($cliente, 3000, $this->listaPlata->id, $this->idRespondida);
        $solicitud->update(['monto_aplicado_al_cliente' => 3000]);

        $this->importar("numero_cliente,nombre,monto_venta_actual\n{$cliente->numero_cliente},Cliente Test,10500\n");

        $solicitud->refresh();
        $this->assertFalse((bool) $solicitud->cubierto_por_carga_masiva);
        $this->assertEquals(0.0, (float) $solicitud->monto_aplicado_al_cliente);
        $this->assertEquals(10500.0, (float) $cliente->fresh()->monto_venta_actual);

        app(AjustarMontoPorSolicitudService::class)->revertirBeneficios($solicitud);

        $this->assertEquals(10500.0, (float) $cliente->fresh()->monto_venta_actual);
    }

    public function test_confirmar_pago_suma_si_wizerp_aun_no_cubre(): void
    {
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, 6000, $this->listaPlata->id, $this->idRespondida);
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud);

        app(AjustarMontoPorSolicitudService::class)->aplicarPagoConfirmado($solicitud, 6000, $this->vendedor->id);

        $cliente->refresh();
        $solicitud->refresh();
        $this->assertEquals(7000.0, (float) $cliente->monto_venta_actual);
        $this->assertEquals(6000.0, (float) $solicitud->monto_aplicado_al_cliente);
        $this->assertDatabaseHas('historial_montos_clientes', [
            'cliente_id' => $cliente->id,
            'origen' => RegistrarHistorialMontoClienteService::ORIGEN_SOLICITUD_PAGO,
            'monto_operacion' => 6000,
        ]);
    }

    public function test_carga_que_ya_trae_la_venta_evita_duplicar_al_confirmar(): void
    {
        $cliente = $this->cliente(1000);
        $solicitud = $this->solicitud($cliente, 6000, $this->listaPlata->id, $this->idRespondida);

        $this->importar("numero_cliente,nombre,monto_venta_actual\n{$cliente->numero_cliente},Cliente Test,7000\n");

        $solicitud->refresh();
        $this->assertTrue((bool) $solicitud->cubierto_por_carga_masiva);

        app(AjustarMontoPorSolicitudService::class)->aplicarPagoConfirmado($solicitud, 6000, $this->vendedor->id);

        $this->assertEquals(7000.0, (float) $cliente->fresh()->monto_venta_actual);
        $this->assertEquals(
            0,
            HistorialMontoCliente::where('origen', RegistrarHistorialMontoClienteService::ORIGEN_SOLICITUD_PAGO)->count()
        );
    }

    private function cliente(float $monto): Cliente
    {
        return Cliente::create([
            'numero_cliente' => '4401',
            'nombre' => 'Cliente Test',
            'lista_actual_id' => $this->listaBronce->id,
            'monto_venta_actual' => $monto,
        ]);
    }

    private function solicitud(Cliente $cliente, float $monto, int $listaId, ?int $estadoId = null): SolicitudTag
    {
        return SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_estado_solicitud_id' => $estadoId ?? $this->idPendiente,
            'catalogo_lista_descuento_id' => $listaId,
            'monto_cotizado' => $monto,
            'pago_confirmado' => false,
        ]);
    }

    private function importar(string $csv): void
    {
        $path = tempnam(sys_get_temp_dir(), 'solicitud-carga-');
        file_put_contents($path, $csv);
        $archivo = new UploadedFile($path, 'test.csv', 'text/csv', null, true);
        app(ImportarClientesWizerpService::class)->ejecutar($archivo);
    }
}
