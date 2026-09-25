<?php

namespace Tests\Unit\Traspasos;

use App\Models\Almacen;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Traspasos\AlmacenesOrigenTraspasoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AlmacenesOrigenTraspasoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloquea_almacen_sin_pivot(): void
    {
        $sucursal = Sucursal::factory()->create();
        $almacen = Almacen::create([
            'codigo' => 'CEDIS-T',
            'nombre' => 'CEDIS Test',
            'activo' => true,
            'visible_en_traspasos' => true,
        ]);

        $usuario = User::factory()->create();
        $usuario->concederAccesoSucursal($sucursal, esPrincipal: true);

        $svc = app(AlmacenesOrigenTraspasoService::class);

        $this->expectException(ValidationException::class);
        $svc->assertAlmacenPermitido($usuario, $almacen->id);
    }

    public function test_permite_almacen_en_pivot(): void
    {
        $sucursal = Sucursal::factory()->create();
        $almacen = Almacen::create([
            'codigo' => 'CAR-T',
            'nombre' => 'Carmen Test',
            'activo' => true,
            'visible_en_traspasos' => true,
        ]);
        $sucursal->almacenesOrigenTraspaso()->sync([$almacen->id]);

        $usuario = User::factory()->create();
        $usuario->concederAccesoSucursal($sucursal, esPrincipal: true);

        $resultado = app(AlmacenesOrigenTraspasoService::class)->assertAlmacenPermitido($usuario, $almacen->id);

        $this->assertSame($almacen->id, $resultado->id);
    }
}
