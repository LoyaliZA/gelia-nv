<?php

namespace App\Services\Contabilidad;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Rap2hpoutre\FastExcel\FastExcel;

class ProcesarListaPreciosContabilidadService
{
    /**
     * @return array<string, array{nombre: string, precio: float}>
     */
    public function ejecutar(UploadedFile $archivo): array
    {
        $nombreTemp = 'temp_conta_'.uniqid().'.'.$archivo->getClientOriginalExtension();
        $rutaCompleta = sys_get_temp_dir().'/'.$nombreTemp;
        $archivo->move(sys_get_temp_dir(), $nombreTemp);

        $diccionario = [];

        try {
            (new FastExcel)->import($rutaCompleta, function ($linea) use (&$diccionario) {
                $sku = trim((string) ($linea['SKU'] ?? ''));
                if ($sku === '') {
                    return;
                }

                $precioBase = $linea['Plataformas'] ?? $linea['Lista3'] ?? $linea['PG'] ?? 0;
                $diccionario[$sku] = [
                    'nombre' => $linea['Descripcion'] ?? 'Producto Desconocido',
                    'precio' => (float) $precioBase,
                ];
            });

            return $diccionario;
        } catch (\Throwable $e) {
            Log::error('GELIA (Conta) - Error procesando Excel: '.$e->getMessage());
            throw new \RuntimeException('No se pudo leer el archivo de lista de precios.');
        } finally {
            if (file_exists($rutaCompleta)) {
                unlink($rutaCompleta);
            }
        }
    }
}
