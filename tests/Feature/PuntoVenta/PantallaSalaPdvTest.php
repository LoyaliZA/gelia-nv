<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Broadcast\Payloads\PayloadTurnoPdvBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PantallaSalaPdvTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Sala']);
    }

    public function test_pantalla_publica_renderiza_sin_autenticacion(): void
    {
        $this->crearLlamadoActivo('V-0200', 'Cliente Sala', 'Ana Vendedora');

        $this->get(route('sala_turnos.publica.show', ['sucursal' => $this->sucursal->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Pantallas/Sala', false)
                ->where('sucursal_id', $this->sucursal->id)
                ->has('estado_inicial.llamados', 1)
                ->where('estado_inicial.llamados.0.folio', 'V-0200')
                ->where('estado_inicial.llamados.0.snapshot_nombre_llamado', 'Cliente Sala')
                ->where('estado_inicial.llamados.0.atencion_primer_nombre', 'Ana')
                ->where('estado_inicial.turno_actual.folio', 'V-0200')
                ->where('estado_inicial.turno_actual.atencion_nombre', 'Ana Vendedora')
                ->has('estado_inicial.proximos')
                ->has('estado_inicial.anteriores')
                ->has('estado_inicial.publicidad')
                ->has('estado_inicial.tema.color_primario'));
    }

    public function test_estado_solo_lectura_expone_llamados_sin_datos_sensibles(): void
    {
        $turno = $this->crearLlamadoActivo('V-0300', 'Visitante Uno', 'Luis Vendedor', [
            'prioridad_vip' => true,
            'prioridad_adulto_mayor' => true,
            'prioridad_discapacidad' => true,
            'prioridad_diamante' => true,
        ]);

        $response = $this->getJson(route('sala_turnos.publica.estado', ['sucursal' => $this->sucursal->id]))
            ->assertOk();

        $llamado = $response->json('llamados.0');
        $this->assertSame('V-0300', $llamado['folio']);
        $this->assertSame('Visitante Uno', $llamado['snapshot_nombre_llamado']);
        $this->assertSame('Luis', $llamado['atencion_primer_nombre']);
        $this->assertSame('Luis Vendedor', $llamado['atencion_nombre']);
        $this->assertTrue($llamado['prioridad_diamante']);
        $this->assertArrayNotHasKey('prioridad_vip', $llamado);
        $this->assertArrayNotHasKey('prioridad_adulto_mayor', $llamado);
        $this->assertArrayNotHasKey('prioridad_discapacidad', $llamado);
        $this->assertArrayNotHasKey('cliente_id', $llamado);
        $this->assertArrayNotHasKey('telefono', $llamado);
        $this->assertArrayNotHasKey('permisos', $llamado);
        $this->assertSame($turno->id, $llamado['turno_id']);
    }

    public function test_payload_publico_broadcast_incluye_nombre_llamado_y_excluye_vip(): void
    {
        $ventas = User::factory()->create(['name' => 'Carmen Vendedora']);
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'folio' => 'V-0400',
            'snapshot_nombre_llamado' => 'Rosa Hernández',
            'prioridad_vip' => true,
            'prioridad_diamante' => false,
        ]);
        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $ventas->id,
        ]);
        $atencion->load('user');

        $payload = PayloadTurnoPdvBroadcast::publico($turno, $atencion);

        $this->assertSame('Rosa Hernández', $payload['snapshot_nombre_llamado']);
        $this->assertSame('Carmen', $payload['atencion_primer_nombre']);
        $this->assertSame('Carmen Vendedora', $payload['atencion_nombre']);
        $this->assertArrayNotHasKey('prioridad_vip', $payload);
    }

    public function test_pantalla_publica_responde_404_si_modulo_inactivo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '0'],
        );

        $this->get(route('sala_turnos.publica.show', ['sucursal' => $this->sucursal->id]))
            ->assertNotFound();
    }

    public function test_pantalla_publica_responde_404_si_sucursal_inactiva(): void
    {
        $inactiva = Sucursal::factory()->create(['activo' => false]);

        $this->get(route('sala_turnos.publica.show', ['sucursal' => $inactiva->id]))
            ->assertNotFound();
    }

    public function test_estado_expone_proximos_en_orden_de_cola_sin_vendedor(): void
    {
        $this->crearLlamadoActivo('V-0100', 'Cliente Actual', 'Ana Vendedora');

        TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'folio' => 'V-0101',
            'snapshot_nombre_llamado' => 'Cliente Normal',
            'alta_at' => now()->subMinutes(10),
            'atencion_actual_id' => null,
        ]);
        TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'folio' => 'V-0102',
            'snapshot_nombre_llamado' => 'Cliente Prioritario',
            'prioridad_adulto_mayor' => true,
            'alta_at' => now()->subMinutes(1),
            'atencion_actual_id' => null,
        ]);

        $response = $this->getJson(route('sala_turnos.publica.estado', ['sucursal' => $this->sucursal->id]))
            ->assertOk();

        $this->assertSame('V-0100', $response->json('turno_actual.folio'));
        $this->assertSame(['V-0102', 'V-0101'], array_column($response->json('proximos'), 'folio'));
        $this->assertArrayNotHasKey('atencion_nombre', $response->json('proximos.0'));
        $this->assertArrayNotHasKey('atendido_por', $response->json('proximos.0'));
        $this->assertSame('Cliente Prioritario', $response->json('proximos.0.snapshot_nombre_llamado'));
    }

    public function test_estado_expone_anteriores_atendidos_con_hora_y_nombre(): void
    {
        $ventas = User::factory()->create(['name' => 'Luis Martínez']);
        $turno = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_EN_REATENCION,
            'folio' => 'B-013',
            'snapshot_nombre_llamado' => 'Ana Gabriela Ruiz Núñez',
            'baja_motivo' => null,
        ]);
        TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $ventas->id,
            'inicio_at' => now()->subMinutes(20),
            'fin_at' => now()->setTime(10, 21, 0),
            'es_transferencia' => false,
        ]);

        $baja = TurnoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_CERRADO,
            'folio' => 'B-099',
            'baja_motivo' => 'abandono',
        ]);
        TurnoPdvAtencion::factory()->create([
            'turno_id' => $baja->id,
            'user_id' => $ventas->id,
            'fin_at' => now()->subMinutes(5),
        ]);

        $response = $this->getJson(route('sala_turnos.publica.estado', ['sucursal' => $this->sucursal->id]))
            ->assertOk();

        $this->assertSame('B-013', $response->json('anteriores.0.folio'));
        $this->assertSame('Luis Martínez', $response->json('anteriores.0.atendido_por'));
        $this->assertSame('10:21', $response->json('anteriores.0.hora'));
        $folios = array_column($response->json('anteriores'), 'folio');
        $this->assertNotContains('B-099', $folios);
        $this->assertArrayNotHasKey('prioridad_vip', $response->json('anteriores.0'));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function crearLlamadoActivo(
        string $folio,
        string $nombreLlamado,
        string $nombreVendedor,
        array $extra = [],
    ): TurnoPdv {
        $ventas = User::factory()->create(['name' => $nombreVendedor]);
        $turno = TurnoPdv::factory()->create(array_merge([
            'sucursal_id' => $this->sucursal->id,
            'estado' => TurnoPdv::ESTADO_ASIGNADO,
            'folio' => $folio,
            'snapshot_nombre_llamado' => $nombreLlamado,
        ], $extra));

        $atencion = TurnoPdvAtencion::factory()->create([
            'turno_id' => $turno->id,
            'user_id' => $ventas->id,
            'inicio_at' => now(),
            'fin_at' => null,
        ]);

        $turno->update(['atencion_actual_id' => $atencion->id]);

        return $turno->fresh();
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1'],
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }
}
