<?php

namespace Tests\Feature\Admin;

use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\ConfiguracionOperativaTienda;
use App\Services\ControlPedidos\PlazosRetrasoPedidoBmaConfig;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Support\ControlPedidos\CorteMismoDiaTienda;
use App\Support\ControlPedidos\PlazoApartadoTienda;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConfiguracionOperativaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function datos(): array
    {
        return ['tienda' => app(ConfiguracionOperativaTienda::class)->defaults(),
            'general' => ['zona_horaria' => 'America/Mexico_City', 'recordatorio_hora' => '11:00', 'cierre_operativo' => '20:00', 'dias_resguardo' => 3, 'dias_prorroga_vip' => 1],
            'plazos' => app(PlazosRetrasoPedidoBmaConfig::class)->configuracionPorDefecto()];
    }

    private function administrador(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('configuracion_sistema.gestionar', 'web'));

        return $user;
    }

    public function test_el_submodulo_exige_el_permiso_del_sistema(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/configuracion-operativa')->assertForbidden();
        $this->put('/admin/configuracion-operativa', $this->datos())->assertForbidden();
    }

    public function test_guardar_conecta_horarios_plazos_auditoria_y_caches(): void
    {
        $this->actingAs($this->administrador());
        app(PreparacionTiendaConfig::class)->zonaHoraria();
        app(PlazosRetrasoPedidoBmaConfig::class)->obtener();
        $datos = $this->datos();
        $datos['tienda']['md_dias'] = [6];
        $datos['tienda']['md_corte'] = '12:15';
        $datos['tienda']['sabado_apertura'] = '10:00';
        $datos['tienda']['sabado_cierre'] = '14:30';
        $datos['general']['recordatorio_hora'] = '10:45';
        $datos['plazos']['comercial']['dias_empaque'] = 4;
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('10:45', app(PreparacionTiendaConfig::class)->recordatorioHoraLocal());
        $this->assertSame(4, app(PlazosRetrasoPedidoBmaConfig::class)->obtener()['comercial']['dias_empaque']);
        $corte = app(CorteMismoDiaTienda::class);
        $zona = 'America/Mexico_City';
        $this->assertTrue($corte->evaluar(Carbon::parse('2026-11-07 12:15:00', $zona), $zona, true)['oportuno']);
        $this->assertFalse($corte->evaluar(Carbon::parse('2026-11-07 12:15:01', $zona), $zona, true)['oportuno']);
        $this->assertFalse($corte->evaluar(Carbon::parse('2026-11-06 10:00:00', $zona), $zona, true)['oportuno']);
        $plazo = app(PlazoApartadoTienda::class);
        $this->assertFalse($plazo->evaluarConfirmacion(Carbon::parse('2026-11-07 09:30', $zona), null)['permite']);
        $vigente = $plazo->evaluarConfirmacion(Carbon::parse('2026-11-07 10:30', $zona), null);
        $this->assertSame('14:30', $vigente['vence_at']->format('H:i'));
        $this->assertDatabaseHas('auditorias_configuraciones', ['modulo' => 'Configuración operativa']);
    }

    public function test_rechaza_configuracion_invalida_sin_guardar_parcialmente(): void
    {
        $this->actingAs($this->administrador());
        $datos = $this->datos();
        $datos['tienda']['md_corte'] = '25:70';
        $datos['tienda']['md_dias'] = [];
        $datos['tienda']['sabado_cierre'] = '08:00';
        $datos['general']['zona_horaria'] = 'Invalida';
        $datos['plazos']['hora_corte'] = '99:00';
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasErrors(['tienda.md_corte', 'tienda.md_dias', 'tienda.sabado_cierre', 'general.zona_horaria', 'plazos.hora_corte']);
        $this->assertDatabaseMissing('configuraciones_sistema', ['clave' => ConfiguracionOperativaTienda::CLAVE]);
    }

    public function test_las_sucursales_candidatas_dependen_del_catalogo_y_sus_capacidades(): void
    {
        $tipo = CatalogoTipoAlmacen::create(['nombre' => 'PDV']);
        $carmen = Sucursal::create(['codigo' => 'CC', 'nombre' => 'Carmen Cadena', 'activo' => true]);
        $city = Sucursal::create(['codigo' => 'CITY', 'nombre' => 'City Center', 'activo' => true]);
        $base = ['activo' => true, 'visible_en_pedidos' => true, 'tipo_almacen_id' => $tipo->id];
        $origen = Almacen::create($base + ['codigo' => 'CARMEN', 'nombre' => 'PDV Carmen', 'sucursal_id' => $carmen->id, 'permite_salida_pedidos' => true]);
        $destino = Almacen::create($base + ['codigo' => 'CITY', 'nombre' => 'PDV City', 'sucursal_id' => $city->id, 'permite_salida_pedidos' => false]);
        $catalogo = app(DestinoRevisionRegistrarPedidos::class);
        $this->assertSame([$origen->id], array_column($catalogo->catalogoTienda(), 'id'));
        $destino->update(['permite_salida_pedidos' => true]);
        $this->assertCount(2, $catalogo->catalogoTienda());
        $this->assertStringContainsString('City Center', $catalogo->catalogoTienda()[1]['nombre']);
        $city->update(['activo' => false]);
        $this->assertSame([$origen->id], array_column($catalogo->catalogoTienda(), 'id'));
        // El límite piloto sigue aplicando incluso con capacidad habilitada en catálogo.
        $city->update(['activo' => true]);
        ConfiguracionSistema::updateOrCreate(['clave' => PreparacionTiendaConfig::CLAVE_FLAG], ['valor' => json_encode(['almacenes_habilitados' => [$origen->id]]), 'tipo' => 'json']);
        app(PreparacionTiendaConfig::class)->olvidarCache();
        $this->assertSame([$origen->id], array_column($catalogo->catalogoTienda(), 'id'));
    }
}
