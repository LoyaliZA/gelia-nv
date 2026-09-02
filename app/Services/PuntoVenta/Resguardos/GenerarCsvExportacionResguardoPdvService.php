<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Support\PuntoVenta\Resguardos\ColumnasExportacionResguardoPdv;
use Illuminate\Support\Facades\Storage;

class GenerarCsvExportacionResguardoPdvService
{
    public function __construct(
        private readonly ConsultaBandejasResguardoPdvService $bandejas,
        private readonly ConsultaAuditoriaResguardoPdvService $auditoria,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{path: string, nombre_archivo: string, tamano_bytes: int, num_registros: int, resguardo_id: ?int}
     */
    public function ejecutar(User $usuario, array $filtros, string $exportacionId): array
    {
        $tipo = ResguardoPdvExportacionTipo::desdeFiltros($filtros);

        if ($tipo === ResguardoPdvExportacionTipo::AUDITORIA) {
            return $this->generarAuditoria($usuario, $filtros, $exportacionId);
        }

        return $this->generarListado($usuario, $filtros, $exportacionId);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{path: string, nombre_archivo: string, tamano_bytes: int, num_registros: int, resguardo_id: ?int}
     */
    private function generarListado(User $usuario, array $filtros, string $exportacionId): array
    {
        $filas = $this->bandejas->filasParaExportacion($usuario, $filtros);
        $columnas = ColumnasExportacionResguardoPdv::listado();
        $nombre = 'resguardos_listado_'.$exportacionId.'.csv';

        return $this->escribirCsv($exportacionId, $nombre, $columnas, $filas, null);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{path: string, nombre_archivo: string, tamano_bytes: int, num_registros: int, resguardo_id: ?int}
     */
    private function generarAuditoria(User $usuario, array $filtros, string $exportacionId): array
    {
        $resguardo = ResguardoPdv::query()->findOrFail((int) ($filtros['resguardo_id'] ?? 0));
        $payload = $this->auditoria->obtener($usuario, $resguardo, $this->filtrosAuditoria($filtros));

        $filas = array_map(
            fn (array $item) => $this->filaAuditoria($resguardo, $item),
            $payload['timeline'],
        );

        $columnas = ColumnasExportacionResguardoPdv::auditoria();
        $folio = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($resguardo->snapshot_folio ?: $resguardo->id));
        $nombre = 'resguardo_auditoria_'.$folio.'_'.$exportacionId.'.csv';

        return $this->escribirCsv($exportacionId, $nombre, $columnas, $filas, $resguardo->id);
    }

    /**
     * @param  array<string, string>  $columnas
     * @param  list<array<string, mixed>>  $filas
     * @return array{path: string, nombre_archivo: string, tamano_bytes: int, num_registros: int, resguardo_id: ?int}
     */
    private function escribirCsv(
        string $exportacionId,
        string $nombreArchivo,
        array $columnas,
        array $filas,
        ?int $resguardoId,
    ): array {
        $rutaRelativa = 'pdv/resguardos/exportaciones/'.$exportacionId.'.csv';
        $disco = Storage::disk('local');
        $disco->makeDirectory('pdv/resguardos/exportaciones');

        $rutaAbsoluta = $disco->path($rutaRelativa);
        $out = fopen($rutaAbsoluta, 'w');
        if ($out === false) {
            throw new \RuntimeException('No se pudo crear el archivo de exportación.');
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_values($columnas));

        foreach ($filas as $fila) {
            $valores = [];
            foreach (array_keys($columnas) as $clave) {
                $valores[] = $fila[$clave] ?? '';
            }
            fputcsv($out, $valores);
        }

        fclose($out);

        $tamano = (int) filesize($rutaAbsoluta);

        return [
            'path' => $rutaRelativa,
            'nombre_archivo' => $nombreArchivo,
            'tamano_bytes' => $tamano,
            'num_registros' => count($filas),
            'resguardo_id' => $resguardoId,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function filaAuditoria(ResguardoPdv $resguardo, array $item): array
    {
        $metadata = $item['metadata_legible'] ?? [];
        $detalle = [];
        if (is_array($metadata)) {
            foreach ($metadata as $clave => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }
                if (is_scalar($valor)) {
                    $detalle[] = $clave.': '.$valor;
                }
            }
        }

        return [
            'resguardo_id' => $resguardo->id,
            'resguardo_folio' => $resguardo->snapshot_folio,
            'ocurrido_at' => $this->formatearFecha($item['ocurrido_at'] ?? null),
            'tipo_evento' => $item['tipo_etiqueta'] ?? ($item['tipo_evento'] ?? ''),
            'categoria' => $item['categoria'] ?? '',
            'estado_anterior' => $item['estado_anterior_etiqueta'] ?? ($item['estado_anterior'] ?? ''),
            'estado_nuevo' => $item['estado_nuevo_etiqueta'] ?? ($item['estado_nuevo'] ?? ''),
            'actor' => $item['actor_referencia'] ?? '',
            'bulto_folio' => $item['bulto_folio'] ?? '',
            'detalle' => implode(' | ', $detalle),
        ];
    }

    private function formatearFecha(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($valor)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return (string) $valor;
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosAuditoria(array $filtros): array
    {
        return array_filter([
            'tipo_evento' => $filtros['tipo_evento'] ?? null,
            'categoria' => $filtros['categoria'] ?? null,
            'desde' => $filtros['desde'] ?? null,
            'hasta' => $filtros['hasta'] ?? null,
        ], static fn ($valor) => $valor !== null && $valor !== '');
    }
}
