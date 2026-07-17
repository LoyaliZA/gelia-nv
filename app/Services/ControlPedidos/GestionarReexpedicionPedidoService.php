<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoReexpedicionPedido;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GestionarReexpedicionPedidoService
{
    /**
     * Crea/actualiza vínculos CP ↔ varias paqueterías.
     * Cada ítem: paqueteria_id + costo_adicional opcional (cae a costo_base).
     *
     * @param  array<int, array{paqueteria_id: int|string, costo_adicional?: float|int|string|null}>  $paqueterias
     * @return array{creados: int, actualizados: int}
     */
    public function guardarPorCodigoPostal(
        string $codigoPostal,
        float|int|string|null $costoBase,
        array $paqueterias,
        bool $activo = true,
    ): array {
        $cp = trim($codigoPostal);
        if ($cp === '') {
            throw new \InvalidArgumentException('El código postal es requerido.');
        }
        if ($paqueterias === []) {
            throw new \InvalidArgumentException('Seleccione al menos una paquetería.');
        }

        $base = $costoBase === '' || $costoBase === null ? null : (float) $costoBase;
        $creados = 0;
        $actualizados = 0;

        DB::transaction(function () use ($cp, $base, $paqueterias, $activo, &$creados, &$actualizados) {
            $vistos = [];
            foreach ($paqueterias as $item) {
                $paqId = (int) ($item['paqueteria_id'] ?? 0);
                if ($paqId < 1 || isset($vistos[$paqId])) {
                    continue;
                }
                $vistos[$paqId] = true;

                $override = $item['costo_adicional'] ?? null;
                $costo = ($override === '' || $override === null) ? $base : (float) $override;

                $existente = CatalogoReexpedicionPedido::query()
                    ->where('codigo_postal', $cp)
                    ->where('paqueteria_id', $paqId)
                    ->first();

                if ($existente) {
                    $existente->update([
                        'costo_adicional' => $costo,
                        'activo' => $activo,
                    ]);
                    $actualizados++;
                } else {
                    CatalogoReexpedicionPedido::create([
                        'codigo_postal' => $cp,
                        'paqueteria_id' => $paqId,
                        'costo_adicional' => $costo,
                        'activo' => $activo,
                    ]);
                    $creados++;
                }
            }
        });

        if ($creados + $actualizados === 0) {
            throw new \InvalidArgumentException('No se pudo vincular ninguna paquetería válida.');
        }

        return compact('creados', 'actualizados');
    }

    public function plantillaCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['codigo_postal', 'paqueterias', 'costo_adicional', 'activo']);
            fputcsv($out, ['64000', 'FEDEX|DHL', '85.50', '1']);
            fputcsv($out, ['64001', 'FEDEX', '90.00', '1']);
            fputcsv($out, ['64000', 'Estafeta', '120.00', '1']);
            fclose($out);
        }, 'plantilla_reexpedicion_pedido.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * CSV: codigo_postal, paqueterias (nombres separados por | o ,), costo_adicional, activo(opcional).
     * Misma fila con varias paqueterías aplica el mismo costo; para override use otra fila con una sola paq.
     *
     * @return array{creados: int, actualizados: int, errores: list<string>}
     */
    public function importarCsv(UploadedFile|string $archivo): array
    {
        $path = $archivo instanceof UploadedFile ? $archivo->getRealPath() : $archivo;
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \InvalidArgumentException('No se pudo leer el archivo CSV.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new \InvalidArgumentException('CSV vacío.');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $mapa = [];
        foreach ($header as $i => $col) {
            $mapa[mb_strtolower(trim((string) $col))] = $i;
        }
        foreach (['codigo_postal', 'paqueterias', 'costo_adicional'] as $req) {
            if (! isset($mapa[$req]) && ! ($req === 'paqueterias' && isset($mapa['paqueteria']))) {
                fclose($handle);
                throw new \InvalidArgumentException("Falta columna «{$req}» en el CSV.");
            }
        }
        $colPaqs = $mapa['paqueterias'] ?? $mapa['paqueteria'];

        $porNombre = CatalogoPaqueteriaPedido::query()
            ->get(['id', 'nombre'])
            ->mapWithKeys(fn ($p) => [mb_strtoupper(trim($p->nombre)) => $p->id]);

        $creados = 0;
        $actualizados = 0;
        $errores = [];
        $linea = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $linea++;
            if ($this->filaVacia($row)) {
                continue;
            }
            try {
                $cp = trim((string) ($row[$mapa['codigo_postal']] ?? ''));
                $nombresRaw = (string) ($row[$colPaqs] ?? '');
                $costo = $row[$mapa['costo_adicional']] ?? null;
                $activoRaw = isset($mapa['activo']) ? ($row[$mapa['activo']] ?? '1') : '1';
                $activo = ! in_array(mb_strtolower(trim((string) $activoRaw)), ['0', 'no', 'false', 'inactivo'], true);

                $nombres = preg_split('/[|,;]+/', $nombresRaw) ?: [];
                $vinculos = [];
                foreach ($nombres as $nombre) {
                    $clave = mb_strtoupper(trim($nombre));
                    if ($clave === '') {
                        continue;
                    }
                    if (! isset($porNombre[$clave])) {
                        throw new \InvalidArgumentException("Paquetería desconocida: «{$nombre}»");
                    }
                    $vinculos[] = [
                        'paqueteria_id' => $porNombre[$clave],
                        'costo_adicional' => $costo,
                    ];
                }

                $res = $this->guardarPorCodigoPostal($cp, $costo, $vinculos, $activo);
                $creados += $res['creados'];
                $actualizados += $res['actualizados'];
            } catch (\Throwable $e) {
                $errores[] = "Línea {$linea}: " . $e->getMessage();
            }
        }
        fclose($handle);

        return compact('creados', 'actualizados', 'errores');
    }

    private function filaVacia(array $row): bool
    {
        foreach ($row as $cel) {
            if (trim((string) $cel) !== '') {
                return false;
            }
        }

        return true;
    }
}
