<?php

namespace Tests\Feature\ControlPedidos;

use App\Models\Almacen;
use App\Models\Sucursal;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\User;
use App\Services\ControlPedidos\CalcularRequisitosPreparacionService;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\ResponderPesajePedidoBmaService;
use App\Services\ControlPedidos\SolicitarPesajePedidoBmaService;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElegibilidadPesajePedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->mock(NotificarPedidoBmaService::class)->shouldReceive('ejecutar')->zeroOrMoreTimes();
        $this->usuario = User::factory()->create();
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_BORRADOR],
            ['codigo_interno' => 'BORRADOR_Q5', 'nombre_visual' => 'Borrador', 'color_hex' => '#999999', 'orden' => 1, 'activo' => true]
        );
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE],
            ['codigo_interno' => 'PESAJE_Q5', 'nombre_visual' => 'Pesaje', 'color_hex' => '#999999', 'orden' => 2, 'activo' => true]
        );
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO],
            ['codigo_interno' => 'PESAJE_OK_Q5', 'nombre_visual' => 'Pesaje listo', 'color_hex' => '#999999', 'orden' => 3, 'activo' => true]
        );
        CatalogoEstatusPedido::query()->firstOrCreate(
            ['fase_ciclo' => CatalogoEstatusPedido::FASE_ENVIADO],
            ['codigo_interno' => 'ENVIADO_Q5', 'nombre_visual' => 'Enviado', 'color_hex' => '#999999', 'orden' => 8, 'activo' => true]
        );
    }

    public function test_envio_cedis_con_logistica_exige_cajas_y_peso_aunque_la_tarifa_local_no_pese(): void
    {
        $pedido = $this->pedidoListo(true, CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL, false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('peso real (pesaje CEDIS)');
        $this->validar($pedido);
    }

    public function test_envio_cedis_con_pesaje_completo_no_hereda_el_bloqueo_de_peso(): void
    {
        $pedido = $this->pedidoListo(true, CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, true);
        $tipo = CatalogoTipoCajaPedido::query()->create(['nombre' => 'Caja Q5', 'activo' => true]);
        $pedido->update([
            'peso_real_kg' => 1.5,
            'catalogo_tipo_caja_id' => $tipo->id,
            'numero_cajas' => 1,
        ]);
        PedidoBmaCaja::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_tipo_caja_id' => $tipo->id,
            'cantidad' => 1,
            'orden' => 1,
            'peso_real_kg' => 1.5,
            'peso_volumetrico_kg' => 1.2,
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
        ]);

        $this->validar($pedido->fresh());
        $this->assertTrue(true);
    }

    public function test_preparacion_bodega_pide_el_peso_de_la_tarea_y_no_el_detalle_cedis(): void
    {
        $pedido = $this->pedidoListo(true, CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, true);
        $tarea = $this->tarea($pedido, PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_BODEGA_NORMAL, true);

        try {
            $this->validar($pedido->fresh());
            $this->fail('La modalidad de bodega exige el peso de la preparación.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('peso real (preparación de Tienda)', $e->getMessage());
            $this->assertStringNotContainsString('pesaje CEDIS', $e->getMessage());
            $this->assertStringNotContainsString('detalle de envíos', $e->getMessage());
        }

        $tarea->update(['peso_real_kg' => 1.25]);

        $this->validar($pedido->fresh());
        $this->assertSame(0, $pedido->cajas()->count());
    }

    public function test_municipio_exige_peso_de_preparacion_solo_si_el_transporte_lo_pide(): void
    {
        $sinPeso = $this->pedidoListo(true, CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL, false);
        $this->tarea($sinPeso, PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO, false, $sinPeso->catalogo_paqueteria_id);
        $this->validar($sinPeso->fresh());

        $conPeso = $this->pedidoListo(true, CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL, true);
        $this->tarea($conPeso, PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA, CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO, false, $conPeso->catalogo_paqueteria_id);

        try {
            $this->validar($conPeso->fresh());
            $this->fail('El transporte municipal con peso debe bloquear el avance.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('peso real (preparación de Tienda)', $e->getMessage());
            $this->assertStringNotContainsString('pesaje CEDIS', $e->getMessage());
            $this->assertStringNotContainsString('detalle de envíos', $e->getMessage());
        }
    }

    public function test_nombre_de_departamento_no_cambia_la_obligacion_de_pesaje(): void
    {
        foreach (['Aromas Q5', 'Bellaroma Q5'] as $nombre) {
            $pedido = $this->pedidoListo(true, CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, true);
            $pedido->origen->update(['nombre' => $nombre]);

            try {
                $this->validar($pedido->fresh());
                $this->fail($nombre);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('peso real (pesaje CEDIS)', $e->getMessage());
            }
        }
    }

    public function test_consulta_de_mercancia_sin_logistica_no_exige_kilogramos(): void
    {
        $pedido = $this->pedidoListo(false, null, null);

        $this->validar($pedido);
        $this->assertNull($pedido->fresh()->peso_real_kg);
    }

    public function test_respuesta_incompleta_de_pesaje_se_rechaza_y_la_de_mercancia_pide_piezas(): void
    {
        $logistica = $this->pedidoPendiente(true);
        try {
            app(ResponderPesajePedidoBmaService::class)->ejecutar($logistica, $this->usuario->id, []);
            $this->fail('El pesaje vacío no debe registrarse.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('al menos un envío', $e->getMessage());
        }
        $this->assertNull($logistica->fresh()->pesaje_respondido_at);

        $mercancia = $this->pedidoPendiente(false);
        try {
            app(ResponderPesajePedidoBmaService::class)->ejecutar($mercancia, $this->usuario->id, [], [
                'numero_cajas' => 1,
            ]);
            $this->fail('La consulta sin piezas no debe registrarse.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('al menos un producto', $e->getMessage());
        }
    }

    public function test_solicitud_duplicada_no_crea_otra_consulta(): void
    {
        $pedido = $this->pedidoListo(true, CatalogoPaqueteriaPedido::CATEGORIA_COMERCIAL, true);
        $borrador = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_BORRADOR)->firstOrFail();
        $pedido->update([
            'catalogo_estatus_pedido_id' => $borrador->id,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO,
            'pesaje_respondido_at' => null,
            'consulta_cerrada_at' => null,
        ]);
        $servicio = app(SolicitarPesajePedidoBmaService::class);
        $servicio->ejecutar($pedido, $this->usuario->id);
        $servicio->ejecutar($pedido->fresh(), $this->usuario->id);

        $this->assertSame(1, PedidoBmaHistorialEstado::query()->where('pedido_bma_id', $pedido->id)->count());
        $this->assertSame(PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE, $pedido->fresh()->estatus_envio);
    }

    public function test_pedido_enviado_no_acepta_pesaje_ni_consulta(): void
    {
        $pedido = $this->pedidoPendiente(true, CatalogoEstatusPedido::FASE_ENVIADO, PedidoBma::ESTATUS_ENVIO_COMPLETO);
        $this->assertFalse($pedido->puedeSolicitarPesaje());
        $this->assertFalse($pedido->puedeResponderPesaje());
    }

    public function test_empaque_municipal_pide_bascula_solo_cuando_el_transporte_exige_peso(): void
    {
        $servicio = app(CalcularRequisitosPreparacionService::class);
        $sinPeso = $this->tareaEmpaque(false);
        $conPeso = $this->tareaEmpaque(true);

        $faltantesSin = $servicio->validarEmpaqueMunicipio($sinPeso, ['peso_real_obligatorio' => false, 'evidencia_por_producto' => false]);
        $faltantesCon = $servicio->validarEmpaqueMunicipio($conPeso, ['peso_real_obligatorio' => true, 'evidencia_por_producto' => false]);

        $this->assertNotContains('Foto de báscula / peso', $faltantesSin);
        $this->assertContains('Foto del bulto', $faltantesSin);
        $this->assertContains('Foto de báscula / peso', $faltantesCon);
    }

    private function clienteId(): int
    {
        $ahora = now();
        $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista Q5 '.uniqid(),
            'activo' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        return (int) DB::table('clientes')->insertGetId([
            'numero_cliente' => substr(uniqid(), -8),
            'nombre' => 'Cliente Q5',
            'lista_actual_id' => $listaId,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    private function validar(PedidoBma $pedido): void
    {
        $validador = new class {
            use ValidacionCamposPedidoBma;

            public function check(PedidoBma $p): void
            {
                $this->validarCamposRequeridos($p);
            }
        };
        $validador->check($pedido->fresh());
    }

    private function pedidoListo(bool $logistica, ?string $categoria, ?bool $requierePeso): PedidoBma
    {
        $pedido = $this->pedidoPendiente($logistica, CatalogoEstatusPedido::FASE_PESAJE_RESPONDIDO, PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO);
        $attrs = [
            'folio_remision' => 'Q5-'.uniqid(),
            'cliente_id' => $this->clienteId(),
            'total_mercancia' => 100,
            'consulta_cerrada_at' => now(),
            'pesaje_respondido_at' => now(),
            'pesaje_respondido_por_id' => $this->usuario->id,
            'costo_envio' => 40,
            'catalogo_tipo_guia_id' => 1,
            'catalogo_zona_id' => 1,
            'codigo_postal' => '86000',
            'domicilio_entrega' => 'Calle de prueba 1',
        ];
        if (! $logistica) {
            $attrs['sucursal_destino_id'] = Sucursal::factory()->create(['activo' => true])->id;
        }
        if ($categoria !== null) {
            $attrs['catalogo_paqueteria_id'] = CatalogoPaqueteriaPedido::query()->create([
                'nombre' => 'Paq Q5 '.uniqid(),
                'categoria' => $categoria,
                'requiere_peso' => (bool) $requierePeso,
                'activo' => true,
                'habilitado_envio_municipio' => true,
                'modalidad_tarifa' => CatalogoPaqueteriaPedido::MODALIDAD_FIJA,
            ])->id;
        }
        $pedido->update($attrs);
        PedidoBmaDocumento::query()->create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
            'ruta_archivo' => 'q5/pedido.pdf',
            'nombre_original' => 'pedido.pdf',
            'mime_type' => 'application/pdf',
        ]);

        return $pedido->fresh();
    }

    private function pedidoPendiente(bool $logistica, string $fase = CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE, ?string $estatusEnvio = PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', $fase)->firstOrFail();
        $origen = CatalogoOrigenPedido::query()->create([
            'codigo' => 'Q5-'.uniqid(),
            'nombre' => ($logistica ? 'Envío Q5 ' : 'Tienda Q5 ').uniqid(),
            'requiere_logistica' => $logistica,
            'activo' => true,
        ]);
        $almacen = Almacen::query()->create([
            'codigo' => 'CEDIS-'.uniqid(),
            'nombre' => 'CEDIS Q5',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);

        return PedidoBma::query()->create([
            'folio' => 'Q5-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'origen_id' => $origen->id,
            'almacen_id' => $almacen->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'estatus_envio' => $estatusEnvio,
            'total_mercancia' => 0,
            'costo_envio' => 0,
            'saldo_a_favor' => 0,
            'costo_seguro' => 0,
            'total_a_cobrar' => 0,
            'aplica_seguro' => false,
            'es_resguardo' => false,
        ]);
    }

    private function tarea(PedidoBma $pedido, string $estado, string $codigo, bool $requierePesoModalidad, ?int $paqueteriaId = null): PedidoBmaTareaPreparacion
    {
        $modalidad = CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $codigo, 'activo' => true, 'requisitos_json' => ['peso_real_obligatorio' => $requierePesoModalidad]]
        );
        $tarea = PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $pedido->almacen_id,
            'area_responsable_codigo' => 'TIENDA',
            'estado' => $estado,
            'solicitada_por_id' => $this->usuario->id,
            'solicitada_at' => now(),
            'catalogo_paqueteria_id' => $paqueteriaId,
            'version' => 1,
        ]);
        PedidoBmaTareaProducto::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'descripcion_snapshot' => 'Pieza Q5',
            'cantidad_solicitada' => 1,
            'cantidad_encontrada' => 1,
            'estado_fisico' => 'bueno',
            'orden' => 1,
        ]);

        return $tarea;
    }

    private function tareaEmpaque(bool $pesoObligatorio): PedidoBmaTareaPreparacion
    {
        $pedido = $this->pedidoPendiente(true);
        $tarea = $this->tarea(
            $pedido,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
            CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO,
            $pesoObligatorio
        );
        $this->assertSame(0, PedidoBmaTareaDocumento::query()->where('pedido_bma_tarea_preparacion_id', $tarea->id)->count());

        return $tarea->fresh(['modalidad', 'documentos', 'productos']);
    }
}
