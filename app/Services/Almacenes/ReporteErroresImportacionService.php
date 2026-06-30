<?php

namespace App\Services\Almacenes;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReporteErroresImportacionService
{
    private array $errores = [];

    private ?string $token = null;

    public function agregar(int $fila, string $referencia, string $campo, string $mensaje): void
    {
        $this->errores[] = [
            'fila' => $fila,
            'referencia' => $referencia,
            'campo' => $campo,
            'mensaje' => $mensaje,
        ];
    }

    public function agregarExcepcion(int $fila, string $referencia, string $campo, \Throwable $e): void
    {
        $this->agregar($fila, $referencia, $campo, $e->getMessage());
    }

    public function tieneErrores(): bool
    {
        return count($this->errores) > 0;
    }

    public function resumen(): array
    {
        return array_map(
            fn ($e) => "Fila {$e['fila']}: {$e['mensaje']}",
            $this->errores,
        );
    }

    public function generarCsv(): ?string
    {
        if (! $this->tieneErrores()) {
            return null;
        }

        $this->token = Str::uuid()->toString();
        $relativePath = "temp/import_errores_{$this->token}.csv";
        $fullPath = Storage::path($relativePath);

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        $out = fopen($fullPath, 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['fila', 'referencia', 'campo', 'mensaje']);

        foreach ($this->errores as $error) {
            fputcsv($out, [$error['fila'], $error['referencia'], $error['campo'], $error['mensaje']]);
        }

        fclose($out);

        return $this->token;
    }

    public function token(): ?string
    {
        return $this->token;
    }
}
