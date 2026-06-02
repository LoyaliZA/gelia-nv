<?php

namespace Tests\Feature\Activos;

use App\Models\ActivoConfiguracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FirmaActivoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('activos.ver', 'web');
        Permission::findOrCreate('activos.configurar_tipos', 'web');

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('activos.ver');
        $this->admin->givePermissionTo('activos.configurar_tipos');
    }

    public function test_configuracion_se_crea_con_migracion(): void
    {
        // La migración inserta el registro por defecto
        $this->assertDatabaseHas('activo_configuraciones', [
            'id' => 1,
        ]);

        $terminos = ActivoConfiguracion::obtenerTerminos();
        $this->assertNotEmpty($terminos);
        $this->assertStringContainsString('Recepción e Inventario', $terminos);
    }

    public function test_guardar_configuracion_actualiza_terminos(): void
    {
        $nuevoTexto = 'Nuevas clausulas legales de prueba para responsiva.';

        $response = $this->actingAs($this->admin)->post(route('activos.configuracion.guardar'), [
            'terminos_condiciones' => $nuevoTexto,
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('activo_configuraciones', [
            'id' => 1,
            'terminos_condiciones' => $nuevoTexto,
        ]);

        $this->assertEquals($nuevoTexto, ActivoConfiguracion::obtenerTerminos());
    }

    public function test_guardar_configuracion_requiere_permiso(): void
    {
        $regularUser = User::factory()->create();
        $regularUser->givePermissionTo('activos.ver'); // No tiene activos.configurar_tipos

        $response = $this->actingAs($regularUser)->post(route('activos.configuracion.guardar'), [
            'terminos_condiciones' => 'Intento sin permiso',
        ]);

        $response->assertForbidden();
    }
}
