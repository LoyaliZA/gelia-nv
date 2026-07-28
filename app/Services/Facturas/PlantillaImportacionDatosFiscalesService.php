<?php

namespace App\Services\Facturas;

use App\Models\CatalogoRegimenFiscal;
use App\Models\CatalogoUsoCfdi;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlantillaImportacionDatosFiscalesService
{
    public const HEADERS_CLIENTES = [
        'NUMERO CLIENTE',
        'RFC',
        'CODIGO POSTAL',
        'REGIMEN FISCAL',
        'CORREO ELECTRONICO',
        'USO DE FACTURA',
        'NOMBRE (RAZON SOCIAL)',
        'NUMERO TELEFONICO',
    ];

    public const HEADERS_RECEPTORES = [
        'NOMBRE (RAZON SOCIAL)',
        'RFC',
        'CODIGO POSTAL',
        'REGIMEN FISCAL',
        'CORREO ELECTRONICO',
        'USO DE FACTURA',
        'NUMERO TELEFONICO',
    ];

    public function descargarClientes(): StreamedResponse
    {
        $datos = collect([
            array_combine(self::HEADERS_CLIENTES, [
                '12345',
                'XAXX010101000',
                '12345',
                '601',
                'correo@ejemplo.com',
                'G03',
                'EMPRESA EJEMPLO SA DE CV',
                '5512345678',
            ]),
        ]);

        return (new FastExcel(new SheetCollection([
            'Datos' => $datos,
            'Catalogos' => $this->filasCatalogos(),
        ])))->download('plantilla_datos_fiscales_clientes.xlsx');
    }

    public function descargarReceptores(): StreamedResponse
    {
        $datos = collect([
            array_combine(self::HEADERS_RECEPTORES, [
                'TERCERO EJEMPLO SA DE CV',
                'XAXX010101000',
                '12345',
                '601',
                'tercero@ejemplo.com',
                'G03',
                '5512345678',
            ]),
        ]);

        return (new FastExcel(new SheetCollection([
            'Datos' => $datos,
            'Catalogos' => $this->filasCatalogos(),
        ])))->download('plantilla_receptores_fiscales.xlsx');
    }

    /** @return \Illuminate\Support\Collection<int, array{tipo: string, codigo: string, nombre: string}> */
    private function filasCatalogos()
    {
        $filas = collect();

        foreach (CatalogoRegimenFiscal::query()->where('activo', true)->orderBy('codigo')->get() as $row) {
            $filas->push([
                'tipo' => 'regimen_fiscal',
                'codigo' => $row->codigo,
                'nombre' => $row->nombre,
            ]);
        }
        foreach (CatalogoUsoCfdi::query()->where('activo', true)->orderBy('codigo')->get() as $row) {
            $filas->push([
                'tipo' => 'uso_cfdi',
                'codigo' => $row->codigo,
                'nombre' => $row->nombre,
            ]);
        }

        return $filas;
    }
}
