<?php

namespace Tests\Feature\PuntoVenta;

use App\Events\PuntoVenta\JornadaAbierta;
use App\Events\PuntoVenta\RecepcionFisicaPdvCompletada;
use App\Events\PuntoVenta\TurnoAsignado;
use App\Events\PuntoVenta\TurnoCreado;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Broadcast\AutorizaCanalesPdv;
use App\Support\PuntoVenta\Broadcast\CanalesPdv;
use App\Support\PuntoVenta\Broadcast\PdvRealtimeEnvelope;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\PuntoVenta\CapturadorBroadcastPdv;
use Tests\TestCase;

class EventosCanalesRealtimePdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    private CapturadorBroadcastPdv $capturador;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();
        $this->registrarCapturadorBroadcast();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Realtime']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Remota']);
    }

    public function test_canal_sucursal_autoriza_usuario_con_acceso_y_permiso(): void
    {
        $usuario = $this->usuarioOperador($this->sucursal, [PuntoVentaModulo::PERMISO_TURNOS_VER]);
        $sinAcceso = $this->usuarioOperador($this->otraSucursal, [PuntoVentaModulo::PERMISO_TURNOS_VER]);
        $sinPermiso = User::factory()->create();
        $sinPermiso->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $autoriza = app(AutorizaCanalesPdv::class);

        $this->assertTrue($autoriza->puedeSucursal($usuario, (int) $this->sucursal->id));
        $this->assertFalse($autoriza->puedeSucursal($sinAcceso, (int) $this->sucursal->id));
        $this->assertFalse($autoriza->puedeSucursal($sinPermiso, (int) $this->sucursal->id));
    }

    public function test_canal_usuario_solo_autoriza_al_titular_con_acceso_pdv(): void
    {
        $titular = $this->usuarioOperador($this->sucursal, [PuntoVentaModulo::PERMISO_TURNOS_VER]);
        $otro = $this->usuarioOperador($this->sucursal, [PuntoVentaModulo::PERMISO_TURNOS_VER]);

        $autoriza = app(AutorizaCanalesPdv::class);

        $this->assertTrue($autoriza->puedeUsuario($titular, (int) $titular->id));
        $this->assertFalse($autoriza->puedeUsuario($otro, (int) $titular->id));
    }

    public function test_turno_asignado_emite_payload_seguro_en_canales_previstos(): void
    {
        $ventas = User::factory()->create(['name' => 'Ana Vendedora']);
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'folio' => 'V-0100',
            'snapshot_nombre_llamado' => 'Cliente Secreto',
            'prioridad_vip' => true,
            'version' => 3,
        ]);
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $ventas->id,
        ]);
        $evento = TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'atencion_id' => $atencion->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_ASIGNADO,
            'estado_nuevo' => TurnoPdv::ESTADO_ASIGNADO,
            'ocurrido_at' => now(),
        ]);
        $eventId = 'turnos:'.TurnoPdvEvento::TIPO_ASIGNADO.':'.$evento->id;

        TurnoAsignado::dispatch($turno, $atencion, $evento, (int) $this->sucursal->id);

        $sucursal = $this->emisionPorCanal(CanalesPdv::sucursal((int) $this->sucursal->id)->name);
        $this->assertNotNull($sucursal);
        $this->assertSame($eventId, $sucursal['payload']['event_id']);
        $this->assertSame('sucursal', $sucursal['payload']['audiencia']);
        $this->assertSame(PdvRealtimeEnvelope::PAYLOAD_VERSION, $sucursal['payload']['payload_version']);
        $this->assertSame('Cliente Secreto', $sucursal['payload']['datos']['snapshot_nombre_llamado']);
        $this->assertTrue($sucursal['payload']['datos']['prioridad_vip']);

        $usuario = $this->emisionPorCanal(CanalesPdv::usuario((int) $ventas->id)->name);
        $this->assertNotNull($usuario);
        $this->assertSame('usuario', $usuario['payload']['audiencia']);
        $this->assertSame($ventas->id, $usuario['payload']['datos']['atencion']['user_id']);

        $publico = $this->emisionPorCanal(CanalesPdv::turnosPublico((int) $this->sucursal->id)->name);
        $this->assertNotNull($publico);
        $this->assertSame('publico', $publico['payload']['audiencia']);
        $this->assertSame('V-0100', $publico['payload']['datos']['folio']);
        $this->assertSame('Cliente Secreto', $publico['payload']['datos']['snapshot_nombre_llamado']);
        $this->assertArrayNotHasKey('prioridad_vip', $publico['payload']['datos']);
        $this->assertArrayNotHasKey('prioridad_adulto_mayor', $publico['payload']['datos']);
        $this->assertArrayNotHasKey('prioridad_discapacidad', $publico['payload']['datos']);
        $this->assertSame('Ana', $publico['payload']['datos']['atencion_primer_nombre']);
    }

    public function test_turno_alta_solo_emite_canal_sucursal(): void
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'version' => 1,
        ]);
        $evento = TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_ALTA,
            'estado_nuevo' => TurnoPdv::ESTADO_EN_COLA,
            'ocurrido_at' => now(),
        ]);

        TurnoCreado::dispatch($turno, $evento, (int) $this->sucursal->id);

        $this->assertCount(1, $this->capturador->emisiones);
        $this->assertSame(
            CanalesPdv::sucursal((int) $this->sucursal->id)->name,
            $this->capturador->emisiones[0]['channels'][0],
        );
        $this->assertSame(TurnoPdvEvento::TIPO_ALTA, $this->capturador->emisiones[0]['payload']['tipo']);
    }

    public function test_resguardo_emite_solo_canal_sucursal_con_payload_reducido(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'snapshot_folio' => 'R-500',
            'snapshot_cliente_nombre' => 'Cliente Confidencial',
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'version' => 2,
        ]);
        $evento = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'ocurrido_at' => now(),
        ]);
        $eventId = 'resguardos:'.ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA.':'.$evento->id;

        RecepcionFisicaPdvCompletada::dispatch($resguardo, $evento, (int) $this->sucursal->id);

        $this->assertCount(1, $this->capturador->emisiones);
        $payload = $this->capturador->emisiones[0]['payload'];
        $this->assertSame('resguardos', $payload['dominio']);
        $this->assertSame($eventId, $payload['event_id']);
        $this->assertSame('R-500', $payload['datos']['folio']);
        $this->assertArrayNotHasKey('snapshot_cliente_nombre', $payload['datos']);
        $this->assertArrayNotHasKey('cliente_id', $payload['datos']);
    }

    public function test_operacion_jornada_abierta_emite_canal_sucursal(): void
    {
        $ventas = User::factory()->create();
        $jornada = JornadaPdv::factory()->create([
            'user_id' => $ventas->id,
            'sucursal_id' => $this->sucursal->id,
            'estado' => EstadoJornadaPdv::Abierta,
            'version' => 1,
        ]);
        $intervalo = IntervaloOperativoPdv::factory()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $ventas->id,
            'sucursal_id' => $this->sucursal->id,
            'tipo' => TipoIntervaloOperativoPdv::Disponible,
        ]);

        JornadaAbierta::dispatch($jornada, $intervalo, (int) $this->sucursal->id, (int) $ventas->id);

        $payload = $this->capturador->emisiones[0]['payload'] ?? [];
        $this->assertSame('jornada.abierta', $payload['tipo'] ?? null);
        $this->assertSame('operacion', $payload['dominio'] ?? null);
        $this->assertNotNull($payload['datos']['jornada']['jornada_id'] ?? null);
    }

    public function test_event_id_permite_deduplicar_reintentos(): void
    {
        $turno = TurnoPdv::factory()->create(['sucursal_id' => $this->sucursal->id]);
        $atencion = TurnoPdvAtencion::factory()->create(['turno_id' => $turno->id]);
        $evento = TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'atencion_id' => $atencion->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_ASIGNADO,
            'ocurrido_at' => now(),
        ]);
        $eventId = 'turnos:'.TurnoPdvEvento::TIPO_ASIGNADO.':'.$evento->id;

        TurnoAsignado::dispatch($turno, $atencion, $evento, (int) $this->sucursal->id);
        TurnoAsignado::dispatch($turno, $atencion, $evento, (int) $this->sucursal->id);

        $ids = collect($this->capturador->emisiones)
            ->pluck('payload.event_id')
            ->unique()
            ->values();

        $this->assertSame([$eventId], $ids->all());
        $this->assertCount(6, $this->capturador->emisiones);
    }

    public function test_broadcast_no_sale_si_la_transaccion_revierte(): void
    {
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
        ]);
        $evento = TurnoPdvEvento::query()->create([
            'turno_id' => $turno->id,
            'tipo_evento' => TurnoPdvEvento::TIPO_ALTA,
            'estado_nuevo' => TurnoPdv::ESTADO_EN_COLA,
            'ocurrido_at' => now(),
        ]);

        DB::beginTransaction();

        try {
            TurnoCreado::dispatch($turno, $evento, (int) $this->sucursal->id);
            DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->assertSame([], $this->capturador->emisiones);
    }

    private function registrarCapturadorBroadcast(): void
    {
        $this->capturador = new CapturadorBroadcastPdv;
        $capturador = $this->capturador;
        Broadcast::extend('captura_pdv', fn () => $capturador);
        config([
            'broadcasting.default' => 'captura_pdv',
            'broadcasting.connections.captura_pdv' => ['driver' => 'captura_pdv'],
        ]);
        Broadcast::purge('captura_pdv');
    }

    /**
     * @return array{channels: array<int, string>, event: string, payload: array<string, mixed>}|null
     */
    private function emisionPorCanal(string $nombreCanal): ?array
    {
        foreach ($this->capturador->emisiones as $emision) {
            if (in_array($nombreCanal, $emision['channels'], true)) {
                return $emision;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $permisos
     */
    private function usuarioOperador(Sucursal $sucursal, array $permisos): User
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(array_merge(
            [PuntoVentaModulo::PERMISO_ACCEDER],
            $permisos,
        ));
        $usuario->concederAccesoSucursal($sucursal, esPrincipal: true);
        app(AlcancePdv::class)->establecerSucursalActiva($usuario, $sucursal->id);

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
    }
}
