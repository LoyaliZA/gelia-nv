<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\ResguardoPdvMarcadoRezagado;
use App\Events\PuntoVenta\ResguardoPdvMarcadoVencido;
use App\Events\PuntoVenta\ResguardoPdvProximoAVencer;
use App\Listeners\PuntoVenta\NotificarResguardoPdvMarcadoRezagado;
use App\Listeners\PuntoVenta\NotificarResguardoPdvMarcadoVencido;
use App\Listeners\PuntoVenta\NotificarResguardoPdvProximoAVencer;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Notifications\PuntoVenta\AlertaResguardoPdvNotification;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\EvaluarVencimientosResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\NotificarResguardoPdvService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AlertasEscalamientoResguardoPdvTest extends TestCase
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

        Carbon::setTestNow(Carbon::parse('2026-08-24 10:00:00', 'America/Mexico_City'));

        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal A']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal B']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_vencido_notifica_ver_vencidos_y_escalamiento_a_reponer(): void
    {
        $resguardo = $this->crearResguardo();
        $operador = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS],
            $this->sucursal
        );
        $supervisor = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO],
            $this->sucursal
        );
        $sinPermiso = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR],
            $this->sucursal
        );

        $evento = $this->crearEventoMarcado($resguardo, ResguardoPdvEvento::TIPO_MARCADO_VENCIDO, 'resguardo:'.$resguardo->id.':marcado_vencido');

        app(NotificarResguardoPdvMarcadoVencido::class)->handle(
            new ResguardoPdvMarcadoVencido($resguardo, $evento, (int) $this->sucursal->id)
        );

        $this->assertCount(1, $operador->fresh()->notifications);
        $this->assertSame(
            AlertaResguardoPdvNotification::TIPO_VENCIDO,
            $operador->notifications->first()->data['tipo']
        );
        $this->assertCount(1, $supervisor->fresh()->notifications);
        $this->assertSame(
            AlertaResguardoPdvNotification::TIPO_ESCALAMIENTO,
            $supervisor->notifications->first()->data['tipo']
        );
        $this->assertSame('vencido', $supervisor->notifications->first()->data['escalamiento_contexto']);
        $this->assertCount(0, $sinPermiso->fresh()->notifications);
    }

    public function test_rezagado_escala_solo_a_receptores_de_la_sucursal(): void
    {
        $resguardo = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'recepcion_fisica_at' => null,
            'salida_cedis_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);
        $receptor = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR],
            $this->sucursal
        );
        $otraSucursal = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR],
            $this->otraSucursal
        );

        $evento = $this->crearEventoMarcado($resguardo, ResguardoPdvEvento::TIPO_MARCADO_REZAGADO, 'resguardo:'.$resguardo->id.':marcado_rezagado');

        app(NotificarResguardoPdvMarcadoRezagado::class)->handle(
            new ResguardoPdvMarcadoRezagado($resguardo, $evento, (int) $this->sucursal->id)
        );

        $this->assertCount(1, $receptor->fresh()->notifications);
        $this->assertSame(
            AlertaResguardoPdvNotification::TIPO_ESCALAMIENTO,
            $receptor->notifications->first()->data['tipo']
        );
        $this->assertSame('rezagado', $receptor->notifications->first()->data['escalamiento_contexto']);
        $this->assertCount(0, $otraSucursal->fresh()->notifications);
    }

    public function test_proximo_a_vencer_notifica_operadores_autorizados(): void
    {
        $resguardo = $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-08-06 10:00:00', 'America/Mexico_City'),
        ]);
        $operador = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            $this->sucursal
        );

        app(NotificarResguardoPdvProximoAVencer::class)->handle(
            new ResguardoPdvProximoAVencer(
                $resguardo,
                (int) $this->sucursal->id,
                'resguardo:'.$resguardo->id.':proximo_a_vencer',
                ['clasificaciones' => ['proximo_a_vencer' => true]]
            )
        );

        $this->assertCount(1, $operador->fresh()->notifications);
        $this->assertSame(
            AlertaResguardoPdvNotification::TIPO_PROXIMO_A_VENCER,
            $operador->notifications->first()->data['tipo']
        );
    }

    public function test_job_repetido_no_duplica_alertas(): void
    {
        $this->usuarioConPermisos([PuntoVentaModulo::PERMISO_RESGUARDOS_VER], $this->sucursal);
        $this->usuarioConPermisos([PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS], $this->sucursal);
        $this->usuarioConPermisos([PuntoVentaModulo::PERMISO_RESGUARDOS_REPONER_VENCIDO], $this->sucursal);
        $this->usuarioConPermisos([PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR], $this->sucursal);

        $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);
        $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-08-06 10:00:00', 'America/Mexico_City'),
        ]);
        $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'recepcion_fisica_at' => null,
            'salida_cedis_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        $service = app(EvaluarVencimientosResguardoPdvService::class);
        $primera = $service->ejecutar();
        $segunda = $service->ejecutar();

        $this->assertSame(1, $primera['vencidos']);
        $this->assertSame(1, $primera['rezagados']);
        $this->assertSame(1, $primera['proximos']);
        $this->assertSame(0, $segunda['vencidos']);
        $this->assertSame(0, $segunda['rezagados']);
        $this->assertSame(0, $segunda['proximos']);

        $totalNotificaciones = \Illuminate\Notifications\DatabaseNotification::query()->count();
        $this->assertSame(6, $totalNotificaciones);
    }

    public function test_entregado_y_devuelto_no_reciben_alertas_del_job(): void
    {
        $operador = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS],
            $this->sucursal
        );

        $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'recepcion_fisica_at' => Carbon::parse('2026-07-01 10:00:00'),
            'entrega_completada_at' => Carbon::parse('2026-08-01 10:00:00'),
        ]);
        $this->crearResguardo([
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'recepcion_fisica_at' => Carbon::parse('2026-07-01 10:00:00'),
            'devolucion_confirmada_at' => Carbon::parse('2026-08-10 10:00:00'),
        ]);

        app(EvaluarVencimientosResguardoPdvService::class)->ejecutar();

        $this->assertCount(0, $operador->fresh()->notifications);
    }

    public function test_payload_seguro_sin_datos_sensibles(): void
    {
        $resguardo = $this->crearResguardo([
            'snapshot_folio' => 'REM-ALERTA-1',
            'snapshot_cliente_nombre' => 'Cliente Confidencial Completo',
        ]);
        $usuario = $this->usuarioConPermisos(
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            $this->sucursal
        );

        app(NotificarResguardoPdvService::class)->proximoAVencer(
            $resguardo,
            (int) $this->sucursal->id,
            'resguardo:'.$resguardo->id.':proximo_a_vencer'
        );

        $data = $usuario->fresh()->notifications->first()->data;

        $this->assertSame('/punto-venta/resguardos/'.$resguardo->id, $data['url']);
        $this->assertSame('REM-ALERTA-1', $data['folio']);
        $this->assertArrayNotHasKey('snapshot_cliente_nombre', $data);
        $this->assertStringNotContainsString('Cliente Confidencial', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function test_fallo_de_notificacion_no_propaga_excepcion(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('canal caído'));

        $resguardo = $this->crearResguardo();
        $this->usuarioConPermisos([PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS], $this->sucursal);

        app(NotificarResguardoPdvService::class)->vencido(
            $resguardo,
            (int) $this->sucursal->id,
            'resguardo:'.$resguardo->id.':marcado_vencido'
        );

        $this->assertTrue(true);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearResguardo(array $atributos = []): ResguardoPdv
    {
        return ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $this->sucursal->id,
            'salida_cedis_at' => now(),
            'snapshot_folio' => 'BMA-TEST-1',
        ], $atributos));
    }

    private function crearEventoMarcado(
        ResguardoPdv $resguardo,
        string $tipo,
        string $idempotencyKey,
    ): ResguardoPdvEvento {
        return ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => $tipo,
            'estado_anterior' => $resguardo->estado,
            'estado_nuevo' => $resguardo->estado,
            'actor_id' => null,
            'ocurrido_at' => now(),
            'idempotency_key' => $idempotencyKey,
        ]);
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
