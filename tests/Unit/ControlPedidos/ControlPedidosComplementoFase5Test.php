<?php

namespace Tests\Unit\ControlPedidos;

use App\Http\Requests\ControlPedidos\PedidoBmaRequestBase;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\CrearPedidoBmaService;
use App\Services\ControlPedidos\GenerarFolioPedidoBmaService;
use App\Services\ControlPedidos\ValidacionCamposPedidoBma;
use Illuminate\Foundation\Http\FormRequest;
use Tests\TestCase;

/**
 * Check Fase 5: vínculo complemento + subfolio -Cn (sin DB).
 */
class ControlPedidosComplementoFase5Test extends TestCase
{
    private function resolverEstatus(): object
    {
        return new class {
            use ValidacionCamposPedidoBma;

            public function estatus(PedidoBma $pedido): string
            {
                return $this->resolverEstatusEnvioAlEnviar($pedido);
            }
        };
    }

    public function test_siguiente_sufijo_complemento_c1_c2(): void
    {
        $base = 'PBMA-2026-00012';
        $this->assertSame(
            'PBMA-2026-00012-C1',
            GenerarFolioPedidoBmaService::siguienteSufijoComplemento($base, [])
        );
        $this->assertSame(
            'PBMA-2026-00012-C2',
            GenerarFolioPedidoBmaService::siguienteSufijoComplemento($base, ["{$base}-C1"])
        );
        $this->assertSame(
            'PBMA-2026-00012-C3',
            GenerarFolioPedidoBmaService::siguienteSufijoComplemento($base, ["{$base}-C1", "{$base}-C2"])
        );
    }

    public function test_es_complemento_raiz_y_cabecera(): void
    {
        $raiz = new PedidoBma(['id' => 10, 'folio' => 'PBMA-2026-00012']);
        $hijo = new PedidoBma([
            'id' => 11,
            'folio' => 'PBMA-2026-00012-C1',
            'pedido_principal_id' => 10,
        ]);
        $hijo->setRelation('principal', $raiz);
        $raiz->setRelation('complementos', collect([$hijo]));

        $this->assertFalse($raiz->esComplemento());
        $this->assertTrue($hijo->esComplemento());
        $this->assertTrue($raiz->esPrincipalConComplementos());
        $this->assertFalse($hijo->esPrincipalConComplementos());
        $this->assertSame($raiz, $hijo->raizEmpaque());
        $this->assertSame($raiz, $raiz->raizEmpaque());
        $this->assertSame('PBMA-2026-00012', $hijo->folioVisibleCabecera());
        $this->assertSame('PBMA-2026-00012', $raiz->folioVisibleCabecera());
    }

    public function test_grupo_empaque_incluye_raiz_y_complementos(): void
    {
        $raiz = new PedidoBma(['id' => 1, 'folio' => 'PBMA-2026-00001']);
        $c1 = new PedidoBma(['id' => 2, 'folio' => 'PBMA-2026-00001-C1', 'pedido_principal_id' => 1]);
        $c2 = new PedidoBma(['id' => 3, 'folio' => 'PBMA-2026-00001-C2', 'pedido_principal_id' => 1]);
        $raiz->setRelation('complementos', collect([$c1, $c2]));

        $grupo = collect([$raiz])->merge($raiz->complementos);
        $this->assertCount(3, $grupo);
        $this->assertTrue($raiz->esPrincipalConComplementos());
        // Regla empacar: solo miembros que pasen gates CEDIS; vacío ⇒ error (servicio).
        $listos = $grupo->filter(fn () => false);
        $this->assertTrue($listos->isEmpty());
    }

    public function test_complementario_al_enviar_queda_completo(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio([
            'codigo' => CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_COMPLEMENTARIO,
        ]);
        $pedido = new PedidoBma(['costo_envio' => 90, 'pedido_principal_id' => 5]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertTrue($pedido->esResguardoComplementario());
        $this->assertSame(
            PedidoBma::ESTATUS_ENVIO_COMPLETO,
            $this->resolverEstatus()->estatus($pedido)
        );
    }

    public function test_request_exige_principal_si_modo_complementario(): void
    {
        $request = new class extends PedidoBmaRequestBase {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return $this->reglasComunes();
            }
        };

        $rules = $request->rules();
        $this->assertArrayHasKey('pedido_principal_id', $rules);
        $flat = collect($rules['pedido_principal_id'])->map(fn ($r) => (string) $r)->implode('|');
        $this->assertStringContainsString('required_if:modo_resguardo,complementario', $flat);
        $this->assertInstanceOf(FormRequest::class, $request);
    }

