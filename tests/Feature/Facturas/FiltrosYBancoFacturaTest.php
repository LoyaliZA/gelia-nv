<?php

namespace Tests\Feature\Facturas;

use App\Models\Area;
use App\Models\CatalogoBanco;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\Departamento;
use App\Models\SolicitudFactura;
use App\Models\User;
use App\Services\Facturas\ActualizarBorradorFacturaService;
use App\Services\Facturas\CrearSolicitudFacturaService;
use App\Services\Facturas\ListarSolicitudesFacturaService;
use App\Services\Facturas\ResponderSolicitudFacturaService;
use App\Support\Facturas\BancoFacturacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FiltrosYBancoFacturaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        foreach (['Borrador', 'Pendiente', 'Respondida', 'Incorrecta'] as $nombre) {
            CatalogoEstadoSolicitud::updateOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        foreach (['facturas.crear', 'facturas.ver_listado', 'facturas.responder'] as $nombre) {
            Permission::findOrCreate($nombre, 'web');
        }
        Role::findOrCreate('Administrador', 'web');
    }

    private function vendedor(Departamento $departamento, ?Area $area = null): User
    {
        $usuario = User::factory()->create(['area_id' => $area?->id]);
        $usuario->givePermissionTo(['facturas.crear', 'facturas.ver_listado']);
        $usuario->departamentos()->attach($departamento);
        return $usuario;
    }

    private function solicitud(User $usuario, Departamento $departamento, string $folio): SolicitudFactura
    {
        return SolicitudFactura::create(['folio' => $folio, 'razon_social' => 'EMPRESA PRUEBA', 'vendedor_id' => $usuario->id, 'departamento_id' => $departamento->id, 'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Pendiente')]);
    }

    public function test_filtros_solo_incluyen_departamentos_y_areas_principales_visibles_con_solicitudes(): void
    {
        $d1 = Departamento::create(['nombre' => 'Ventas A', 'activo' => true]);
        $d2 = Departamento::create(['nombre' => 'Ventas B', 'activo' => true]);
        $a1 = Area::create(['nombre' => 'Mayoreo A', 'departamento_id' => $d1->id]);
        $a2 = Area::create(['nombre' => 'Mayoreo B', 'departamento_id' => $d2->id]);
        $sinFacturas = Area::create(['nombre' => 'Sin solicitudes', 'departamento_id' => $d1->id]);
        $u1 = $this->vendedor($d1, $a1);
        $u2 = $this->vendedor($d2, $a2);
        $this->vendedor($d1, $sinFacturas);
        $u1->areas()->attach($a2); // A secondary assignment must not become a principal-area option.
        $f1 = $this->solicitud($u1, $d1, 'TEST-A');
        $this->solicitud($u2, $d2, 'TEST-B');
        $service = app(ListarSolicitudesFacturaService::class);
        $opciones = $service->opcionesFiltros($u1);
        $this->assertSame([$d1->id], $opciones['departamentos']->pluck('id')->all());
        $this->assertSame([$a1->id], $opciones['areas']->pluck('id')->all());
        $this->assertSame([$u1->id], $opciones['vendedores']->pluck('id')->all());
        $this->assertEmpty($service->ejecutar($u1, ['departamento_id' => $d2->id], false));
        $this->assertEmpty($service->ejecutar($u1, ['area_id' => $a2->id], false));
        $admin = User::factory()->create();
        $admin->assignRole('Administrador');
        $this->assertSame([$f1->id], $service->ejecutar($admin, ['departamento_id' => $d1->id, 'area_id' => $a1->id], false)->pluck('id')->all());
        $this->assertSame(1, $service->metricas($admin, ['departamento_id' => $d1->id, 'tab' => 'RESPONDIDAS'])['pendientes']);
        $this->assertCount(2, $service->opcionesFiltros($admin)['areas']);
    }

    public function test_filtro_de_fecha_funciona_con_un_solo_limite(): void
    {
        $d = Departamento::create(['nombre' => 'Ventas fechas', 'activo' => true]);
        $u = $this->vendedor($d);
        $antes = $this->solicitud($u, $d, 'FECHA-ANTES');
        $antes->forceFill(['created_at' => '2026-09-01 12:00:00'])->save();
        $despues = $this->solicitud($u, $d, 'FECHA-DESPUES');
        $despues->forceFill(['created_at' => '2026-10-01 12:00:00'])->save();
        $service = app(ListarSolicitudesFacturaService::class);
        $this->assertSame([$despues->id], $service->ejecutar($u, ['fecha_inicio' => '2026-10-01'], false)->pluck('id')->all());
        $this->assertSame([$antes->id], $service->ejecutar($u, ['fecha_fin' => '2026-09-01'], false)->pluck('id')->all());
    }

    public function test_banco_bloqueado_sigue_visible_y_se_guarda_en_borrador_pero_no_se_envia(): void
    {
        $d = Departamento::create(['nombre' => 'Ventas bancos', 'activo' => true]);
        $u = $this->vendedor($d);
        $banco = CatalogoBanco::create(['nombre' => 'Banco restringido', 'activo' => true, 'compartido' => true, 'facturacion_habilitada' => false, 'motivo_bloqueo_facturacion' => 'Cuenta temporalmente restringida.']);
        $this->assertContains($banco->id, app(\App\Services\ControlPedidos\BancosDepartamentoService::class)->disponibles($d->id)->pluck('id'));
        $resultado = app(CrearSolicitudFacturaService::class)->ejecutar(['modo' => 'borrador', 'razon_social' => 'EMPRESA PRUEBA', 'catalogo_banco_id' => $banco->id], $u->id);
        $factura = $resultado['solicitud'];
        $this->assertSame($banco->id, $factura->catalogo_banco_id);
        try {
            app(ActualizarBorradorFacturaService::class)->ejecutar($factura, ['enviar_ahora' => true], $u);
            $this->fail('El envío de un banco bloqueado debe rechazarse.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Cuenta temporalmente restringida', $e->errors()['catalogo_banco_id'][0]);
        }
        $this->assertSame('Borrador', $factura->fresh()->estado->nombre);
    }

    public function test_creacion_directa_rechaza_banco_bloqueado_antes_de_guardar_archivos(): void
    {
        $d = Departamento::create(['nombre' => 'Ventas directas', 'activo' => true]);
        $u = $this->vendedor($d);
        $banco = CatalogoBanco::create(['nombre' => 'Banco sin facturación', 'activo' => true, 'compartido' => true, 'facturacion_habilitada' => false]);
        $this->actingAs($u)->postJson(route('facturas.store'), ['razon_social' => 'EMPRESA PRUEBA', 'catalogo_banco_id' => $banco->id, 'vouchers' => [UploadedFile::fake()->create('pago.pdf', 20, 'application/pdf')]])->assertUnprocessable()->assertJsonValidationErrors('catalogo_banco_id');
        $this->assertSame(0, SolicitudFactura::count());
    }

    public function test_banco_es_obligatorio_en_nuevas_solicitudes_internas(): void
    {
        $d = Departamento::create(['nombre' => 'Ventas nuevo banco', 'activo' => true]);
        $u = $this->vendedor($d);
        $this->actingAs($u)->postJson(route('facturas.store'), ['razon_social' => 'EMPRESA PRUEBA', 'vouchers' => [UploadedFile::fake()->create('pago.pdf', 20, 'application/pdf')]])->assertUnprocessable()->assertJsonValidationErrors('catalogo_banco_id');
    }

    public function test_banco_habilitado_persiste_y_emision_revalida_un_bloqueo_posterior(): void
    {
        $d = Departamento::create(['nombre' => 'Ventas emisión', 'activo' => true]);
        $u = $this->vendedor($d);
        $banco = CatalogoBanco::create(['nombre' => 'Banco habilitado', 'activo' => true, 'compartido' => true, 'facturacion_habilitada' => true]);
        $this->actingAs($u)->postJson(route('facturas.store'), ['razon_social' => 'EMPRESA PRUEBA', 'catalogo_banco_id' => $banco->id, 'vouchers' => [UploadedFile::fake()->create('pago.pdf', 20, 'application/pdf')]])->assertOk();
        $factura = SolicitudFactura::firstOrFail();
        $this->assertSame($banco->id, $factura->catalogo_banco_id);
        $banco->update(['facturacion_habilitada' => false]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ResponderSolicitudFacturaService::class)->ejecutar($factura, ['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Respondida')], $u);
    }

    public function test_reparacion_no_permite_borrar_un_banco_restringido_para_evitar_el_bloqueo(): void
    {
        $d = Departamento::create(['nombre' => 'Ventas reparación', 'activo' => true]);
        $u = $this->vendedor($d);
        $banco = CatalogoBanco::create(['nombre' => 'Banco restringido reparación', 'activo' => true, 'compartido' => true, 'facturacion_habilitada' => false]);
        $factura = $this->solicitud($u, $d, 'BANCO-REPARACION');
        $factura->update(['catalogo_banco_id' => $banco->id, 'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Incorrecta')]);
        try {
            app(\App\Services\Facturas\RepararSolicitudFacturaService::class)->ejecutar($factura, ['catalogo_banco_id' => null, 'razon_social' => 'EMPRESA PRUEBA'], $u);
            $this->fail('La reparación debe mantener el requisito de banco.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('catalogo_banco_id', $e->errors());
        }
        $this->assertSame($banco->id, $factura->fresh()->catalogo_banco_id);
    }

    public function test_banco_de_otro_departamento_no_puede_usarse_en_facturas(): void
    {
        $d = Departamento::create(['nombre' => 'Ventas privado', 'activo' => true]);
        $banco = CatalogoBanco::create(['nombre' => 'Banco privado', 'activo' => true, 'compartido' => false, 'facturacion_habilitada' => true]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        BancoFacturacion::validar($banco->id, $d->id, true);
    }

    public function test_listado_sigue_funcionando_antes_de_aplicar_migracion_bancaria(): void
    {
        $d = Departamento::create(['nombre' => 'Ventas despliegue', 'activo' => true]);
        $u = $this->vendedor($d);
        $factura = $this->solicitud($u, $d, 'PRE-MIGRACION');
        Schema::table('solicitudes_facturas', fn ($table) => $table->dropConstrainedForeignId('catalogo_banco_id'));
        Schema::table('catalogo_bancos', fn ($table) => $table->dropColumn(['facturacion_habilitada', 'motivo_bloqueo_facturacion']));
        $this->assertFalse(BancoFacturacion::configurado());
        $this->assertSame([$factura->id], app(ListarSolicitudesFacturaService::class)->ejecutar($u, [], false)->pluck('id')->all());
        $this->actingAs($u)->postJson(route('facturas.store'), ['razon_social' => 'SOLICITUD DURANTE DESPLIEGUE', 'vouchers' => [UploadedFile::fake()->create('pago.pdf', 20, 'application/pdf')]])->assertOk();
        $this->assertSame(2, SolicitudFactura::count());
    }
}
