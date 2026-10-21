<?php

namespace App\Console\Commands;

use App\Services\Productos\Imagenes\AlmacenArchivoImagenProducto;
use App\Services\Productos\Imagenes\GestionarLoteImagenProductoService;
use Illuminate\Console\Command;

class LimpiarTemporalesImagenProducto extends Command
{
    protected $signature = 'productos-imagenes:limpiar-temporales';

    protected $description = 'Caduca intentos de imágenes de producto y reintenta borrados pendientes. No se programa solo.';

    public function handle(
        GestionarLoteImagenProductoService $lotes,
        AlmacenArchivoImagenProducto $archivos,
    ): int {
        $vencidos = $lotes->expirarVencidos();
        $limpiezas = $archivos->reintentarLimpiezas();
        $this->info('Intentos vencidos: '.$vencidos.'. Limpiezas reintentadas: '.$limpiezas.'.');

        return self::SUCCESS;
    }
}
