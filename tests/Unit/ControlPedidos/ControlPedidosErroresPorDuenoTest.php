<?php

namespace Tests\Unit\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\AprobarPedidoBmaService;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosAuditoriaService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\ReportarErrorDatosPedidoBmaService;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ControlPedidosErroresPorDuenoTest extends TestCase
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

    public function test_dueno_activo_prioriza_vendedora_auxiliar_guias(): void
    {
        $this->assertSame(
            CamposIncorrectosPedidoBma::DUENO_VENDEDORA,
            CamposIncorrectosPedidoBma::duenoActivo(['guia_pdf', 'domicilio', 'remision'])
        );
        $this->assertSame(
            CamposIncorrectosPedidoBma::DUENO_AUXILIAR,
            CamposIncorrectosPedidoBma::duenoActivo(['guia_pdf', 'remision'])
        );
        $this->assertSame(
            CamposIncorrectosPedidoBma::DUENO_GUIAS,
            CamposIncorrectosPedidoBma::duenoActivo(['numero_rastreo'])
        );
        $this->assertNull(CamposIncorrectosPedidoBma::duenoActivo([]));
    }

    public function test_quitar_campos_de_dueno(): void
    {
        $cola = ['domicilio', 'remision', 'guia_pdf'];
        $sinVendedora = CamposIncorrectosPedidoBma::quitarCamposDeDueno(
            $cola,
            CamposIncorrectosPedidoBma::DUENO_VENDEDORA
        );
        $this->assertSame(['remision', 'guia_pdf'], $sinVendedora);

        $sinAuxiliar = CamposIncorrectosPedidoBma::quitarCamposDeDueno(
            $sinVendedora,
            CamposIncorrectosPedidoBma::DUENO_AUXILIAR
        );
        $this->assertSame(['guia_pdf'], $sinAuxiliar);
    }

    public function test_reportar_solo_remision_va_a_auxiliar(): void
    {
        $pedido = $this->crearPedidoPendienteEnvio();

        $actualizado = app(ReportarErrorDatosPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->usuario->id,
            ['remision'],
            'Remisión ilegible'
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertSame(['remision'], $actualizado->campos_incorrectos);
        $this->assertFalse($actualizado->tieneRemision());
        $this->assertNull($actualizado->pago_validado_at);
    }

    public function test_reportar_solo_guia_con_empaque_va_a_pendiente_guia(): void
    {
        $pedido = $this->crearPedidoPendienteEnvio();

        $actualizado = app(ReportarErrorDatosPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->usuario->id,
            ['numero_rastreo', 'guia_pdf'],
            'Guía no coincide'
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertSame(['numero_rastreo', 'guia_pdf'], $actualizado->campos_incorrectos);
        $this->assertNull($actualizado->numero_rastreo);
    }

    public function test_reportar_solo_domicilio_va_a_vendedora(): void
    {
        $pedido = $this->crearPedidoPendienteEnvio();

        $actualizado = app(ReportarErrorDatosPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->usuario->id,
            ['domicilio'],
            ''
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
    }

    public function test_cascada_domicilio_remision_guia(): void
    {
        config(['control_pedidos.direcciones_normalizadas' => false]);

        $pedido = $this->crearPedidoPendienteEnvio();

        $reportado = app(ReportarErrorDatosPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->usuario->id,
            ['domicilio', 'remision', 'guia_pdf'],
            'Cola completa'
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            $reportado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertEqualsCanonicalizing(
            ['domicilio', 'remision', 'guia_pdf'],
            $reportado->campos_incorrectos
        );

        // Reenvío exige pesaje respondido (flujo logístico).
        $reportado->update(['pesaje_respondido_at' => now()]);

        $enviado = app(EnviarPedidoBmaService::class)->ejecutar(
            $reportado->fresh(['estatus', 'origen', 'documentos', 'comprobantes', 'cliente']),
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
            $enviado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertEqualsCanonicalizing(
            ['remision', 'guia_pdf'],
            $enviado->campos_incorrectos
        );

        // Flag de re-revisión para la bandeja de auxiliar (historial: no vino de borrador).
        $vista = app(ListarPedidosAuditoriaService::class)->ejecutar(['tab' => 'PENDIENTES'], false);
        $fila = $vista->firstWhere('id', $enviado->id);
        $this->assertNotNull($fila);
        $this->assertTrue((bool) $fila->pendiente_re_revision);

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $enviado->id,
            'tipo' => PedidoBmaDocumento::TIPO_COMPROBANTE,
            'ruta_archivo' => 'pedidos_bma/comprobantes/test.jpg',
            'nombre_original' => 'comp.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 50,
            'orden' => 1,
        ]);
        PedidoBmaDocumento::create([
            'pedido_bma_id' => $enviado->id,
            'tipo' => PedidoBmaDocumento::TIPO_REMISION,
            'ruta_archivo' => 'pedidos_bma/remisiones/nueva.pdf',
            'nombre_original' => 'remision.pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => 100,
            'orden' => 2,
        ]);
        $enviado->update([
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ]);

        $aprobado = app(AprobarPedidoBmaService::class)->ejecutar(
            $enviado->fresh(['estatus', 'documentos']),
            $this->usuario->id
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            $aprobado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertSame(['guia_pdf'], $aprobado->campos_incorrectos);
    }

    public function test_auxiliar_reporta_domicilio_a_vendedora(): void
    {
        $pedido = $this->crearPedidoPendienteAuxiliar();

        $actualizado = app(ReportarErrorDatosPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->usuario->id,
            ['domicilio', 'telefono'],
            'Datos de contacto mal'
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertEqualsCanonicalizing(['domicilio', 'telefono'], $actualizado->campos_incorrectos);
        $this->assertFalse($actualizado->tieneRemision());
        $this->assertNull($actualizado->pago_validado_at);
    }

    public function test_auxiliar_reporta_guia_sin_empaque_queda_en_cedis(): void
    {
        $pedido = $this->crearPedidoPendienteAuxiliar();

        $actualizado = app(ReportarErrorDatosPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->usuario->id,
            ['numero_rastreo'],
            'Guía incorrecta en captura'
        );

        $this->assertSame(
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            $actualizado->fresh('estatus')->estatus->fase_ciclo
        );
        $this->assertSame(['numero_rastreo'], $actualizado->campos_incorrectos);
    }

    public function test_auxiliar_no_puede_reportar_solo_remision_a_si_misma(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $pedido = $this->crearPedidoPendienteAuxiliar();
        app(ReportarErrorDatosPedidoBmaService::class)->ejecutar(
            $pedido->fresh(['estatus', 'documentos']),
            $this->usuario->id,
            ['remision'],
            ''
        );
    }

    private function crearPedidoPendienteAuxiliar(): PedidoBma
    {
        $pendiente = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);

        $pedido = PedidoBma::create([
            'folio' => 'PED-AUX-'.uniqid(),
            'folio_remision' => 'REM-AUX-'.uniqid(),
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
            'destinatario' => 'Destinatario Test',
            'telefono_contacto' => '9991234567',
            'total_mercancia' => 1000,
            'costo_envio' => 150,
            'catalogo_estatus_pedido_id' => $pendiente->id,
            'es_resguardo' => false,
            'pago_validado_at' => now(),
            'pago_validado_por_id' => $this->usuario->id,
        ]);

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

    private function crearPedidoPendienteGuia(): PedidoBma
    {
        $pedido = $this->crearPedidoAprobadoCedis([
            'catalogo_paqueteria_id' => $this->paqueteriaComercialId(),
        ]);

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
            'destinatario' => 'Destinatario Test',
            'telefono_contacto' => '9991234567',
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

        PedidoBmaDocumento::create([
            'pedido_bma_id' => $pedido->id,
            'tipo' => PedidoBmaDocumento::TIPO_COMPROBANTE,
            'ruta_archivo' => 'pedidos_bma/comprobantes/test.jpg',
            'nombre_original' => 'comp.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 50,
            'orden' => 2,
        ]);

        return $pedido->fresh();
    }

    private function seedCatalogosMinimos(): void
    {
        $now = now();

        if (! CatalogoEstatusPedido::exists()) {
            foreach ([
                ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'fase_ciclo' => 'BORRADOR', 'color_hex' => '#94A3B8', 'orden' => 1],
                ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'AZUL ①', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'color_hex' => '#3B82F6', 'orden' => 2],
                ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'AMARILLO', 'fase_ciclo' => 'EN_CEDIS', 'color_hex' => '#EAB308', 'orden' => 3],
                ['codigo_interno' => 'ROJO', 'nombre_visual' => 'Incidencia', 'fase_ciclo' => 'INCIDENCIA_CEDIS', 'color_hex' => '#EF4444', 'orden' => 4],
                ['codigo_interno' => 'NARANJA', 'nombre_visual' => 'Rechazado', 'fase_ciclo' => 'RECHAZADO_VENDEDORA', 'color_hex' => '#F97316', 'orden' => 5],
                ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'color_hex' => '#A855F7', 'orden' => 7],
                ['codigo_interno' => 'PENDIENTE_ENVIO', 'nombre_visual' => 'Pendiente de envío', 'fase_ciclo' => 'PENDIENTE_DE_ENVIO', 'color_hex' => '#0EA5E9', 'orden' => 10],
                ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'fase_ciclo' => 'ENVIADO', 'color_hex' => '#22C55E', 'orden' => 9],
            ] as $row) {
                CatalogoEstatusPedido::create(array_merge($row, ['activo' => true]));
            }
        }

        $this->origenForaneo();

        if (! DB::table('catalogo_bancos')->exists()) {
            DB::table('catalogo_bancos')->insert([
                'nombre' => 'BBVA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('catalogo_listas_descuento')->exists()) {
            DB::table('catalogo_listas_descuento')->insert([
                'nombre' => 'Lista Test', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
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
                    'nombre' => 'FEDEX', 'categoria' => 'comercial', 'activo' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        if (! DB::table('catalogo_tipos_caja_pedido')->exists()) {
            DB::table('catalogo_tipos_caja_pedido')->insert([
                'nombre' => 'CAJA TEST', 'peso_volumetrico' => 1, 'activo' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('catalogo_tipos_guia_pedido')->exists()) {
            DB::table('catalogo_tipos_guia_pedido')->insert([
                'nombre' => 'Terrestre', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('catalogo_zonas_pedido')->exists()) {
            DB::table('catalogo_zonas_pedido')->insert([
                'nombre' => 'Sin reexpedición', 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
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
                'codigo' => 'VTA', 'nombre' => 'CEDIS', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (! DB::table('catalogo_envios_tienda')->exists()) {
            DB::table('catalogo_envios_tienda')->insert([
                'nombre' => 'Tienda', 'es_otro' => false, 'activo' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
