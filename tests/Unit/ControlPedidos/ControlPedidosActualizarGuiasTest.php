<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\ActualizarGuiaPedidoBmaService;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosDelegadoService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\ReportarErrorDatosPedidoBmaService;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ControlPedidosActualizarGuiasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->seedCatalogosMinimos();
        Notification::fake();
    }

    public function test_filtros_delegado_por_tab(): void
    {
        $pedidoGuia = $this->crearPedidoPendienteGuia();
        $pedidoEnvio = $this->crearPedidoPendienteEnvio();

        $listar = app(ListarPedidosDelegadoService::class);

        $pendientes = $listar->ejecutar(['tab' => 'PENDIENTES_GUIA'], false);
        $this->assertTrue($pendientes->contains('id', $pedidoGuia->id));
        $this->assertFalse($pendientes->contains('id', $pedidoEnvio->id));

        $envio = $listar->ejecutar(['tab' => 'PENDIENTES_ENVIO'], false);
        $this->assertTrue($envio->contains('id', $pedidoEnvio->id));
        $this->assertFalse($envio->contains('id', $pedidoGuia->id));

        $metricas = $listar->metricas();
        $this->assertGreaterThanOrEqual(1, $metricas['pendientes_guia']);
        $this->assertGreaterThanOrEqual(1, $metricas['pendientes_envio']);
        $this->assertSame(
            $metricas['pendientes_guia']
                + ($metricas['pendiente_empaque'] ?? 0)
                + $metricas['pendientes_envio']
                + $metricas['enviados'],
            $metricas['total']
        );
    }

    public function test_corregir_guia_marca_retraso(): void
    {
        $pedido = $this->crearPedidoPendienteEnvio();

        $actualizado = app(ActualizarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'GUIA-CORREGIDA-99',
            $this->usuario->id
        );

        $this->assertSame('GUIA-CORREGIDA-99', $actualizado->numero_rastreo);
        $this->assertTrue((bool) $actualizado->guia_retraso);
        $this->assertNotNull($actualizado->guia_corregida_at);
        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertDatabaseHas('pedido_bma_historial_estados', [
            'pedido_bma_id' => $pedido->id,
        ]);
        $this->assertTrue(
            DB::table('pedido_bma_historial_estados')
                ->where('pedido_bma_id', $pedido->id)
                ->where('comentarios', 'like', '%provoca retraso%')
                ->exists()
        );
    }

    public function test_reportar_error_datos_rechaza_y_bloquea_envio(): void
    {
        $pedido = $this->crearPedidoPendienteEnvio();

        $actualizado = app(ReportarErrorDatosPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->usuario->id,
            ['domicilio', 'codigo_postal'],
            'CP y calle incorrectos'
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertSame(['domicilio', 'codigo_postal'], $actualizado->campos_incorrectos);
        $this->assertNull($actualizado->numero_rastreo);
        $this->assertFalse($actualizado->fresh('estatus')->puedeMarcarEnviado());
    }

    public function test_allowlist_campos_incorrectos_filtra_invalidos(): void
    {
        $filtrados = CamposIncorrectosPedidoBma::filtrar(['domicilio', 'hack', 'paqueteria', 'foo']);
        $this->assertSame(['domicilio', 'paqueteria'], $filtrados);
    }

    public function test_reportar_error_sin_campos_validos_falla(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $pedido = $this->crearPedidoPendienteEnvio();
        app(ReportarErrorDatosPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->usuario->id,
            ['campo_inventado'],
            'detalle'
        );
    }

    public function test_filtro_situacion_sin_guia_y_con_guia(): void
    {
        $sinGuia = $this->crearPedidoPendienteGuia();
        $conGuia = $this->crearPedidoPendienteEnvio();
        $listar = app(ListarPedidosDelegadoService::class);

        $soloSin = $listar->ejecutar(['tab' => 'TODOS', 'situacion' => 'sin_guia'], false);
        $this->assertTrue($soloSin->contains('id', $sinGuia->id));
        $this->assertFalse($soloSin->contains('id', $conGuia->id));

        $soloCon = $listar->ejecutar(['tab' => 'TODOS', 'situacion' => 'con_guia'], false);
        $this->assertTrue($soloCon->contains('id', $conGuia->id));
        $this->assertFalse($soloCon->contains('id', $sinGuia->id));
    }

    public function test_filtro_sin_guia_trata_rastreo_vacio(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);
        $pedido->update(['numero_rastreo' => '']);
        $listar = app(ListarPedidosDelegadoService::class);

        $sin = $listar->ejecutar(['tab' => 'TODOS', 'situacion' => 'sin_guia'], false);
        $con = $listar->ejecutar(['tab' => 'TODOS', 'situacion' => 'con_guia'], false);

        $this->assertTrue($sin->contains('id', $pedido->id));
        $this->assertFalse($con->contains('id', $pedido->id));
    }

    public function test_filtro_error_guia_no_cuenta_como_retraso_sin_flags(): void
    {
        $pedido = $this->crearPedidoPendienteEnvio();
        $pedido->update([
            'campos_incorrectos' => ['numero_rastreo'],
            'guia_retraso' => false,
            'retraso_empaque_alertado_at' => null,
            'retraso_recoleccion_alertado_at' => null,
        ]);
        $listar = app(ListarPedidosDelegadoService::class);

        $err = $listar->ejecutar(['tab' => 'TODOS', 'situacion' => 'error_guia'], false);
        $ret = $listar->ejecutar(['tab' => 'TODOS', 'situacion' => 'retraso'], false);

        $this->assertTrue($err->contains('id', $pedido->id));
        $this->assertFalse($ret->contains('id', $pedido->id));
    }

    public function test_filtro_retraso_por_guia_retraso(): void
    {
        $pedido = $this->crearPedidoPendienteEnvio();
        $pedido->update(['guia_retraso' => true]);
        $listar = app(ListarPedidosDelegadoService::class);

        $ret = $listar->ejecutar(['tab' => 'TODOS', 'situacion' => 'retraso'], false);
        $this->assertTrue($ret->contains('id', $pedido->id));
    }

    public function test_filtro_retraso_por_empaque(): void
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);
        DB::table('pedidos_bma')->where('id', $pedido->id)->update([
            'retraso_empaque_alertado_at' => now(),
            'empacado_at' => null,
        ]);
        $listar = app(ListarPedidosDelegadoService::class);

        $ret = $listar->ejecutar(['tab' => 'TODOS', 'situacion' => 'retraso'], false);
        $this->assertTrue($ret->contains('id', $pedido->id));
    }

    public function test_filtro_retraso_por_recoleccion(): void
    {
        $pedido = $this->crearPedidoPendienteEnvio();
        DB::table('pedidos_bma')->where('id', $pedido->id)->update([
            'retraso_recoleccion_alertado_at' => now(),
        ]);
        $listar = app(ListarPedidosDelegadoService::class);

        $ret = $listar->ejecutar(['tab' => 'TODOS', 'situacion' => 'retraso'], false);
        $this->assertTrue($ret->contains('id', $pedido->id));
    }

    public function test_orden_fecha_desc_y_asc(): void
    {
        $antiguo = $this->crearPedidoAprobadoCedis([
            'created_at' => now()->subDays(2),
        ]);
        $reciente = $this->crearPedidoAprobadoCedis([
            'created_at' => now()->subDay(),
        ]);
        $listar = app(ListarPedidosDelegadoService::class);

        $desc = $listar->ejecutar(['tab' => 'TODOS', 'ordenar' => 'fecha_desc'], false);
        $this->assertSame($reciente->id, $desc->first()->id);

        $asc = $listar->ejecutar(['tab' => 'TODOS', 'ordenar' => 'fecha_asc'], false);
        $this->assertSame($antiguo->id, $asc->first()->id);
    }

    public function test_pedidos_para_exportar_orden_folio_remision(): void
    {
        $primero = $this->crearPedidoPendienteGuia(['folio_remision' => 'REM-EXPORT-AAA']);
        $segundo = $this->crearPedidoPendienteGuia(['folio_remision' => 'REM-EXPORT-ZZZ']);

        $export = app(ListarPedidosDelegadoService::class)->pedidosParaExportar();
        $marcados = $export->whereIn('id', [$primero->id, $segundo->id])->values();

        $this->assertCount(2, $marcados);
        $this->assertSame(
            ['REM-EXPORT-AAA', 'REM-EXPORT-ZZZ'],
            $marcados->pluck('folio_remision')->all()
        );
    }

    public function test_filtro_paqueteria_ids_uno_y_varios(): void
    {
        $idA = $this->paqueteriaComercialId();
        $idB = $this->crearPaqueteriaComercial('PAQ-FILTRO-B');
        $pedidoA = $this->crearPedidoAprobadoCedis(['catalogo_paqueteria_id' => $idA]);
        $pedidoB = $this->crearPedidoAprobadoCedis(['catalogo_paqueteria_id' => $idB]);

        $listar = app(ListarPedidosDelegadoService::class);

        $soloA = $listar->ejecutar(['tab' => 'TODOS', 'paqueteria_ids' => [$idA]], false);
        $this->assertTrue($soloA->contains('id', $pedidoA->id));
        $this->assertFalse($soloA->contains('id', $pedidoB->id));

        $ambos = $listar->ejecutar(['tab' => 'TODOS', 'paqueteria_ids' => [$idA, $idB]], false);
        $this->assertTrue($ambos->contains('id', $pedidoA->id));
        $this->assertTrue($ambos->contains('id', $pedidoB->id));
    }

    public function test_filtro_paqueteria_ids_invalidos_no_muestra_todos(): void
    {
        $pedido = $this->crearPedidoPendienteGuia();
        $listar = app(ListarPedidosDelegadoService::class);

        $sinFiltro = $listar->ejecutar(['tab' => 'TODOS'], false);
        $this->assertTrue($sinFiltro->contains('id', $pedido->id));

        $invalido = $listar->ejecutar(['tab' => 'TODOS', 'paqueteria_ids' => [999_999_999]], false);
        $this->assertCount(0, $invalido);
        $this->assertFalse($invalido->contains('id', $pedido->id));
    }

    public function test_filtros_combinados_tab_q_paqueteria_situacion(): void
    {
        $idPaq = $this->paqueteriaComercialId();
        $match = $this->crearPedidoPendienteGuia([
            'folio_remision' => 'REM-COMBO-OK-001',
            'catalogo_paqueteria_id' => $idPaq,
        ]);
        $this->crearPedidoPendienteGuia([
            'folio_remision' => 'REM-COMBO-OTRO-002',
            'catalogo_paqueteria_id' => $this->crearPaqueteriaComercial('PAQ-COMBO-OTRA'),
        ]);

        $listar = app(ListarPedidosDelegadoService::class);
        $resultado = $listar->ejecutar([
            'tab' => 'PENDIENTES_GUIA',
            'q' => 'COMBO-OK',
            'paqueteria_ids' => [$idPaq],
            'situacion' => 'sin_guia',
        ], false);

        $this->assertEqualsCanonicalizing([$match->id], $resultado->pluck('id')->all());
    }

    public function test_paginacion_respeta_filtros(): void
    {
        $idPaq = $this->paqueteriaComercialId();
        $ids = [];
        for ($i = 0; $i < 16; $i++) {
            $p = $this->crearPedidoPendienteGuia([
                'folio_remision' => 'REM-PAG-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'catalogo_paqueteria_id' => $idPaq,
            ]);
            $ids[] = $p->id;
        }

        $listar = app(ListarPedidosDelegadoService::class);
        $p1 = $listar->ejecutar([
            'tab' => 'PENDIENTES_GUIA',
            'paqueteria_ids' => [$idPaq],
            'page' => 1,
        ]);
        $p2 = $listar->ejecutar([
            'tab' => 'PENDIENTES_GUIA',
            'paqueteria_ids' => [$idPaq],
            'page' => 2,
        ]);

        $this->assertSame(15, $p1->perPage());
        $this->assertSame(16, $p1->total());
        $this->assertSame(1, $p2->count());

        $pagina1Ids = $p1->pluck('id')->all();
        $pagina2Ids = $p2->pluck('id')->all();
        $this->assertEmpty(array_intersect($pagina1Ids, $pagina2Ids));
        $this->assertEqualsCanonicalizing($ids, array_merge($pagina1Ids, $pagina2Ids));
    }

    public function test_metricas_no_dependen_de_filtros_listado(): void
    {
        $this->crearPedidoPendienteGuia();
        $listar = app(ListarPedidosDelegadoService::class);
        $antes = $listar->metricas();
        $listar->ejecutar(['q' => 'NO-EXISTE-XYZ-999'], false);
        $despues = $listar->metricas();
        $this->assertSame($antes, $despues);
    }

    private function crearPaqueteriaComercial(string $nombre): int
    {
        $now = now();
        $id = DB::table('catalogo_paqueterias_pedido')->insertGetId([
            'nombre' => $nombre,
            'categoria' => 'comercial',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $id;
    }

    private function crearPedidoPendienteGuia(array $overrides = []): PedidoBma
    {
        $pedido = $this->crearPedidoAprobadoCedis(array_merge([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ], $overrides));

        return app(MarcarEmpacadoPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['paqueteria', 'origen']),
            $this->usuario->id
        );
    }

    private function crearPedidoPendienteEnvio(): PedidoBma
    {
        $pedido = $this->crearPedidoPendienteGuia();

        return app(AsignarGuiaPedidoBmaService::class)->ejecutar(
            $pedido->fresh('estatus'),
            'GUIA-ORIGINAL',
            $this->usuario->id
        );
    }

    private function origenForaneo(): CatalogoOrigenPedido
    {
        return CatalogoOrigenPedido::firstOrCreate(
            ['nombre' => 'Envío Foráneo'],
            ['requiere_logistica' => true, 'activo' => true]
        );
    }

    private function paqueteriaComercialId(): int
    {
        return (int) DB::table('catalogo_paqueterias_pedido')
            ->where('categoria', 'comercial')
            ->value('id');
    }

    private function crearPedidoAprobadoCedis(array $overrides = []): PedidoBma
    {
        $enCedis = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS);

        $pedido = PedidoBma::create(array_merge([
            'folio' => 'PED-TEST-'.uniqid(),
            'folio_remision' => 'REM-TEST-'.uniqid(),
            'fecha' => now()->toDateString(),
            'vendedor_id' => $this->usuario->id,
            'cliente_id' => DB::table('clientes')->value('id'),
            'origen_id' => $this->origenForaneo()->id,
            'almacen_id' => DB::table('almacenes')->value('id'),
            'catalogo_banco_id' => DB::table('catalogo_bancos')->value('id'),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'numero_cajas' => 1,
            'peso_real_kg' => 1.5,
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
            'catalogo_tipo_guia_id' => DB::table('catalogo_tipos_guia_pedido')->value('id'),
            'catalogo_zona_id' => DB::table('catalogo_zonas_pedido')->value('id'),
            'catalogo_envio_tienda_id' => DB::table('catalogo_envios_tienda')->value('id'),
            'codigo_postal' => '86000',
            'domicilio_entrega' => 'Calle Test 123',
            'total_mercancia' => 1000,
            'costo_envio' => 150,
            'catalogo_estatus_pedido_id' => $enCedis->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ], $overrides));

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'pedidos_bma/remisiones/test.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 1,
        ]);

        return $pedido->fresh();
    }

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
                ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'AZUL ①', 'color_hex' => '#3B82F6', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 2],
                ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'AMARILLO', 'color_hex' => '#EAB308', 'fase_ciclo' => 'EN_CEDIS', 'orden' => 3],
                ['codigo_interno' => 'ROJO', 'nombre_visual' => 'Incidencia', 'color_hex' => '#EF4444', 'fase_ciclo' => 'INCIDENCIA_CEDIS', 'orden' => 4],
                ['codigo_interno' => 'NARANJA', 'nombre_visual' => 'Rechazado', 'color_hex' => '#F97316', 'fase_ciclo' => 'RECHAZADO_VENDEDORA', 'orden' => 5],
                ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'color_hex' => '#A855F7', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 7],
                ['codigo_interno' => 'PENDIENTE_ENVIO', 'nombre_visual' => 'Pendiente de envío', 'color_hex' => '#0EA5E9', 'fase_ciclo' => 'PENDIENTE_DE_ENVIO', 'orden' => 10],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
            }
        }

        $this->origenForaneo();

        if (! DB::table('catalogo_bancos')->exists()) {
            DB::table('catalogo_bancos')->insert([
                'nombre' => 'BBVA',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_paqueterias_pedido')->where('categoria', 'comercial')->exists()) {
            $existente = DB::table('catalogo_paqueterias_pedido')->where('nombre', 'FEDEX')->first();
            if ($existente) {
                DB::table('catalogo_paqueterias_pedido')
                    ->where('id', $existente->id)
                    ->update(['categoria' => 'comercial', 'updated_at' => $now]);
            } else {
                DB::table('catalogo_paqueterias_pedido')->insert([
                    'nombre' => 'FEDEX',
                    'categoria' => 'comercial',
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (! DB::table('catalogo_tipos_caja_pedido')->exists()) {
            DB::table('catalogo_tipos_caja_pedido')->insert([
                'nombre' => 'CAJA TEST',
                'peso_volumetrico' => 1,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_tipos_guia_pedido')->exists()) {
            DB::table('catalogo_tipos_guia_pedido')->insert([
                'nombre' => 'Terrestre',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_zonas_pedido')->exists()) {
            DB::table('catalogo_zonas_pedido')->insert([
                'nombre' => 'Sin reexpedición',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('clientes')->exists()) {
            DB::table('clientes')->insert([
                'numero_cliente' => '1001',
                'nombre' => 'Cliente Test',
                'lista_actual_id' => DB::table('catalogo_listas_descuento')->value('id'),
                'vendedor_id' => $this->usuario->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('almacenes')->exists()) {
            DB::table('almacenes')->insert([
                'codigo' => 'VTA',
                'nombre' => 'CEDIS',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('catalogo_envios_tienda')->exists()) {
            DB::table('catalogo_envios_tienda')->insert([
                'nombre' => 'Tienda',
                'es_otro' => false,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
