<?php

namespace App\Services\PuntoVenta\Publicidad;

use App\Events\PuntoVenta\PublicidadPdvActualizada;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Log;

class DepurarPublicidadPdvService
{
    public function __construct(
        private readonly EliminarPublicidadPdvService $eliminar,
    ) {}

    /**
     * @return array{piezas: int, archivos: int, ausentes: int}
     */
    public function ejecutar(): array
    {
        $piezas = 0;
        $archivos = 0;
        $ausentes = 0;
        $sucursales = [];
        $huboGlobal = false;

        do {
            $lote = PdvPantallaPublicidad::query()
                ->where('eliminar_automaticamente', true)
                ->whereNotNull('eliminar_programado_at')
                ->where('eliminar_programado_at', '<=', now())
                ->orderBy('id')
                ->limit(50)
                ->get();

            if ($lote->isEmpty()) {
                break;
            }

            foreach ($lote as $item) {
                $resultado = $this->eliminar->eliminarRegistro($item);
                $piezas++;
                if ($resultado['archivo_eliminado']) {
                    $archivos++;
                }
                if ($resultado['archivo_ausente']) {
                    $ausentes++;
                    Log::info('Publicidad PDV depurada sin archivo local', [
                        'publicidad_id' => $resultado['publicidad_id'],
                        'medio_referenciado' => true,
                    ]);
                }
                if ($resultado['sucursal_id'] === null) {
                    $huboGlobal = true;
                } else {
                    $sucursales[$resultado['sucursal_id']] = true;
                }
            }
        } while ($lote->count() === 50);

        if ($huboGlobal) {
            $contexto = Sucursal::query()->orderBy('id')->value('id');
            if ($contexto) {
                PublicidadPdvActualizada::dispatch((int) $contexto, null, null);
            }
        }

        foreach (array_keys($sucursales) as $sucursalId) {
            PublicidadPdvActualizada::dispatch((int) $sucursalId, (int) $sucursalId, null);
        }

        $resumen = [
            'piezas' => $piezas,
            'archivos' => $archivos,
            'ausentes' => $ausentes,
        ];
        Log::info('Depuración de publicidad PDV', $resumen);

        return $resumen;
    }
}
