<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\TurnoAsignado;
use App\Events\PuntoVenta\TurnoTransferido;
use App\Listeners\PuntoVenta\NotificarTurnoPdvSubscriber;
use App\Models\ConfiguracionSistema;
use App\Models\PushSubscription;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\PuntoVenta\AlertaResguardoPdvNotification;
use App\Notifications\PuntoVenta\AlertaTurnoPdvNotification;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;
use App\Services\PuntoVenta\Turnos\NotificarTurnoPdvService;
use App\Services\WebPush\ConstruirPayloadDesdeNotificacionService;
use App\Services\WebPush\EnviarWebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebPushPdvTest extends TestCase
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
            'webpush.enabled' => true,
            'webpush.vapid.public_key' => 'test-public-key',
            'webpush.vapid.private_key' => 'test-private-key',
        ]);

        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Push']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Push B']);
    }

    public function test_turno_asignado_notifica_solo_usuario_de_sucursal(): void
    {
        [$turno, $atencion, $evento, $vendedor, $otro] = $this->contextoTurnoAsignado();

        app(NotificarTurnoPdvService::class)->desdeEvento(
            $turno,
            $atencion,
            $evento,
            (int) $this->sucursal->id,
        );

        $this->assertCount(1, $vendedor->fresh()->notifications);
        $this->assertCount(0, $otro->fresh()->notifications);

        $data = $vendedor->notifications->first()->data;
        $this->assertSame(AlertaTurnoPdvNotification::TIPO_ASIGNADO, $data['tipo']);
        $this->assertSame('/punto-venta/turnos/ventas', $data['url']);
        $this->assertStringNotContainsString(
            (string) $turno->snapshot_nombre_llamado,
            (string) $data['mensaje_visible']
        );
    }

    public function test_turno_transferido_notifica_solo_destino_en_sucursal(): void
    {
        [$turno, , , $origen, ] = $this->contextoTurnoAsignado();
        $destino = $this->usuarioConPermisos([], $this->sucursal);
        $destinoOtraSucursal = $this->usuarioConPermisos([], $this->otraSucursal);

        $atencionNueva = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $destino->id,
            'numero_secuencia' => 2,
            'es_transferencia' => true,
        ]);
        $evento = TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'atencion_id' => $atencionNueva->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_TRANSFERIDO,
            'estado_anterior' => TurnoPdv::ESTADO_ASIGNADO,
            'estado_nuevo' => TurnoPdv::ESTADO_ASIGNADO,
            'ocurrido_at' => now(),
        ]);

        app(NotificarTurnoPdvSubscriber::class)->handleTransferido(new TurnoTransferido(
            $turno,
            TurnoPdvAtencion::query()->where('user_id', $origen->id)->firstOrFail(),
            $atencionNueva,
            $evento,
            (int) $this->sucursal->id,
        ));

        $this->assertCount(1, $destino->fresh()->notifications);
        $this->assertCount(0, $destinoOtraSucursal->fresh()->notifications);
        $this->assertSame(
            AlertaTurnoPdvNotification::TIPO_TRANSFERIDO,
            $destino->notifications->first()->data['tipo']
        );
    }

    public function test_evento_duplicado_no_duplica_notificacion_turno(): void
    {
        [$turno, $atencion, $evento, $vendedor] = $this->contextoTurnoAsignado();
        $servicio = app(NotificarTurnoPdvService::class);

        $servicio->desdeEvento($turno, $atencion, $evento, (int) $this->sucursal->id);
        $servicio->desdeEvento($turno, $atencion, $evento, (int) $this->sucursal->id);

        $this->assertCount(1, $vendedor->fresh()->notifications);
    }

    public function test_payload_pdv_usa_tag_idempotente_y_deep_link_seguro(): void
    {
        $payload = app(ConstruirPayloadDesdeNotificacionService::class)->desdeArray([
            'modulo' => 'punto_venta',
            'tipo' => AlertaResguardoPdvNotification::TIPO_RECEPCION_FISICA,
            'titulo' => 'Recepción física: BMA-1',
            'mensaje_visible' => 'Resguardo BMA-1 recibido y en custodia.',
            'resguardo_id' => 15,
            'folio' => 'BMA-1',
            'sucursal_id' => $this->sucursal->id,
            'url' => '/punto-venta/resguardos/15',
            'idempotency_key' => 'pdv:notify:pdv.resguardo.recepcion_fisica:abc',
        ]);

        $this->assertSame(url('/punto-venta/resguardos/15'), $payload['url']);
        $this->assertSame(
            'gelia-'.sha1('pdv:notify:pdv.resguardo.recepcion_fisica:abc'),
            $payload['tag']
        );
        $this->assertArrayNotHasKey('snapshot_nombre_llamado', $payload['data']);
        $this->assertSame(15, $payload['data']['resguardo_id']);
    }

    public function test_listener_web_push_envia_payload_minimo_sin_duplicar_por_reintento(): void
    {
        [$turno, $atencion, $evento, $vendedor] = $this->contextoTurnoAsignado();

        $webPush = Mockery::mock(EnviarWebPushService::class);
        $webPush->shouldReceive('estaConfigurado')->andReturn(true);
        $webPush->shouldReceive('enviarAUsuario')
            ->once()
            ->withArgs(function ($user, array $payload) use ($vendedor, $turno, $evento): bool {
                return $user->id === $vendedor->id
                    && $payload['url'] === url('/punto-venta/turnos/ventas')
                    && $payload['body'] === "Turno {$turno->folio} asignado para atención."
                    && $payload['tag'] === 'gelia-'.sha1('pdv:notify:'.TurnoPdvEvento::TIPO_ASIGNADO.':'.$evento->id);
            });
        $this->app->instance(EnviarWebPushService::class, $webPush);

        app(NotificarTurnoPdvSubscriber::class)->handleAsignado(new TurnoAsignado(
            $turno,
            $atencion,
            $evento,
            (int) $this->sucursal->id,
        ));

        app(NotificarTurnoPdvSubscriber::class)->handleAsignado(new TurnoAsignado(
            $turno,
            $atencion,
            $evento,
            (int) $this->sucursal->id,
        ));
    }

    public function test_fallo_web_push_no_impide_notificacion_database(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'snapshot_folio' => 'BMA-PUSH-1',
        ]);
        $usuario = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            $this->sucursal
        );

        $webPush = Mockery::mock(EnviarWebPushService::class);
        $webPush->shouldReceive('estaConfigurado')->andReturn(true);
        $webPush->shouldReceive('enviarAUsuario')->andThrow(new \RuntimeException('Push caído'));
        $this->app->instance(EnviarWebPushService::class, $webPush);

        app(NotificarResguardoPdvService::class)->recepcionFisica(
            $resguardo,
            (int) $this->sucursal->id,
            'pdv:push:fail:1'
        );

        $this->assertCount(1, $usuario->fresh()->notifications);
    }

    public function test_suscripcion_invalida_se_elimina_al_fallar_envio(): void
    {
        $usuario = User::factory()->create();
        $suscripcion = PushSubscription::query()->create([
            'user_id' => $usuario->id,
            'endpoint' => 'https://push.example/expired-endpoint',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aesgcm',
        ]);

        $report = Mockery::mock(\Minishlink\WebPush\MessageSentReport::class);
        $report->shouldReceive('isSuccess')->andReturn(false);
        $report->shouldReceive('getResponse')->andReturn(new \GuzzleHttp\Psr7\Response(410));
        $report->shouldReceive('getReason')->andReturn('Gone');

        $cliente = Mockery::mock(\Minishlink\WebPush\WebPush::class);
        $cliente->shouldReceive('sendOneNotification')->once()->andReturn($report);

        $servicio = new class($cliente) extends EnviarWebPushService
        {
            public function __construct(private readonly \Minishlink\WebPush\WebPush $clienteMock) {}

            public function enviarASuscripciones($suscripciones, array $payload): int
            {
                if ($suscripciones->isEmpty()) {
                    return 0;
                }

                foreach ($suscripciones as $sub) {
                    $report = $this->clienteMock->sendOneNotification(
                        \Minishlink\WebPush\Subscription::create([
                            'endpoint' => $sub->endpoint,
                            'publicKey' => $sub->public_key,
                            'authToken' => $sub->auth_token,
                            'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                        ]),
                        json_encode($payload, JSON_UNESCAPED_UNICODE),
                    );

                    if (! $report->isSuccess()) {
                        $status = $report->getResponse()?->getStatusCode();
                        if (in_array($status, [404, 410], true)) {
                            $sub->delete();
                        }
                    }
                }

                return 0;
            }
        };

        $servicio->enviarASuscripciones(collect([$suscripcion]), [
            'title' => 'Prueba',
            'body' => 'Cuerpo',
            'url' => url('/dashboard'),
        ]);

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $suscripcion->id]);
    }

    public function test_notification_sent_dispara_web_push_para_alerta_turno(): void
    {
        $usuario = User::factory()->create();
        $notificacion = new AlertaTurnoPdvNotification(
            AlertaTurnoPdvNotification::TIPO_ASIGNADO,
            'Turno asignado',
            'Turno V-100 asignado para atención.',
            10,
            'V-100',
            (int) $this->sucursal->id,
            'pdv:notify:turno.asignado:100',
        );

        $webPush = Mockery::mock(EnviarWebPushService::class);
        $webPush->shouldReceive('estaConfigurado')->andReturn(true);
        $webPush->shouldReceive('enviarAUsuario')->once();
        $this->app->instance(EnviarWebPushService::class, $webPush);

        $usuario->notify($notificacion);

        $this->assertCount(1, $usuario->fresh()->notifications);
    }

    /**
     * @return array{0: TurnoPdv, 1: TurnoPdvAtencion, 2: TurnoPdvEvento, 3: User, 4: User}
     */
    private function contextoTurnoAsignado(): array
    {
        $vendedor = $this->usuarioConPermisos([], $this->sucursal);
        $otro = $this->usuarioConPermisos([], $this->otraSucursal);

        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'folio' => 'V-9001',
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'snapshot_nombre_llamado' => 'Cliente Secreto Apellido',
        ]);
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $vendedor->id,
        ]);
        $evento = TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'atencion_id' => $atencion->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_ASIGNADO,
            'estado_anterior' => TurnoPdv::ESTADO_EN_COLA,
            'estado_nuevo' => TurnoPdv::ESTADO_ASIGNADO,
            'ocurrido_at' => now(),
        ]);

        return [$turno, $atencion, $evento, $vendedor, $otro];
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
