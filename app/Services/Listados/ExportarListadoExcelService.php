<?php

namespace App\Services\Listados;

use App\Models\CustomList;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * Exporta listados Excel. Nota de encabezado opcional (fila 1 mergeada);
 * sin nota el archivo queda igual que FastExcel plano.
 */
class ExportarListadoExcelService
{
    /**
     * Prioridad: request explícito > config de lista personalizada > null.
     */
    public function resolverNota(
        ?bool $mostrarRequest,
        ?string $textoRequest,
        ?CustomList $lista = null,
    ): ?string {
        if ($mostrarRequest === true) {
            $texto = trim((string) $textoRequest);

            return $texto !== '' ? $texto : null;
        }

        if ($mostrarRequest === false) {
            return null;
        }

        if ($lista && $lista->mostrar_nota_encabezado) {
            $texto = trim((string) ($lista->nota_encabezado ?? ''));

            return $texto !== '' ? $texto : null;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function exportar(array $filas, string $path, ?string $notaEncabezado = null): string
    {
        $nota = $notaEncabezado !== null ? trim($notaEncabezado) : '';
        if ($nota === '') {
            (new FastExcel($filas))->export($path);

            return realpath($path) ?: $path;
        }

        $this->exportarConNota($filas, $path, $nota);

        return realpath($path) ?: $path;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function exportarConNota(array $filas, string $path, string $nota): void
    {
        $headers = $filas !== [] ? array_keys($filas[0]) : [];
        $colCount = max(count($headers), 1);

        $writer = new Writer();
        $writer->openToFile($path);

        // Comentario discreto: gris + Arial + itálica (sin negrita)
        $estiloNota = (new Style())
            ->setFontItalic()
            ->setFontName('Arial')
            ->setFontColor('808080')
            ->setFontSize(10);

        $celdasNota = array_fill(0, $colCount, '');
        $celdasNota[0] = $nota;
        $writer->addRow(Row::fromValues($celdasNota, $estiloNota));

        if ($colCount > 1) {
            $writer->getOptions()->mergeCells(0, 1, $colCount - 1, 1);
        }

        if ($headers !== []) {
            $estiloHeader = (new Style())->setFontBold();
            $writer->addRow(Row::fromValues($headers, $estiloHeader));
        }

        foreach ($filas as $fila) {
            $writer->addRow(Row::fromValues(array_values($fila)));
        }

        $writer->close();
    }
}
