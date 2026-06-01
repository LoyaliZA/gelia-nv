<?php

namespace Tests\Feature\Rh;

use App\Models\RhColaborador;
use App\Models\RhPrestamoPagoFijo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PrestamoPagoFijoFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConPermisos(): User
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $user = User::first();
        foreach (['rh.prestamos.ver', 'rh.prestamos.crear', 'rh.prestamos.generar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            $user->givePermissionTo($permiso);
        }

        return $user;
    }

    private function crearColaborador(User $user): RhColaborador
    {
        return RhColaborador::create([
            'uuid' => (string) Str::uuid(),
            'folio' => 'COL-000099',
            'nombre' => 'Ana',
            'apellido_paterno' => 'Prueba',
            'salario_base' => 3000,
            'bono_puntualidad' => 0,
            'bono_productividad' => 0,
            'horas_laboradas_oficiales' => 8,
            'salario_diario' => 100,
            'bono_puntualidad_diario' => 0,
            'bono_productividad_diario' => 0,
            'salario_por_hora' => 12.5,
            'salario_por_minuto' => 0.20833333,
            'activo' => true,
            'registrado_por_id' => $user->id,
        ]);
    }

    public function test_crear_prestamo_y_generar_cuota(): void
    {
        $user = $this->usuarioConPermisos();
        $colaborador = $this->crearColaborador($user);

        $response = $this->actingAs($user)->post(route('rh.prestamos.store'), [
            'rh_colaborador_id' => $colaborador->id,
            'concepto' => 'Préstamo equipo',
            'monto_cuota' => 250,
            'modalidad' => RhPrestamoPagoFijo::MODALIDAD_UNICA_VEZ,
            'fecha_inicio' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rh_prestamos_pagos_fijos', [
            'rh_colaborador_id' => $colaborador->id,
            'concepto' => 'Préstamo equipo',
            'estado' => RhPrestamoPagoFijo::ESTADO_ACTIVO,
        ]);

        $generar = $this->actingAs($user)->post(route('rh.prestamos.generar_cuotas'));
        $generar->assertRedirect();

        $prestamo = RhPrestamoPagoFijo::first();
        $this->assertSame(1, $prestamo->pagos_realizados);
        $this->assertDatabaseHas('rh_deducciones', [
            'rh_prestamo_pago_fijo_id' => $prestamo->id,
            'rh_colaborador_id' => $colaborador->id,
        ]);
    }
}
