<?php

namespace Tests\Unit\PuntoVenta\Operacion;

use App\Models\PuntoVenta\OperacionGestionAuditoriaPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\RegistrarAuditoriaGestionOperativaPdvService;
use App\Support\PuntoVenta\Operacion\EstadoVendedorOperacionPdv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrarAuditoriaGestionOperativaPdvTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_idempotente_no_duplica_entrada(): void
    {
        $actor = User::factory()->create();
        $vendedor = User::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $servicio = app(RegistrarAuditoriaGestionOperativaPdvService::class);
        $ahora = now();
        $clave = 'pdv:gestion:test:1';

        $primero = $servicio->registrar(
            $actor->id,
            $vendedor->id,
            $sucursal->id,
            'activar',
            EstadoVendedorOperacionPdv::NoActivado,
            EstadoVendedorOperacionPdv::Disponible,
            $ahora,
            $clave,
        );

        $segundo = $servicio->registrar(
            $actor->id,
            $vendedor->id,
            $sucursal->id,
            'activar',
            EstadoVendedorOperacionPdv::NoActivado,
            EstadoVendedorOperacionPdv::Disponible,
            $ahora,
            $clave,
        );

        $this->assertTrue($primero->is($segundo));
        $this->assertSame(1, OperacionGestionAuditoriaPdv::query()->count());
    }

    public function test_clave_idempotente_conflicto_rechaza(): void
    {
        $actor = User::factory()->create();
        $vendedor = User::factory()->create();
        $sucursal = Sucursal::factory()->create();
        $servicio = app(RegistrarAuditoriaGestionOperativaPdvService::class);
        $clave = 'pdv:gestion:test:conflicto';

        $servicio->registrar(
            $actor->id,
            $vendedor->id,
            $sucursal->id,
            'activar',
            EstadoVendedorOperacionPdv::NoActivado,
            EstadoVendedorOperacionPdv::Disponible,
            now(),
            $clave,
        );

        $this->expectException(ValidationException::class);

        $servicio->registrar(
            $actor->id,
            $vendedor->id,
            $sucursal->id,
            'desactivar',
            EstadoVendedorOperacionPdv::Disponible,
            EstadoVendedorOperacionPdv::JornadaCerrada,
            now(),
            $clave,
        );
    }
}
