<?php

namespace Tests\Feature\Rh;

use App\Models\CatalogoBono;
use App\Models\CatalogoPuesto;
use App\Services\Rh\ValidarBonosColaboradorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ValidarBonosColaboradorFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_bono_no_elegible_para_puesto(): void
    {
        $bonoCaja = CatalogoBono::create(['nombre' => 'Bono Caja', 'codigo' => 'bono_caja', 'activo' => true]);
        $bonoDesempaque = CatalogoBono::create(['nombre' => 'Bono Desempaque', 'codigo' => 'bono_desempaque', 'activo' => true]);

        $puesto = CatalogoPuesto::create(['nombre' => 'Cajero', 'activo' => true]);
        $puesto->bonos()->sync([$bonoDesempaque->id]);

        $service = app(ValidarBonosColaboradorService::class);

        $this->expectException(ValidationException::class);

        $service->ejecutar($puesto->id, [
            ['catalogo_bono_id' => $bonoCaja->id, 'monto' => 500],
        ]);
    }

    public function test_acepta_bono_elegible_para_puesto(): void
    {
        $bono = CatalogoBono::create(['nombre' => 'Bono Caja', 'codigo' => 'bono_caja', 'activo' => true]);
        $puesto = CatalogoPuesto::create(['nombre' => 'Cajero', 'activo' => true]);
        $puesto->bonos()->sync([$bono->id]);

        $service = app(ValidarBonosColaboradorService::class);
        $service->ejecutar($puesto->id, [
            ['catalogo_bono_id' => $bono->id, 'monto' => 500],
        ]);

        $this->assertTrue(true);
    }
}
