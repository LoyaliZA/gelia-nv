<?php

namespace Tests\Feature\Manuales;

use App\Models\User;
use App\Support\Manuales\Content\RegistrarPedidosVendedorManualContent;
use App\Support\Manuales\ManualesCatalog;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class RegistrarPedidosVendedorManualTest extends TestCase
{
    private function vendedor(array $permisos): void
    {
        // El acceso del controller se comprueba sin depender de una BD de usuarios.
        $this->withoutMiddleware();
        Inertia::flushShared();
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('can')->andReturnUsing(fn ($permiso) => in_array($permiso, $permisos, true));
        $this->actingAs($user);
    }

    public function test_vendedor_abre_la_guia_con_el_contenido_compartido_del_pdf(): void
    {
        $this->vendedor(['control_pedidos.ver_listado']);
        $slug = ManualesCatalog::SLUG_REGISTRAR_PEDIDOS_VENDEDOR;

        $this->get(route('soporte.manuales.show', $slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Soporte/Manuales/Show')
                ->where('manual.slug', $slug)
                ->where('contenido', RegistrarPedidosVendedorManualContent::payload())
                ->where('pdf_url', route('soporte.manuales.pdf', $slug))
                ->etc());
    }

    public function test_vendedor_descarga_un_pdf_real(): void
    {
        $this->vendedor(['control_pedidos.ver_listado']);

        $response = $this->get(route('soporte.manuales.pdf', ManualesCatalog::SLUG_REGISTRAR_PEDIDOS_VENDEDOR));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_perfil_sin_acceso_no_puede_abrir_ni_descargar_la_guia(): void
    {
        $this->vendedor(['control_pedidos.cedis']);
        $slug = ManualesCatalog::SLUG_REGISTRAR_PEDIDOS_VENDEDOR;

        $this->get(route('soporte.manuales.show', $slug))->assertForbidden();
        $this->get(route('soporte.manuales.pdf', $slug))->assertForbidden();
    }

    public function test_slug_inexistente_responde_no_encontrado(): void
    {
        $this->vendedor(['control_pedidos.ver_listado']);
        $this->get(route('soporte.manuales.show', 'manual-inexistente'))->assertNotFound();
        $this->get(route('soporte.manuales.pdf', 'manual-inexistente'))->assertNotFound();
    }
}
