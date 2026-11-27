<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoTipoGuiaPedido;
use App\Models\ControlPedidos\CatalogoZonaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Departamento;
use App\Models\User;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\Nacional\BarreraSeguroCajaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\ControlPedidos\Concerns\EscenarioMultienvioNacional;
use Tests\TestCase;

/** A4: cada miembro de un Multienvío cotiza, se envía al Auxiliar y se audita como un pedido de una caja. */
class MultienvioNacionalRecorridoVentasTest extends TestCase
{
    use EscenarioMultienvioNacional;
    use RefreshDatabase;

    private User $ventas;

    private User $auxiliar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
        Storage::disk('public')->put('pedidos_bma/soporte.pdf', 'PDF');
        // El alcance del Auxiliar es por departamento (VisibilidadPedidoBma::idsVendedoresVisibles).
        $depto = Departamento::query()->create(['nombre' => 'Ventas MEV', 'activo' => true]);
        $this->ventas = User::factory()->create(['departamento_id' => $depto->id]);
        $this->ventas->givePermissionTo(Permission::findOrCreate('control_pedidos.crear', 'web'));
        $this->auxiliar = User::factory()->create(['departamento_id' => $depto->id]);
        $this->auxiliar->givePermissionTo(Permission::findOrCreate('control_pedidos.auditar', 'web'));
        foreach ([
            CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO => ['MEV-PR', 'Pesaje respondido', 1],
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR => ['MEV-PA', 'Pendiente auxiliar', 2],
        ] as $fase => [$codigo, $nombre, $orden]) {
            CatalogoEstatusPedido::query()->firstOrCreate(['fase_ciclo' => $fase], ['codigo_interno' => $codigo, 'nombre_visual' => $nombre, 'color_hex' => '#999999', 'orden' => $orden, 'activo' => true]);
        }
    }

    public function test_cada_miembro_cotiza_se_envia_al_auxiliar_y_se_audita_por_separado(): void
    {
        $cliente = Cliente::query()->create([
            'numero_cliente' => 'R270001', 'nombre' => 'Cliente multienvío', 'vendedor_id' => $this->ventas->id,
            'lista_actual_id' => DB::table('catalogo_listas_descuento')->insertGetId(['nombre' => 'Lista MEV', 'activo' => true]),
        ]);
        $direccion = ClienteDireccion::query()->create([
            'cliente_id' => $cliente->id, 'numero_direccion' => 1, 'tipo_direccion' => ClienteDireccion::TIPO_ENVIO,
            'nombre_destinatario' => 'Cliente', 'telefono_destinatario' => '5551234567', 'calle' => 'Calle 1', 'numero_exterior' => '1',
            'colonia' => 'Centro', 'municipio' => 'Centro', 'ciudad' => 'Centro', 'estado' => 'Tabasco', 'codigo_postal' => '86000',
            'pais' => 'México', 'esta_activa' => true, 'estado_verificacion' => ClienteDireccion::ESTADO_VERIFIED, 'origen' => ClienteDireccion::ORIGEN_MANUAL,
        ]);
        $zona = CatalogoZonaPedido::query()->create(['nombre' => 'Sin cargo MEV', 'activo' => true, 'costo_adicional' => 0]);
        $tipoGuia = CatalogoTipoGuiaPedido::query()->create(['nombre' => 'Guía MEV', 'activo' => true]);

        // 1. Decisión, borrador y confirmación por las rutas HTTP.
        [$origen, $grupo, $cajas] = $this->escenario(['cliente_id' => $cliente->id, 'cliente_direccion_id' => $direccion->id, 'codigo_postal' => '86000', 'domicilio_entrega' => 'Calle 1 1, Centro, Tabasco']); // decisión «activar» (la ruta HTTP de decisión ya la cubre MultienvioNacionalHttpTest)
        $revision = $grupo->revision_consulta;
        $this->actingAs($this->ventas);
        $this->putJson('/control-pedidos/'.$origen->id.'/multienvio/borrador', ['envios' => $this->enviosHttp($cajas)])->assertOk();
        $this->postJson('/control-pedidos/'.$origen->id.'/multienvio/confirmar', ['revision' => $revision])->assertOk()->assertJsonPath('grupo.estado', 'activo');

        $miembros = PedidoBma::query()->where('multienvio_nacional_id', $origen->fresh()->multienvio_nacional_id)->orderBy('multienvio_orden')->get();
        $this->assertCount(3, $miembros);

        // 2. Cotización con la ruta existente, miembro por miembro.
        $fletes = [100, 120, 130];
        $mercancia = [600, 500, 400];
        $esperado = [];
        foreach ($miembros as $i => $miembro) {
            $caja = $miembro->cajas()->firstOrFail();
            $lineas = [['uuid_operativo' => $caja->uuid_operativo, 'costo_envio' => $fletes[$i]]];

            $vista = $this->postJson('/control-pedidos/'.$miembro->id.'/cotizacion-nacional/vista-previa', [
                'catalogo_paqueteria_id' => $miembro->catalogo_paqueteria_id, 'catalogo_zona_id' => $zona->id, 'aplica_seguro' => true, 'lineas' => $lineas,
            ])->assertOk()->assertJsonPath('venta', $mercancia[$i])->assertJsonPath('flete', $fletes[$i])->assertJsonPath('cobertura_seguro', true)->json();

            $fresco = $miembro->fresh();
            $this->postJson('/control-pedidos/'.$miembro->id.'/paqueteria-nacional', [
                'catalogo_paqueteria_id' => $miembro->catalogo_paqueteria_id, 'solicitud_id' => (string) Str::uuid(),
                'revision_esperada' => (int) $fresco->revision_registro, 'base_captura' => RevisionCapturaPedido::base($fresco),
                'aplica_seguro' => true, 'lineas' => $lineas,
                'cotizacion' => ['cliente_direccion_id' => $direccion->id, 'catalogo_tipo_guia_id' => $tipoGuia->id, 'catalogo_zona_id' => $zona->id],
            ])->assertOk();

            $guardado = $miembro->fresh();
            $this->assertEquals($vista['seguro'], (float) $guardado->costo_seguro, 'seguro vista previa == guardado (miembro '.($i + 1).')');
            $this->assertEquals($vista['flete'], (float) $guardado->costo_envio);
            $this->assertEquals($vista['total'], (float) $guardado->total_a_cobrar, 'total vista previa == guardado');
            $this->assertEquals($mercancia[$i], (float) $guardado->total_mercancia, 'sin mercancía de otro miembro');
            $this->assertTrue((bool) $guardado->aplica_seguro);
            $this->assertSame(1, $guardado->cajas()->count());
            BarreraSeguroCajaNacional::assert($guardado); // 1 caja: no bloquea
            $esperado[$i] = (float) $vista['total'];
        }
        // Valores reales del servicio (spec: 65.00/63.40/61.60 y 765.00/683.40/591.60).
        $this->assertEqualsWithDelta([765.00, 683.40, 591.60], $esperado, 0.001);

        // 3. Pago de mercancía + envío al Auxiliar por la ruta existente.
        foreach ($miembros as $i => $miembro) {
            $this->actingAs($this->ventas);
            app(RegistrarPagoPedidoBmaService::class)->handle($miembro->fresh(), ['monto' => $esperado[$i], 'forma_pago' => 'efectivo'], UploadedFile::fake()->create('pago'.$i.'.pdf', 10, 'application/pdf'), $this->ventas->id);
            $r = $this->putJson('/control-pedidos/'.$miembro->id.'/enviar', []); $this->assertSame(200, $r->status(), (string) $r->getContent());
            $this->assertSame(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR, $miembro->fresh()->estatus->fase_ciclo);
        }

        // 4. El Auxiliar los ve a los tres, cada uno por separado.
        $lista = $this->actingAs($this->auxiliar)->getJson(route('control_pedidos.auditar.listado', ['tab' => 'PENDIENTES']))->assertOk()->json('pedidos');
        $ids = collect(data_get($lista, 'data', $lista))->pluck('id')->all();
        foreach ($miembros as $miembro) {
            $this->assertContains($miembro->id, $ids);
            $this->getJson(route('control_pedidos.auditar.detalle', $miembro))->assertOk();
        }

        // Validar el pago de un miembro no valida a los demás.
        $this->postJson(route('control_pedidos.auditar.validar_pago', $miembros[0]))->assertStatus(302)->assertSessionHasNoErrors();
        $this->assertNotNull($miembros[0]->fresh()->pago_validado_at);
        $this->assertNull($miembros[1]->fresh()->pago_validado_at);
        $this->assertNull($miembros[2]->fresh()->pago_validado_at);

        // 5. Registrar un pago en un miembro no cambia el pendiente de los otros.
        $cobertura = app(CoberturaPagoPedidoBmaService::class);
        $antes = $miembros->map(fn ($m) => $cobertura->calcular($m->fresh())['pendiente'])->all();
        app(RegistrarPagoPedidoBmaService::class)->handle($miembros[1]->fresh(), ['monto' => 10, 'forma_pago' => 'efectivo'], UploadedFile::fake()->create('extra.pdf', 10, 'application/pdf'), $this->auxiliar->id);
        $despues = $miembros->map(fn ($m) => $cobertura->calcular($m->fresh())['pendiente'])->all();
        $this->assertSame($antes[0], $despues[0]);
        $this->assertSame($antes[2], $despues[2]);
    }

    private function enviosHttp(array $cajas): array
    {
        $envios = [];
        foreach ($cajas as $i => $caja) {
            $envios[] = ['uuid_operativo' => $caja->uuid_operativo, 'folio_remision' => ['F-A', 'F-B', 'F-C'][$i], 'total_mercancia' => [600, 500, 400][$i], 'contenido' => 'Caja '.($i + 1)];
        }

        return $envios;
    }
}
