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
        $datos['tienda']['horarios_activos'] = true;
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

    public function test_horarios_apagados_por_defecto_permiten_jornadas_y_se_activan_sin_perder_fechas(): void
    {
        $config = app(ConfiguracionOperativaTienda::class);
        $this->assertFalse($config->horariosActivos());
        $zona = 'America/Mexico_City';
        $domingo = Carbon::parse('2026-11-08 23:00', $zona);
        $vence = $domingo->copy()->subDays(2);
        $plazo = app(PlazoApartadoTienda::class);
        $this->assertTrue($plazo->evaluarConfirmacion($domingo, null)['permite']);
        $this->assertNull($plazo->evaluarConfirmacion($domingo, null)['vence_at']);
        $this->assertTrue($plazo->evaluarConfirmacion($domingo, $vence)['vence_at']->equalTo($vence));
        $this->assertTrue($config->diaMd($domingo));
        $this->assertTrue(app(CorteMismoDiaTienda::class)->evaluar($domingo, $zona, true)['oportuno']);
        $this->actingAs($this->administrador());
        $datos = $this->datos();
        $datos['tienda']['horarios_activos'] = true;
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasNoErrors();
        $this->assertTrue($config->horariosActivos());
        $rechazo = $plazo->evaluarConfirmacion($domingo, $vence);
        $this->assertFalse($rechazo['permite']);
        $this->assertStringContainsString('17:00', $rechazo['mensaje']);
        $this->assertStringContainsString($zona, $rechazo['mensaje']);
        $this->assertFalse($config->diaMd($domingo));
        unset($datos['tienda']['horarios_activos']);
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasNoErrors();
        $this->assertTrue($config->horariosActivos());
        $datos['tienda']['horarios_activos'] = 'inválido';
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasErrors('tienda.horarios_activos');
        $this->assertTrue($config->horariosActivos());
        $datos['tienda']['horarios_activos'] = false;
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasNoErrors();
        $this->assertFalse($config->horariosActivos());
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

    public function test_el_seguimiento_posterior_es_opcional_apagado_por_defecto_y_persistente(): void
    {
        $config = app(ConfiguracionOperativaTienda::class);
        $this->assertFalse($config->seguimientoEntregaPdvHabilitado());
        $this->actingAs($this->administrador());
        $datos = $this->datos();
        $datos['tienda']['seguimiento_entrega_pdv'] = true;
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasNoErrors();
        $this->assertTrue($config->seguimientoEntregaPdvHabilitado());
        unset($datos['tienda']['seguimiento_entrega_pdv']);
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasNoErrors();
        $this->assertTrue($config->seguimientoEntregaPdvHabilitado());
        $datos['tienda']['seguimiento_entrega_pdv'] = 'invalido';
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasErrors('tienda.seguimiento_entrega_pdv');
        $this->assertTrue($config->seguimientoEntregaPdvHabilitado());
        $datos['tienda']['seguimiento_entrega_pdv'] = false;
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasNoErrors();
        $this->assertFalse($config->seguimientoEntregaPdvHabilitado());
    }

    public function test_bancos_y_prioridades_se_guardan_con_auditoria_y_se_conservan_si_se_omiten(): void
    {
        $this->actingAs($this->administrador());
        $banco = \App\Models\CatalogoBanco::create(['nombre' => 'Banco configurable', 'activo' => true, 'compartido' => true]);
        $datos = $this->datos();
        $datos['bancos'] = [['id' => $banco->id, 'habilitado' => true, 'compartido' => true, 'departamento_ids' => []]];
        $datos['prioridades'] = ['municipal', 'mismo_dia', 'tienda', 'resguardo', 'nacional'];
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasNoErrors();
        $this->assertSame($datos['prioridades'], app(\App\Services\ControlPedidos\PrioridadAtencionPedidosConfig::class)->obtener());
        $this->assertSame($datos['bancos'], app(\App\Services\ControlPedidos\BancosGestionPedidosConfig::class)->obtener());
        $this->put('/admin/configuracion-operativa', $this->datos())->assertSessionHasNoErrors();
        $this->assertSame($datos['bancos'], app(\App\Services\ControlPedidos\BancosGestionPedidosConfig::class)->obtener());
        $this->assertDatabaseHas('auditorias_configuraciones', ['modulo' => 'Configuración operativa']);
        $datos['bancos'][0]['compartido'] = false;
        $datos['prioridades'][1] = 'municipal';
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasErrors(['bancos.0.departamento_ids', 'prioridades.0']);
    }

    public function test_un_departamento_puede_usar_varios_bancos_y_no_se_duplican_asignaciones(): void
    {
        $this->actingAs($this->administrador());
        $depto = \App\Models\Departamento::create(['nombre' => 'Departamento compartido', 'codigo' => 'DC', 'activo' => true]);
        $a = \App\Models\CatalogoBanco::create(['nombre' => 'Cuenta uno', 'activo' => true]);
        $b = \App\Models\CatalogoBanco::create(['nombre' => 'Cuenta dos', 'activo' => true]);
        $datos = $this->datos();
        $datos['bancos'] = array_map(fn ($id) => ['id' => $id, 'habilitado' => true, 'compartido' => false, 'departamento_ids' => [$depto->id]], [$a->id, $b->id]);
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], app(\App\Services\ControlPedidos\BancosGestionPedidosConfig::class)->catalogo([$depto->id])->pluck('id')->all());
        $datos['bancos'][0]['departamento_ids'][] = $depto->id;
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasErrors('bancos.0.departamento_ids');
    }

    public function test_banco_restringido_sin_departamentos_y_ids_inexistentes_no_guardan_nada(): void
    {
        $this->actingAs($this->administrador());
        $banco = \App\Models\CatalogoBanco::create(['nombre' => 'Banco configurable', 'activo' => true]);
        $datos = $this->datos();
        $datos['bancos'] = [['id' => $banco->id, 'habilitado' => true, 'compartido' => false, 'departamento_ids' => []]];
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasErrors('bancos.0.departamento_ids');
        $this->assertDatabaseMissing('configuraciones_sistema', ['clave' => \App\Services\ControlPedidos\BancosGestionPedidosConfig::CLAVE]);
        $datos['bancos'][0]['departamento_ids'] = [999999];
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasErrors('bancos.0.departamento_ids.0');
        $datos['bancos'][0]['id'] = 999999;
        $this->put('/admin/configuracion-operativa', $datos)->assertSessionHasErrors('bancos.0.id');
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
