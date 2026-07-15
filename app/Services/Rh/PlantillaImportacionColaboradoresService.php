<?php

namespace App\Services\Rh;

use App\Models\Area;
use App\Models\CatalogoPuesto;
use App\Models\CatalogoTurno;
use App\Models\Departamento;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlantillaImportacionColaboradoresService
{
    public const HEADERS = [
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'departamento',
        'area',
        'puesto',
        'turno',
        'salario_diario',
        'bono_productividad',
        'bono_puntualidad',
        'horas_laboradas_oficiales',
        'activo',
    ];

    public function descargar(): StreamedResponse
    {
        $departamentos = Departamento::query()
            ->where('activo', true)
            ->with(['areas' => fn ($q) => $q->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        $puestos = CatalogoPuesto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $turnos = CatalogoTurno::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $ejemplo = $this->filaEjemplo($departamentos, $puestos, $turnos);
        $datos = collect([
            array_combine(self::HEADERS, $ejemplo),
        ]);

        $catalogos = $this->filasCatalogos($departamentos, $puestos, $turnos);

        $sheets = new SheetCollection([
            'Datos' => $datos,
            'Catalogos' => $catalogos,
        ]);

        return (new FastExcel($sheets))->download('plantilla_colaboradores.xlsx');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Departamento>  $departamentos
     * @param  \Illuminate\Support\Collection<int, CatalogoPuesto>  $puestos
     * @param  \Illuminate\Support\Collection<int, CatalogoTurno>  $turnos
     * @return list<string|int|float>
     */
    private function filaEjemplo($departamentos, $puestos, $turnos): array
    {
        /** @var Departamento|null $depto */
        $depto = $departamentos->first();
        /** @var Area|null $area */
        $area = $depto?->areas?->first();

        return [
            'Juan',
            'Pérez',
            'García',
            $depto?->nombre ?? '',
            $area?->nombre ?? '',
            $puestos->first()?->nombre ?? '',
            $turnos->first()?->nombre ?? '',
            '350.00',
            '0',
            '0',
            '8',
            '1',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Departamento>  $departamentos
     * @param  \Illuminate\Support\Collection<int, CatalogoPuesto>  $puestos
     * @param  \Illuminate\Support\Collection<int, CatalogoTurno>  $turnos
     * @return \Illuminate\Support\Collection<int, array{tipo: string, nombre: string, departamento: string}>
     */
    private function filasCatalogos($departamentos, $puestos, $turnos)
    {
        $filas = collect();

        foreach ($departamentos as $depto) {
            $filas->push([
                'tipo' => 'departamento',
                'nombre' => $depto->nombre,
                'departamento' => '',
            ]);

            foreach ($depto->areas as $area) {
                $filas->push([
                    'tipo' => 'area',
                    'nombre' => $area->nombre,
                    'departamento' => $depto->nombre,
                ]);
            }
        }

        foreach ($puestos as $puesto) {
            $filas->push([
                'tipo' => 'puesto',
                'nombre' => $puesto->nombre,
                'departamento' => '',
            ]);
        }

        foreach ($turnos as $turno) {
            $filas->push([
                'tipo' => 'turno',
                'nombre' => $turno->nombre,
                'departamento' => '',
            ]);
        }

        if ($filas->isEmpty()) {
            $filas->push([
                'tipo' => '(sin registros activos)',
                'nombre' => '',
                'departamento' => '',
            ]);
        }

        return $filas;
    }
}
