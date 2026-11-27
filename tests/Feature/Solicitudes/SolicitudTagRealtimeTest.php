<?php

namespace Tests\Feature\Solicitudes;

use App\Events\SolicitudTagActualizada;
use App\Models\Area;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\CatalogoTipoCliente;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use ReflectionProperty;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudTagRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private CatalogoListaDescuento $lista;

    private CatalogoProceso $proceso;

    private CatalogoTipoCliente $tipo;

    private User $vendedor;

    private Departamento $departamento;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        foreach (['Pendiente', 'Respondida', 'Verificada', 'Incorrecta', 'Cancelada'] as $estado) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $estado], ['activo' => true]);
        }
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->lista = CatalogoListaDescuento::create([
            'nombre' => 'PUBLICO GENERAL',
            'monto_requerido' => 0,
            'activo' => true,
            'visible_en_solicitudes' => true,
        ]);
        $this->tipo = CatalogoTipoCliente::create(['nombre' => 'ACTIVO', 'activo' => true]);
        CatalogoTipoCliente::reiniciarCache();
        $this->proceso = CatalogoProceso::create([
            'nombre' => 'ASIGNAR TAG Y CAMBIO DE LISTA',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        foreach ([
            'solicitudes.crear',
            'solicitudes.ver_listado',
            'solicitudes.verificar',
            'solicitudes.reportar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Administrador', 'web');
        $this->departamento = Departamento::create(['nombre' => 'Ventas']);
        $area = Area::create(['departamento_id' => $this->departamento->id, 'nombre' => 'Mostrador']);
        $this->vendedor = User::factory()->create(['area_id' => $area->id]);
        $this->vendedor->givePermissionTo(['solicitudes.crear', 'solicitudes.ver_listado']);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    }

    public function test_el_evento_usa_el_canal_y_payload_de_tags(): void
    {
        $evento = new SolicitudTagActualizada(9, 'creada', 3, 4, 5);

        $this->assertSame('solicitud-tag.actualizada', $evento->broadcastAs());
        $this->assertSame(['private-solicitudes.tags'], array_map(
            fn ($canal) => $canal->name,
            $evento->broadcastOn()
        ));
        $this->assertSame([
            'solicitud_id' => 9,
            'accion' => 'creada',
            'por_usuario_id' => 3,
            'vendedor_id' => 4,
            'departamento_id' => 5,
        ], $evento->broadcastWith());
    }

    public function test_crear_solicitud_despacha_evento_en_tiempo_real(): void
    {
        Event::fake([SolicitudTagActualizada::class]);
        $cliente = $this->cliente();

        $this->actingAs($this->vendedor)->post(route('solicitudes.store'), [
            'numero_cliente' => $cliente->numero_cliente,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_tipo_cliente_id' => $this->tipo->id,
            'monto_cotizado' => 100,
            'confirmo_informacion_escalonamiento' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $solicitud = SolicitudTag::query()->where('cliente_id', $cliente->id)->firstOrFail();

        Event::assertDispatched(
            SolicitudTagActualizada::class,
            fn (SolicitudTagActualizada $evento) => $evento->accion === 'creada'
                && $evento->solicitudId === (int) $solicitud->id
                && $evento->porUsuarioId === (int) $this->vendedor->id
                && $evento->vendedorId === (int) $this->vendedor->id
                && $evento->departamentoId === (int) $this->departamento->id
        );
    }

    public function test_el_canal_tags_admite_ver_listado_y_rechaza_sin_permiso(): void
    {
        $callback = $this->callbackCanal('solicitudes.tags');
        $this->assertIsCallable($callback);

        $sinListado = User::factory()->create();

        $this->assertTrue((bool) $callback($this->vendedor));
        $this->assertFalse((bool) $callback($sinListado));
    }

    public function test_rechazar_pagos_vencidos_despacha_evento_sin_usuario(): void
    {
        Event::fake([SolicitudTagActualizada::class]);
        $cliente = $this->cliente();
        $solicitud = SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $this->vendedor->id,
            'departamento_id' => $this->departamento->id,
            'catalogo_proceso_id' => $this->proceso->id,
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Respondida'),
            'monto_cotizado' => 100,
            'pago_confirmado' => false,
            'compra_en_tienda' => false,
            'compra_en_tienda_solo_tag' => false,
        ]);
        $solicitud->created_at = now()->subHours(25);
        $solicitud->save();

        Artisan::call('pagos:rechazar-vencidos');

        Event::assertDispatched(
            SolicitudTagActualizada::class,
            fn (SolicitudTagActualizada $evento) => $evento->accion === 'vencida'
                && $evento->solicitudId === (int) $solicitud->id
                && $evento->porUsuarioId === null
                && $evento->vendedorId === (int) $this->vendedor->id
                && $evento->departamentoId === (int) $this->departamento->id
        );
    }

    private function callbackCanal(string $nombre): mixed
    {
        $manager = app(BroadcastManager::class);
        $drivers = (new ReflectionProperty($manager, 'drivers'))->getValue($manager);

        foreach ($drivers as $driver) {
            $canales = (new ReflectionProperty($driver, 'channels'))->getValue($driver);
            if (isset($canales[$nombre])) {
                return $canales[$nombre];
            }
        }

        $canales = (new ReflectionProperty(\Illuminate\Support\Facades\Broadcast::connection(), 'channels'))
            ->getValue(\Illuminate\Support\Facades\Broadcast::connection());

        return $canales[$nombre] ?? null;
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'numero_cliente' => (string) random_int(8000, 8999),
            'nombre' => 'Cliente realtime',
            'lista_actual_id' => $this->lista->id,
            'monto_venta_actual' => '100.00',
            'es_inactivo' => false,
            'es_menudeo' => false,
            'catalogo_tipo_cliente_id' => $this->tipo->id,
        ]);
    }
}
