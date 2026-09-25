<?php

namespace Tests\Unit\Traspasos;

use App\Models\Almacen;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\Cliente;
use App\Models\CatalogoListaDescuento;
use App\Models\Producto;
use App\Models\SolicitudTraspaso;
use App\Models\SolicitudTraspasoProducto;
use App\Models\SolicitudTraspasoRevisionProducto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Traspasos\GuardarRevisionesTraspasoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuardarRevisionesTraspasoServiceTest extends TestCase
{
    use RefreshDatabase;

    private GuardarRevisionesTraspasoService $svc;

    private SolicitudTraspaso $solicitud;

    private SolicitudTraspasoProducto $linea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(GuardarRevisionesTraspasoService::class);

        CatalogoEstadoSolicitud::create(['nombre' => 'Pendiente', 'activo' => true]);
        CatalogoEstadoSolicitud::reiniciarCache();

        $lista = CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'numero_cliente' => '2001',
            'nombre' => 'Cliente Rev',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);

        $almacen = Almacen::create([
            'codigo' => 'ALM-R',
            'nombre' => 'Almacén Rev',
            'activo' => true,
            'visible_en_traspasos' => true,
        ]);

        $sucursal = Sucursal::factory()->create();
        $vendedor = User::factory()->create();
        $vendedor->concederAccesoSucursal($sucursal, esPrincipal: true);

        $producto = Producto::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 800001,
            'sku' => 'SKU-REV',
            'descripcion' => 'Pieza revisión',
            'activo' => true,
        ]);

        $this->solicitud = SolicitudTraspaso::create([
            'folio' => 'TRA-2026-1',
            'vendedor_id' => $vendedor->id,
            'departamento_id' => null,
            'sucursal_solicitante_id' => $sucursal->id,
            'cliente_id' => $cliente->id,
            'almacen_origen_id' => $almacen->id,
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Pendiente'),
            'total_piezas' => 2,
        ]);

        $this->linea = SolicitudTraspasoProducto::create([
            'solicitud_traspaso_id' => $this->solicitud->id,
            'producto_id' => $producto->id,
            'sku' => $producto->sku,
            'descripcion' => $producto->descripcion,
            'piezas' => 2,
        ]);
    }

    public function test_normalizar_exige_evidencia_en_malo(): void
    {
        $this->expectException(ValidationException::class);

        $this->svc->normalizarRevisiones($this->solicitud, [
            [
                'solicitud_traspaso_producto_id' => $this->linea->id,
                'producto_id' => $this->linea->producto_id,
                'estado_fisico' => 'malo',
                'comentario' => 'Rayón visible',
            ],
            [
                'solicitud_traspaso_producto_id' => $this->linea->id,
                'producto_id' => $this->linea->producto_id,
                'estado_fisico' => 'bueno',
            ],
        ]);
    }

    public function test_persistir_guarda_piezas_y_estado_general(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();

        $normalizadas = $this->svc->normalizarRevisiones($this->solicitud, [
            [
                'solicitud_traspaso_producto_id' => $this->linea->id,
                'producto_id' => $this->linea->producto_id,
                'estado_fisico' => 'bueno',
            ],
            [
                'solicitud_traspaso_producto_id' => $this->linea->id,
                'producto_id' => $this->linea->producto_id,
                'estado_fisico' => 'bueno',
            ],
        ]);

        $general = $this->svc->persistir(
            $this->solicitud,
            SolicitudTraspasoRevisionProducto::MOMENTO_ORIGEN,
            $normalizadas,
            $usuario
        );

        $this->assertSame('bueno', $general);
        $this->assertSame(2, SolicitudTraspasoRevisionProducto::where('momento', 'origen')->count());
    }

    public function test_normalizar_rechaza_conteo_incompleto(): void
    {
        $this->expectException(ValidationException::class);

        $this->svc->normalizarRevisiones($this->solicitud, [
            [
                'solicitud_traspaso_producto_id' => $this->linea->id,
                'producto_id' => $this->linea->producto_id,
                'estado_fisico' => 'bueno',
            ],
        ]);
    }
}