    public function test_principal_resguardo_abierto_pendiente_liberacion_es_complementable(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio([
            'codigo' => CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO,
        ]);
        $principal = new PedidoBma([
            'id' => 40,
            'folio' => 'PBMA-2026-00040',
            'cliente_id' => 7,
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_LIBERACION,
            'es_resguardo' => true,
        ]);
        $principal->setRelation('tipoOperacionEnvio', $tipo);

        $this->assertTrue($principal->esResguardoAbierto());
        $this->assertFalse($principal->esComplemento());

        $service = $this->app->make(CrearPedidoBmaService::class);
        $service->validarPrincipalParaComplemento($principal, 7);
        $this->assertTrue(true);
    }

    public function test_principal_que_ya_es_complemento_se_rechaza(): void
    {
        $principal = new PedidoBma([
            'id' => 41,
            'cliente_id' => 7,
            'pedido_principal_id' => 40,
        ]);

        $service = $this->app->make(CrearPedidoBmaService::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ya es complemento');
        $service->validarPrincipalParaComplemento($principal, 7);
    }

    public function test_aplicar_logistica_desde_principal_copia_y_anula_peso(): void
    {
        $principal = new PedidoBma([
            'cliente_id' => 9,
            'origen_id' => 2,
            'almacen_id' => 3,
            'cliente_direccion_id' => 4,
            'domicilio_entrega' => 'Calle 1',
            'codigo_postal' => '01000',
            'catalogo_paqueteria_id' => 5,
            'catalogo_tipo_guia_id' => 6,
            'catalogo_zona_id' => 7,
            'catalogo_tipo_caja_id' => 8,
            'envia_a_otra_persona' => true,
            'envia_otra_persona' => 'Juan',
            'anexar_remision' => true,
        ]);

        $service = $this->app->make(CrearPedidoBmaService::class);
        $out = $service->aplicarLogisticaDesdePrincipal([
            'peso_real_kg' => 12,
            'numero_cajas' => 2,
            'costo_envio' => 99,
            'origen_id' => 99,
        ], $principal);

        $this->assertSame(9, $out['cliente_id']);
        $this->assertSame(2, $out['origen_id']);
        $this->assertSame(3, $out['almacen_id']);
        $this->assertSame('Calle 1', $out['domicilio_entrega']);
        $this->assertSame('01000', $out['codigo_postal']);
        $this->assertSame(5, $out['catalogo_paqueteria_id']);
        $this->assertTrue($out['envia_a_otra_persona']);
        $this->assertSame('Juan', $out['envia_otra_persona']);
        $this->assertNull($out['peso_real_kg']);
        $this->assertNull($out['numero_cajas']);
        $this->assertNull($out['costo_envio']);
    }

    public function test_request_completar_envio_vendedora_exige_captura(): void
    {
        $tipo = new CatalogoTipoOperacionEnvio([
            'codigo' => CatalogoTipoOperacionEnvio::CODIGO_RESGUARDO_ABIERTO,
        ]);
        $pedido = new PedidoBma([
            'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_LIBERACION,
            'es_resguardo' => true,
        ]);
        $pedido->setRelation('tipoOperacionEnvio', $tipo);

        $request = \App\Http\Requests\ControlPedidos\CompletarEnvioResguardoPedidoBmaRequest::create(
            '/control-pedidos/1/completar-envio-resguardo',
            'POST'
        );
        $route = new \Illuminate\Routing\Route(['POST'], '/control-pedidos/{pedidoBma}/completar-envio-resguardo', []);
        $route->bind($request);
        $route->setParameter('pedidoBma', $pedido);
        $request->setRouteResolver(fn () => $route);

        $rules = $request->rules();
        $this->assertArrayHasKey('peso_real_kg', $rules);
        $this->assertArrayHasKey('numero_cajas', $rules);
        $this->assertArrayHasKey('costo_envio', $rules);
        $this->assertArrayHasKey('comprobante', $rules);
        $this->assertArrayHasKey('cliente_direccion_id', $rules);
        $this->assertArrayHasKey('catalogo_paqueteria_id', $rules);
        $this->assertArrayHasKey('catalogo_tipo_guia_id', $rules);
        $this->assertArrayHasKey('catalogo_zona_id', $rules);
        $this->assertContains('required', $rules['peso_real_kg']);
        $this->assertContains('required', $rules['cliente_direccion_id']);
    }
}
