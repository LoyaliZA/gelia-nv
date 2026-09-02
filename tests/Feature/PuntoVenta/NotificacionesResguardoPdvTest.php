<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\IncidenciaResguardoPdvRegistrada;
use App\Listeners\PuntoVenta\NotificarIncidenciaResguardoPdv;
use App\Listeners\PuntoVenta\NotificarRecepcionFisicaResguardoPdv;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\PuntoVenta\AlertaResguardoPdvNotification;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotificacionesResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'broadcasting.default' => 'log',
        ]);

        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal A']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal B']);
    }

    public function test_recepcion_fisica_notifica_usuarios_con_permiso_y_sucursal(): void
    {
        $resguardo = $this->crearResguardo();
        $destinatario = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            $this->sucursal
        );
        $sinPermiso = $this->usuarioConPermisos([], $this->sucursal);
        $otraSucursal = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            $this->otraSucursal
        );

        app(NotificarResguardoPdvService::class)->recepcionFisica(
            $resguardo,
            (int) $this->sucursal->id,
            'pdv:rec:test:1'
        );

        $this->assertCount(1, $destinatario->fresh()->notifications);
        $this->assertCount(0, $sinPermiso->fresh()->notifications);
        $this->assertCount(0, $otraSucursal->fresh()->notifications);
    }

    public function test_recepcion_esperada_incluye_quien_puede_recibir(): void
    {
        $resguardo = $this->crearResguardo();
        $receptor = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR],
            $this->sucursal
        );

        app(NotificarResguardoPdvService::class)->recepcionEsperada(
            $resguardo,
            (int) $this->sucursal->id,
            'pdv:handoff:test:1'
        );

        $this->assertCount(1, $receptor->fresh()->notifications);
        $data = $receptor->notifications->first()->data;
        $this->assertSame(AlertaResguardoPdvNotification::TIPO_RECEPCION_ESPERADA, $data['tipo']);
    }

    public function test_incidencia_usa_permiso_especifico_y_notifica_autorizador(): void
    {
        $resguardo = $this->crearResguardo();
        $autorizador = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA],
            $this->sucursal
        );
        $sinPermiso = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR],
            $this->sucursal
        );

        $incidencia = ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_DANO,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Empaque roto',
            'reportado_por_id' => $autorizador->id,
            'reportado_at' => now(),
            'version' => 1,
        ]);

        app(NotificarResguardoPdvService::class)->incidencia(
            $resguardo,
            $incidencia,
            (int) $this->sucursal->id,
            'pdv:inc:test:1'
        );

        $this->assertCount(1, $autorizador->fresh()->notifications);
        $this->assertCount(0, $sinPermiso->fresh()->notifications);
    }

    public function test_reintento_no_duplica_notificaciones_persistidas(): void
    {
        $resguardo = $this->crearResguardo();
        $usuario = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            $this->sucursal
        );
        $servicio = app(NotificarResguardoPdvService::class);
        $clave = 'pdv:ent:test:dup';

        $servicio->entrega($resguardo, (int) $this->sucursal->id, $clave);
        $servicio->entrega($resguardo, (int) $this->sucursal->id, $clave);

        $this->assertCount(1, $usuario->fresh()->notifications);
    }

    public function test_payload_seguro_y_enlace_al_detalle(): void
    {
        $resguardo = $this->crearResguardo([
            'snapshot_folio' => 'REM-9001',
            'snapshot_cliente_nombre' => 'Cliente Confidencial Completo',
        ]);
        $usuario = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            $this->sucursal
        );

        app(NotificarResguardoPdvService::class)->recepcionFisica(
            $resguardo,
            (int) $this->sucursal->id,
            'pdv:rec:test:payload'
        );

        $data = $usuario->fresh()->notifications->first()->data;

        $this->assertSame('/punto-venta/resguardos/'.$resguardo->id, $data['url']);
        $this->assertSame('REM-9001', $data['folio']);
        $this->assertSame('punto_venta', $data['modulo']);
        $this->assertArrayNotHasKey('snapshot_cliente_nombre', $data);
        $this->assertStringNotContainsString('Cliente Confidencial', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function test_fallo_de_notificacion_no_propaga_excepcion(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('canal caído'));

        $resguardo = $this->crearResguardo();
        $this->usuarioConPermisos([PuntoVentaModulo::PERMISO_RESGUARDOS_VER], $this->sucursal);

        app(NotificarResguardoPdvService::class)->recepcionFisica(
            $resguardo,
            (int) $this->sucursal->id,
            'pdv:rec:test:fail'
        );

        $this->assertTrue(true);
    }

    public function test_listener_recepcion_fisica_invoca_servicio_con_clave_del_evento(): void
    {
        $resguardo = $this->crearResguardo();
        $usuario = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            $this->sucursal
        );

        $evento = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'estado_anterior' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $usuario->id,
            'ocurrido_at' => now(),
            'idempotency_key' => 'pdv:rec:listener:1',
        ]);

        app(NotificarRecepcionFisicaResguardoPdv::class)->handle(
            new \App\Events\PuntoVenta\RecepcionFisicaPdvCompletada(
                $resguardo,
                $evento,
                (int) $this->sucursal->id
            )
        );

        $notificacion = $usuario->fresh()->notifications->first();
        $this->assertNotNull($notificacion);
        $this->assertStringContainsString('pdv:rec:listener:1', (string) $notificacion->data['idempotency_key']);
    }

    public function test_listener_incidencia_notifica_por_evento_de_dominio(): void
    {
        $resguardo = $this->crearResguardo();
        $usuario = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE],
            $this->sucursal
        );

        $incidencia = ResguardoPdvIncidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo' => ResguardoPdvIncidencia::TIPO_FALTANTE,
            'estado' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
            'descripcion' => 'Falta un bulto',
            'reportado_por_id' => $usuario->id,
            'reportado_at' => now(),
            'version' => 1,
        ]);
        $evento = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_INCIDENCIA_FALTANTE,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $usuario->id,
            'ocurrido_at' => now(),
            'idempotency_key' => 'pdv:inc:listener:1',
        ]);

        app(NotificarIncidenciaResguardoPdv::class)->handle(
            new IncidenciaResguardoPdvRegistrada(
                $resguardo,
                $incidencia,
                $evento,
                (int) $this->sucursal->id,
                (int) $usuario->id
            )
        );

        $this->assertCount(1, $usuario->fresh()->notifications);
        $this->assertSame(
            AlertaResguardoPdvNotification::TIPO_INCIDENCIA,
            $usuario->notifications->first()->data['tipo']
        );
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearResguardo(array $atributos = []): ResguardoPdv
    {
        return ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $this->sucursal->id,
            'snapshot_folio' => 'BMA-TEST-1',
        ], $atributos));
    }

    /**
     * @param  list<string>  $permisos
     */
    private function usuarioConPermisos(array $permisos, Sucursal $sucursal): User
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $usuario = User::factory()->create();
        if ($permisos !== []) {
            $usuario->givePermissionTo($permisos);
        }
        $usuario->concederAccesoSucursal($sucursal, esPrincipal: true);

        return $usuario;
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
