<?php

namespace Tests\Unit\Manuales;

use App\Services\Manuales\GenerarPdfManualService;
use App\Services\Manuales\ResolverManualesVisiblesService;
use App\Support\Manuales\Content\RegistrarPedidosVendedorManualContent;
use App\Support\Manuales\ManualesCatalog;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Tests\TestCase;

class FakeManualUser implements Authorizable
{
    /** @param  list<string>  $permisos */
    public function __construct(
        private array $permisos = [],
        private array $roles = [],
    ) {}

    public function can($abilities, $arguments = [])
    {
        foreach ((array) $abilities as $ability) {
            if (in_array($ability, $this->permisos, true)) {
                return true;
            }
        }

        return false;
    }

    public function cannot($abilities, $arguments = [])
    {
        return ! $this->can($abilities, $arguments);
    }

    public function canAny($abilities, $arguments = [])
    {
        return $this->can($abilities, $arguments);
    }

    public function canAll($abilities, $arguments = [])
    {
        foreach ((array) $abilities as $ability) {
            if (! $this->can($ability, $arguments)) {
                return false;
            }
        }

        return true;
    }

    public function hasRole($roles): bool
    {
        foreach ((array) $roles as $role) {
            if (in_array($role, $this->roles, true)) {
                return true;
            }
        }

        return false;
    }
}

class ResolverManualesVisiblesServiceTest extends TestCase
{
    private ResolverManualesVisiblesService $resolver;

    private GenerarPdfManualService $pdf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ResolverManualesVisiblesService;
        $this->pdf = new GenerarPdfManualService($this->resolver);
    }

    public function test_vendedora_ve_manual_pero_no_cedis_ni_guias(): void
    {
        $user = new FakeManualUser(['control_pedidos.ver_listado', 'control_pedidos.crear']);

        $this->assertTrue($this->resolver->hubVisible($user));

        $list = $this->resolver->listarPara($user);
        $this->assertCount(2, $list);
        $this->assertSame(ManualesCatalog::SLUG_REGISTRAR_PEDIDOS_VENDEDOR, $list[0]['slug']);
        $this->assertSame(ManualesCatalog::SLUG_CONTROL_PEDIDOS, $list[1]['slug']);

        $ids = array_column($list[1]['secciones'], 'id');
        $this->assertContains('vendedora', $ids);
        $this->assertNotContains('cedis', $ids);
        $this->assertNotContains('guias', $ids);
        $this->assertNotContains('auxiliar', $ids);
    }

    public function test_soporte_ve_todas_las_secciones(): void
    {
        $user = new FakeManualUser(['soporte.gestionar']);

        $resolved = $this->resolver->resolverShow(ManualesCatalog::SLUG_CONTROL_PEDIDOS, $user);
        $this->assertNotNull($resolved);
        $this->assertTrue($resolved['ve_todo']);

        $ids = array_column($resolved['secciones'], 'id');
        $this->assertEqualsCanonicalizing(
            ['vendedora', 'auxiliar', 'cedis', 'guias', 'direcciones'],
            $ids
        );
    }

    public function test_sin_permisos_no_hub(): void
    {
        $user = new FakeManualUser([]);
        $this->assertFalse($this->resolver->hubVisible($user));
        $this->assertSame([], $this->resolver->listarPara($user));
        $this->assertNull($this->resolver->resolverShow(ManualesCatalog::SLUG_CONTROL_PEDIDOS, $user));
    }

    public function test_pdf_payload_vendedora_sin_cedis_ni_guias(): void
    {
        $user = new FakeManualUser(['control_pedidos.ver_listado']);

        $payload = $this->pdf->payload(ManualesCatalog::SLUG_CONTROL_PEDIDOS, $user);
        $this->assertNotNull($payload);
        $this->assertContains('vendedora', $payload['secciones_ids']);
        $this->assertNotContains('cedis', $payload['secciones_ids']);
        $this->assertNotContains('guias', $payload['secciones_ids']);

        $secIds = array_column($payload['contenido']['secciones'], 'id');
        $this->assertContains('vendedora', $secIds);
        $this->assertNotContains('cedis', $secIds);
        $this->assertNotContains('guias', $secIds);
    }

    public function test_pdf_payload_soporte_incluye_cedis(): void
    {
        $user = new FakeManualUser(['soporte.administrar']);
        $payload = $this->pdf->payload(ManualesCatalog::SLUG_CONTROL_PEDIDOS, $user);
        $this->assertNotNull($payload);
        $this->assertContains('cedis', $payload['secciones_ids']);
        $this->assertContains('guias', $payload['secciones_ids']);
    }

    public function test_guia_vendedor_reutiliza_el_mismo_contenido_en_pdf(): void
    {
        $payload = $this->pdf->payload(
            ManualesCatalog::SLUG_REGISTRAR_PEDIDOS_VENDEDOR,
            new FakeManualUser(['control_pedidos.ver_listado'])
        );

        $this->assertNotNull($payload);
        $this->assertSame(['vendedor'], $payload['secciones_ids']);
        $this->assertSame(RegistrarPedidosVendedorManualContent::payload(), $payload['contenido']);
    }

    public function test_guia_vendedor_no_concede_acceso_a_otros_perfiles(): void
    {
        $slug = ManualesCatalog::SLUG_REGISTRAR_PEDIDOS_VENDEDOR;
        foreach ([[], ['control_pedidos.auditar'], ['control_pedidos.cedis'], ['clientes.direcciones.ver']] as $permisos) {
            $user = new FakeManualUser($permisos);
            $this->assertNull($this->resolver->resolverShow($slug, $user));
            $this->assertNull($this->pdf->descargar($slug, $user));
        }

        $this->assertNotNull($this->resolver->resolverShow($slug, new FakeManualUser(['soporte.gestionar'])));
        $this->assertNotNull($this->resolver->resolverShow($slug, new FakeManualUser([], ['Super Admin'])));
    }

    public function test_descarga_pdf_vendedor_renderiza_la_guia_y_las_ilustraciones(): void
    {
        $slug = ManualesCatalog::SLUG_REGISTRAR_PEDIDOS_VENDEDOR;
        $user = new FakeManualUser(['control_pedidos.ver_listado']);
        $payload = $this->pdf->payload($slug, $user);
        $html = view('manuales.registrar_pedidos_vendedor', $payload)->render();

        foreach ($payload['contenido']['casos'] as $caso) {
            $this->assertStringContainsString(e($caso['titulo']), $html);
            $this->assertStringContainsString(e($caso['esperar']), $html);
        }
        foreach ($payload['contenido']['pasos'] as $paso) {
            $this->assertStringContainsString(e($paso['visual']['titulo']), $html);
            $this->assertStringContainsString(e($paso['resultado']), $html);
        }

        $response = $this->pdf->descargar($slug, $user);
        $this->assertNotNull($response);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
