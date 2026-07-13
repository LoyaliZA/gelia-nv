<?php

namespace Tests\Feature\Rh;

use App\Models\Area;
use App\Models\CatalogoPuesto;
use App\Models\CatalogoTurno;
use App\Models\Departamento;
use App\Models\RhColaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Rap2hpoutre\FastExcel\FastExcel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ImportarColaboradoresFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConPermisos(array $extras = []): User
    {
        foreach (array_merge(['rh.ver', 'rh.colaboradores.crear'], $extras) as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_merge(['rh.ver', 'rh.colaboradores.crear'], $extras));

        return $user;
    }

    private function catalogosBase(): array
    {
        $depto = Departamento::create(['nombre' => 'Ventas', 'activo' => true]);
        $area = Area::create(['nombre' => 'Mostrador', 'departamento_id' => $depto->id]);
        $puesto = CatalogoPuesto::create(['nombre' => 'Vendedor', 'activo' => true]);
        $turno = CatalogoTurno::create([
            'nombre' => 'Matutino',
            'activo' => true,
            'matriz_horario' => [],
        ]);

        return compact('depto', 'area', 'puesto', 'turno');
    }

    private function crearArchivoImportacion(array $filas): UploadedFile
    {
        $ruta = sys_get_temp_dir() . '/import_colaboradores_test_' . uniqid() . '.xlsx';
        (new FastExcel(collect($filas)))->export($ruta);

        return new UploadedFile(
            $ruta,
            'colaboradores.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_descarga_plantilla_incluye_catalogos_vivos(): void
    {
        $user = $this->usuarioConPermisos();
        $this->catalogosBase();

        $response = $this->actingAs($user)->get(route('rh.colaboradores.plantilla_importacion'));

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('content-disposition') ?? ''
        );
        $this->assertStringContainsString(
            'plantilla_colaboradores.xlsx',
            $response->headers->get('content-disposition') ?? ''
        );

        $contenido = $response->streamedContent();
        $tmp = tempnam(sys_get_temp_dir(), 'plantilla_col_') . '.xlsx';
        file_put_contents($tmp, $contenido);

        $hojas = (new FastExcel)->withSheetsNames()->importSheets($tmp);
        @unlink($tmp);

        $asArray = $hojas->toArray();
        $this->assertArrayHasKey('Datos', $asArray);
        $this->assertArrayHasKey('Catalogos', $asArray);

        $nombresCatalogo = collect($asArray['Catalogos'])->pluck('nombre')->filter()->values()->all();
        $this->assertContains('Ventas', $nombresCatalogo);
        $this->assertContains('Mostrador', $nombresCatalogo);
        $this->assertContains('Vendedor', $nombresCatalogo);
        $this->assertContains('Matutino', $nombresCatalogo);
    }

    public function test_importa_colaborador_valido(): void
    {
        $user = $this->usuarioConPermisos();
        $catalogos = $this->catalogosBase();

        $archivo = $this->crearArchivoImportacion([
            [
                'nombre' => 'Ana',
                'apellido_paterno' => 'López',
                'apellido_materno' => 'Ruiz',
                'departamento' => $catalogos['depto']->nombre,
                'area' => $catalogos['area']->nombre,
                'puesto' => $catalogos['puesto']->nombre,
                'turno' => $catalogos['turno']->nombre,
                'salario_diario' => 100,
                'bono_productividad' => 10,
                'bono_puntualidad' => 5,
                'horas_laboradas_oficiales' => 8,
                'activo' => 1,
            ],
        ]);

        $response = $this->actingAs($user)->post(route('rh.colaboradores.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect(route('rh.colaboradores.index'));
        $response->assertSessionHas('reporte_importacion_colaboradores');

        $stats = session('reporte_importacion_colaboradores');
        $this->assertSame(1, $stats['importados']);
        $this->assertSame(0, $stats['omitidos']);

        $colaborador = RhColaborador::first();
        $this->assertNotNull($colaborador);
        $this->assertSame('Ana', $colaborador->nombre);
        $this->assertSame($catalogos['depto']->id, $colaborador->departamento_id);
        $this->assertSame($catalogos['puesto']->id, $colaborador->catalogo_puesto_id);
        $this->assertSame($catalogos['turno']->id, $colaborador->catalogo_turno_id);
        $this->assertNotEmpty($colaborador->folio);
        $this->assertEqualsWithDelta(3000, (float) $colaborador->salario_base, 0.01);
    }

    public function test_omite_fila_con_departamento_inexistente(): void
    {
        $user = $this->usuarioConPermisos();
        $catalogos = $this->catalogosBase();

        $archivo = $this->crearArchivoImportacion([
            [
                'nombre' => 'Pedro',
                'apellido_paterno' => 'Nadie',
                'apellido_materno' => '',
                'departamento' => 'No Existe',
                'area' => '',
                'puesto' => $catalogos['puesto']->nombre,
                'turno' => $catalogos['turno']->nombre,
                'salario_diario' => 100,
                'bono_productividad' => 0,
                'bono_puntualidad' => 0,
                'horas_laboradas_oficiales' => 8,
                'activo' => 1,
            ],
        ]);

        $response = $this->actingAs($user)->post(route('rh.colaboradores.importar'), [
            'archivo' => $archivo,
        ]);

        $response->assertRedirect(route('rh.colaboradores.index'));
        $stats = session('reporte_importacion_colaboradores');
        $this->assertSame(0, $stats['importados']);
        $this->assertSame(1, $stats['omitidos']);
        $this->assertNotEmpty($stats['errores']);
        $this->assertSame(0, RhColaborador::count());
    }

    public function test_sin_permiso_crear_recibe_403(): void
    {
        Permission::findOrCreate('rh.ver', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('rh.ver');

        $response = $this->actingAs($user)->get(route('rh.colaboradores.plantilla_importacion'));
        $response->assertForbidden();

        $archivo = $this->crearArchivoImportacion([
            [
                'nombre' => 'X',
                'departamento' => 'Y',
                'puesto' => 'Z',
                'turno' => 'T',
                'salario_diario' => 1,
                'horas_laboradas_oficiales' => 8,
            ],
        ]);

        $responseImport = $this->actingAs($user)->post(route('rh.colaboradores.importar'), [
            'archivo' => $archivo,
        ]);
        $responseImport->assertForbidden();
    }
}
