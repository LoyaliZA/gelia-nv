<?php

namespace App\Services\Almacenes;

use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

class LeerEncabezadosArchivoImportacionService
{
    /**
     * @return array<int, string>
     */
    public function ejecutar(string $storagePath): array
    {
        $rows = (new FastExcel)->import(Storage::path($storagePath));
        foreach ($rows as $row) {
            return array_keys($row);
        }

        return [];
    }
}
