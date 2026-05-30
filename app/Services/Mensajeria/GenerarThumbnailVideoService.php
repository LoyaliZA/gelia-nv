<?php

namespace App\Services\Mensajeria;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerarThumbnailVideoService
{
    public function ejecutar(string $videoRuta, int $conversacionId): ?string
    {
        $ffmpeg = trim((string) shell_exec('which ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            return null;
        }

        $inputPath = Storage::disk('public')->path($videoRuta);
        if (!file_exists($inputPath)) {
            return null;
        }

        $filename = Str::uuid() . '_thumb.jpg';
        $relativePath = "mensajeria/{$conversacionId}/{$filename}";
        $outputPath = Storage::disk('public')->path($relativePath);

        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $result = Process::run([
            $ffmpeg, '-y', '-i', $inputPath,
            '-ss', '00:00:01', '-vframes', '1',
            '-q:v', '2', $outputPath,
        ]);

        if ($result->successful() && file_exists($outputPath)) {
            return $relativePath;
        }

        return null;
    }
}
