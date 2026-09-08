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
                ->where('estado_inicial.llamados.0.atencion_primer_nombre', 'Ana'));
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
