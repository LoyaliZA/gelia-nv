<?php

namespace Tests\Feature\Traspasos;

use App\Models\Almacen;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\Producto;
use App\Models\SolicitudTraspaso;
use App\Models\User;
use App\Notifications\AlertaTraspaso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudTraspasoFlujoTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;
    private User $encargada;
    private User $otroVendedor;
    private Almacen $almacen;
    private Cliente $cliente;
    private Producto $productoA;
    private Producto $productoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);

        foreach ([
            'traspasos.ver_listado',
            'traspasos.crear',
            'traspasos.responder',
            'traspasos.reportar_error',
            'traspasos.verificar',
            'traspasos.monitorear_alertas',
            'traspasos.reporte_dia',
            'traspasos.eliminar',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Administrador', 'web');

        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta'] as $estado) {
            CatalogoEstadoSolicitud::create([
                'nombre' => $estado,
                'activo' => true,
            ]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();

        $depto = Departamento::create(['nombre' => 'Ventas Test', 'activo' => true]);

        $this->vendedor = User::factory()->create(['name' => 'Vendedora Test']);
        $this->vendedor->givePermissionTo(['traspasos.ver_listado', 'traspasos.crear']);
        $this->vendedor->departamentos()->sync([$depto->id]);

        $this->encargada = User::factory()->create(['name' => 'Encargada Test']);
        $this->encargada->givePermissionTo(['traspasos.ver_listado', 'traspasos.responder', 'traspasos.verificar']);
        $this->encargada->departamentos()->sync([$depto->id]);

        $this->otroVendedor = User::factory()->create(['name' => 'Otra Vendedora']);
        $this->otroVendedor->givePermissionTo(['traspasos.ver_listado', 'traspasos.crear']);
        $this->otroVendedor->departamentos()->sync([$depto->id]);

        $this->almacen = Almacen::create([
            'codigo' => 'CEDIS',
            'nombre' => 'CEDIS Central',
            'activo' => true,
            'visible_en_traspasos' => true,
        ]);

        $lista = CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
        ]);

        $this->cliente = Cliente::create([
            'numero_cliente' => '1001',
            'nombre' => 'Cliente Traspaso',
            'lista_actual_id' => $lista->id,
            'monto_venta_actual' => 0,
        ]);

        $this->productoA = $this->crearProducto(['sku' => 'SKU-A', 'descripcion' => 'Pieza A']);
        $this->productoB = $this->crearProducto(['sku' => 'SKU-B', 'descripcion' => 'Pieza B']);
    }

    public function test_crear_responder_y_aislamiento_listado(): void
    {
        Notification::fake();
        Storage::fake('public');

        $this->actingAs($this->vendedor)
            ->post(route('traspasos.store'), [
                'numero_cliente' => '1001',
                'almacen_origen_id' => $this->almacen->id,
                'productos' => [
                    ['producto_id' => $this->productoA->id, 'piezas' => 3],
                    ['producto_id' => $this->productoB->id, 'piezas' => 2],
                ],
            ])
            ->assertRedirect();

        $solicitud = SolicitudTraspaso::first();
        $this->assertNotNull($solicitud);
        $this->assertMatchesRegularExpression('/^TRA-\d{4}-\d{5}$/', $solicitud->folio);
        $this->assertSame(5, $solicitud->total_piezas);
        $this->assertCount(2, $solicitud->productos);
        $this->assertSame($this->vendedor->id, $solicitud->vendedor_id);

        Notification::assertSentTo($this->encargada, AlertaTraspaso::class, function (AlertaTraspaso $n) {
            return $n->tipoAlerta === 'nueva';
        });

        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');

        $this->actingAs($this->encargada)
            ->put(route('traspasos.actualizar_estado', $solicitud->id), [
                'catalogo_estado_solicitud_id' => $idRespondida,
                'folio_traspaso' => 'EXT-999',
                'motivo' => 'Traspaso generado',
                'evidencia_respuesta' => UploadedFile::fake()->image('captura.jpg'),
            ])
            ->assertRedirect();

        $solicitud->refresh();
        $this->assertSame('EXT-999', $solicitud->folio_traspaso);
        $this->assertSame($idRespondida, $solicitud->catalogo_estado_solicitud_id);
        $this->assertNotEmpty($solicitud->evidencia_respuesta_path);
        $this->assertSame('Traspaso generado', $solicitud->motivo_respuesta);

        $auditoriaRespuesta = $solicitud->auditorias()->whereNotNull('estado_anterior_id')->latest('id')->first();
        $this->assertNotNull($auditoriaRespuesta);
        $this->assertSame('EXT-999', $auditoriaRespuesta->datos_snapshot['folio_traspaso'] ?? null);
        $this->assertSame($solicitud->evidencia_respuesta_path, $auditoriaRespuesta->datos_snapshot['evidencia_respuesta_path'] ?? null);

        Notification::assertSentTo($this->vendedor, AlertaTraspaso::class, function (AlertaTraspaso $n) {
            return $n->tipoAlerta === 'respondida';
        });

        // Aislamiento: otro vendedor no ve el folio ajeno
        $this->actingAs($this->otroVendedor)
            ->get(route('traspasos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Traspasos/Index', false)
                ->has('traspasos.data', 0)
            );

        // Dueño sí lo ve con respuesta + bitácora completa
        $this->actingAs($this->vendedor)
            ->get(route('traspasos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Traspasos/Index', false)
                ->has('traspasos.data', 1)
                ->where('traspasos.data.0.folio', $solicitud->folio)
                ->where('traspasos.data.0.folio_traspaso', 'EXT-999')
                ->where('traspasos.data.0.motivo_respuesta', 'Traspaso generado')
                ->where('traspasos.data.0.tiene_evidencia_respuesta', true)
                ->has('traspasos.data.0.auditorias', 2)
            );
    }

    private function crearProducto(array $attrs = []): Producto
    {
        static $folio = 900000;
        $folio++;

        return Producto::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'folio' => $folio,
            'sku' => 'SKU' . $folio,
            'descripcion' => 'Producto ' . $folio,
            'activo' => true,
        ], $attrs));
    }
}
