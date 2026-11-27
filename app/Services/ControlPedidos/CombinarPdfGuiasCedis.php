<?php

namespace App\Services\ControlPedidos;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

class CombinarPdfGuiasCedis
{
    /** @param list<string> $archivos */
    public function ejecutar(array $archivos): string
    {
        $destino = tempnam(sys_get_temp_dir(), 'cedis-guias-');
        if ($destino === false) {
            throw new \RuntimeException('No se pudo preparar el archivo de impresión.');
        }

        try {
            $proceso = new Process(['qpdf', '--empty', '--pages', ...$archivos, '--', $destino]);
            $proceso->setTimeout(60)->mustRun();

            return $destino;
        } catch (ExceptionInterface $error) {
            unlink($destino);
            throw new \RuntimeException('No se pudo combinar el lote. Intente imprimir las guías individualmente.', previous: $error);
        }
    }
}
