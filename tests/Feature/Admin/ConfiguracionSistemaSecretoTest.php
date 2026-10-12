<?php

namespace Tests\Feature\Admin;

use App\Models\ConfiguracionSistema;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConfiguracionSistemaSecretoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
    }

    public function test_un_secreto_vacio_conserva_el_valor_y_el_borrado_es_explicito(): void
    {
        $permiso = Permission::findOrCreate('configuracion_sistema.gestionar', 'web');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $usuario = User::factory()->create();
        $usuario->givePermissionTo($permiso);

        $fila = ConfiguracionSistema::query()->create([
            'clave' => 'mail.mailers.smtp.password',
            'valor' => 'clave-guardada',
            'tipo' => 'string',
            'grupo' => 'Mail',
            'descripcion' => 'Contraseña de Aplicación SMTP',
        ]);

        $this->actingAs($usuario)->put(route('admin.configuracion_sistema.update', $fila), [
            'valor' => '',
            'tipo' => 'string',
            'descripcion' => 'Contraseña de Aplicación SMTP',
            'grupo' => 'Mail',
        ])->assertRedirect();

        $this->assertSame('clave-guardada', $fila->fresh()->valor);

        $this->actingAs($usuario)->put(route('admin.configuracion_sistema.update', $fila), [
            'valor' => 'clave-nueva',
            'tipo' => 'string',
            'descripcion' => 'Contraseña de Aplicación SMTP',
            'grupo' => 'Mail',
            'borrar_secreto' => false,
        ])->assertRedirect();

        $this->assertSame('clave-nueva', $fila->fresh()->valor);

        $this->actingAs($usuario)->put(route('admin.configuracion_sistema.update', $fila), [
            'valor' => '',
            'tipo' => 'string',
            'descripcion' => 'Contraseña de Aplicación SMTP',
            'grupo' => 'Mail',
            'borrar_secreto' => true,
        ])->assertRedirect();

        $this->assertSame('', $fila->fresh()->valor);
    }
}
