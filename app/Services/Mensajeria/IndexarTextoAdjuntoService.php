<?php

namespace App\Services\Mensajeria;

use App\Models\Mensaje;
use App\Models\MensajeAdjunto;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use ZipArchive;

class IndexarTextoAdjuntoService
{
    private const MAX_ARCHIVO_BYTES = 8 * 1024 * 1024;

    private const MAX_TEXTO_GUARDADO = 120_000;

    private const MAX_FILAS_EXCEL = 400;

    public function ejecutar(MensajeAdjunto $adjunto): ?string
    {
        if (!Schema::hasColumn('mensaje_adjuntos', 'contenido_indexado')) {
            return null;
        }

        if (filled($adjunto->contenido_indexado)) {
            return $adjunto->contenido_indexado;
        }

        $adjunto->loadMissing('mensaje:id,tipo,contenido');

        $etiquetas = $this->etiquetasBusqueda($adjunto);
        $extraido = $this->extraerContenidoArchivo($adjunto);
        $texto = $this->normalizar(trim($etiquetas . ' ' . $extraido));

        $adjunto->update(['contenido_indexado' => $texto]);

        return $texto;
    }

    public function etiquetasBusqueda(MensajeAdjunto $adjunto): string
    {
        $nombre = trim((string) ($adjunto->nombre_original ?: ''));
        $baseRuta = basename((string) $adjunto->ruta);
        $mime = strtolower((string) $adjunto->mime);
        $ext = strtolower(pathinfo($nombre ?: $baseRuta, PATHINFO_EXTENSION));

        $partes = array_filter([
            $nombre,
            $baseRuta !== $nombre ? $baseRuta : null,
            $ext !== '' ? $ext : null,
            $mime,
        ]);

        $tipo = $adjunto->mensaje?->tipo;
        $partes = array_merge($partes, match ($tipo) {
            Mensaje::TIPO_IMAGEN => ['imagen', 'foto', 'picture', 'image'],
            Mensaje::TIPO_VIDEO => ['video'],
            Mensaje::TIPO_AUDIO => ['audio'],
            default => [],
        });

        if ($ext === 'sql' || str_contains($mime, 'sql')) {
            $partes[] = 'sql';
        }

        $caption = trim((string) ($adjunto->mensaje?->contenido ?? ''));
        if ($caption !== '') {
            $partes[] = $caption;
        }

        return implode(' ', array_unique($partes));
    }

    private function extraerContenidoArchivo(MensajeAdjunto $adjunto): string
    {
        $ruta = $adjunto->ruta;
        if (!$ruta || !Storage::disk('public')->exists($ruta)) {
            return '';
        }

        $path = Storage::disk('public')->path($ruta);
        $tamano = $adjunto->tamano ?: (is_file($path) ? filesize($path) : 0);
        if ($tamano > self::MAX_ARCHIVO_BYTES) {
            return '';
        }

        $nombre = $adjunto->nombre_original ?? basename($ruta);
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $mime = strtolower((string) $adjunto->mime);

        if ($adjunto->mensaje?->tipo === Mensaje::TIPO_IMAGEN) {
            return '';
        }

        return match (true) {
            $this->esTextoPlano($ext, $mime) => $this->leerTextoPlano($path),
            in_array($ext, ['csv', 'xlsx', 'xls', 'ods'], true) || str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel') => $this->extraerExcel($path),
            $ext === 'pdf' || $mime === 'application/pdf' => $this->extraerPdf($path),
            $ext === 'docx' || str_contains($mime, 'wordprocessingml') => $this->extraerDocx($path),
            default => '',
        };
    }

    private function esTextoPlano(string $ext, string $mime): bool
    {
        if (in_array($ext, [
            'txt', 'md', 'json', 'xml', 'log', 'csv', 'sql', 'php', 'js', 'ts', 'jsx', 'tsx',
            'yaml', 'yml', 'env', 'ini', 'sh', 'bash', 'py', 'rb', 'java', 'c', 'cpp', 'h',
        ], true)) {
            return true;
        }

        return str_starts_with($mime, 'text/')
            || str_contains($mime, 'sql')
            || $mime === 'application/json'
            || $mime === 'application/xml';
    }

    private function leerTextoPlano(string $path): string
    {
        $raw = @file_get_contents($path);

        return is_string($raw) ? $raw : '';
    }

    private function extraerExcel(string $path): string
    {
        $lineas = [];

        try {
            foreach ((new FastExcel)->import($path) as $fila) {
                if (is_array($fila)) {
                    $lineas[] = implode(' ', array_map(fn ($v) => trim((string) $v), $fila));
                } else {
                    $lineas[] = trim((string) $fila);
                }

                if (count($lineas) >= self::MAX_FILAS_EXCEL) {
                    break;
                }
            }
        } catch (\Throwable) {
            return '';
        }

        return implode("\n", $lineas);
    }

    private function extraerPdf(string $path): string
    {
        if ($this->comandoDisponible('pdftotext')) {
            $salida = tempnam(sys_get_temp_dir(), 'gelia_pdf_');
            if (!$salida) {
                return '';
            }

            $cmd = sprintf(
                'pdftotext -q %s %s 2>/dev/null',
                escapeshellarg($path),
                escapeshellarg($salida)
            );
            @exec($cmd);
            $texto = is_readable($salida) ? (string) file_get_contents($salida) : '';
            @unlink($salida);

            return $texto;
        }

        return '';
    }

    private function extraerDocx(string $path): string
    {
        if (!class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!is_string($xml) || $xml === '') {
            return '';
        }

        $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $texto = strip_tags($xml);
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $texto;
    }

    private function normalizar(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');

        if ($texto === '') {
            return '';
        }

        if (mb_strlen($texto) > self::MAX_TEXTO_GUARDADO) {
            $texto = mb_substr($texto, 0, self::MAX_TEXTO_GUARDADO);
        }

        return $texto;
    }

    private function comandoDisponible(string $comando): bool
    {
        $which = @shell_exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($comando)));

        return is_string($which) && trim($which) !== '';
    }
}
