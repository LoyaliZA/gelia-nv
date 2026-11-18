<?php

namespace App\Support\ControlPedidos;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Identidad y estado de cada unidad revisable. No usa el índice del array como id permanente. */
final class UnidadesRevisionConsulta
{
    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public static function materializar(array $json): array
    {
        foreach (['anexo_sin_remision' => 'anexo', 'retiro_piezas' => 'retiro'] as $clave => $tipo) {
            foreach ($json[$clave] ?? [] as $indice => $linea) {
                if (! is_array($linea)) {
                    continue;
                }
                if (($linea['solicitud_id'] ?? '') === '' && ($linea['linea_id'] ?? '') === '') {
                    $linea['linea_id'] = (string) Str::uuid();
                }
                $linea['unidades'] = self::fusionar($linea['unidades'] ?? [], self::derivar($tipo, $linea));
                if (self::lineaYaAtendida($tipo, $linea)) {
                    $linea['unidades'] = self::sellarAtendidas($linea);
                }
                $json[$clave][$indice] = $linea;
            }
        }

        return $json;
    }

    /** Anexo ya respondido por atención operativa; no es unidad documental pendiente. */
    public static function lineaYaAtendida(string $tipo, array $linea): bool
    {
        if ($tipo === 'retiro') {
            return ($linea['estado'] ?? '') === 'aplicada';
        }
        if (in_array((string) ($linea['estado'] ?? ''), ['disponible', 'con_detalle', 'rechazado'], true)) {
            return true;
        }
        if (($linea['pendiente_revision'] ?? null) === true) {
            return false;
        }
        if (($linea['pendiente_revision'] ?? null) === false) {
            return true;
        }

        return ($linea['respondida_at'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return list<array<string, mixed>>
     */
    public static function derivar(string $tipo, array $linea): array
    {
        if ($tipo === 'anexo') {
            $docs = array_values(array_diff(
                self::idsDocumentos($linea['documentos'] ?? [], $linea['documento_id'] ?? null),
                self::idsDocumentos($linea['documentos_soporte'] ?? [], null)
            ));
            if ($docs !== []) {
                return array_map(fn (int $id) => self::plantilla($tipo, $linea, 'captura', $id), $docs);
            }

            return [self::plantilla($tipo, $linea, 'sku', null)];
        }
        $docs = self::idsDocumentos($linea['documentos_piezas'] ?? [], null);
        if ($docs !== []) {
            return array_map(fn (int $id) => self::plantilla($tipo, $linea, 'captura', $id), $docs);
        }
        $piezas = is_array($linea['piezas_solicitadas'] ?? null) ? $linea['piezas_solicitadas'] : (is_array($linea['piezas'] ?? null) ? $linea['piezas'] : []);
        if ($piezas !== []) {
            return array_values(array_map(fn ($pieza) => self::plantilla($tipo, $linea, 'sku', null, is_array($pieza) ? $pieza : []), $piezas));
        }

        return [self::plantilla($tipo, $linea, 'sku', null)];
    }

    public static function id(string $tipo, array $linea, string $clase, ?int $documentoId, array $pieza = []): string
    {
        $solicitud = (string) ($linea['solicitud_id'] ?? '');
        $lineaId = (string) ($linea['linea_id'] ?? '');
        $ancla = $solicitud !== '' ? $solicitud : $lineaId;
        if ($documentoId) {
            return $ancla !== '' ? "{$tipo}:{$ancla}:doc:{$documentoId}" : "{$tipo}:doc:{$documentoId}";
        }
        $revision = (string) ($pieza['revision_id'] ?? $linea['sustituye_revision_id'] ?? '');
        if ($revision !== '') {
            return $ancla !== '' ? "{$tipo}:{$ancla}:rev:{$revision}" : "{$tipo}:rev:{$revision}";
        }
        $sku = (string) ($pieza['sku'] ?? $linea['sku'] ?? '');
        if ($ancla !== '') {
            return "{$tipo}:{$ancla}:sku:{$sku}";
        }

        return $tipo.':legacy:'.sha1((string) ($linea['solicitado_at'] ?? '').'|'.$sku.'|'.(string) ($linea['descripcion'] ?? $pieza['descripcion'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{tipo: string, indice: int, linea: array<string, mixed>, unidad: array<string, mixed>}|null
     */
    public static function localizar(array $json, string $unidadId): ?array
    {
        foreach (['anexo_sin_remision' => 'anexo', 'retiro_piezas' => 'retiro'] as $clave => $tipo) {
            foreach ($json[$clave] ?? [] as $indice => $linea) {
                if (! is_array($linea)) {
                    continue;
                }
                foreach ($linea['unidades'] ?? [] as $unidad) {
                    if (is_array($unidad) && ($unidad['unidad_id'] ?? '') === $unidadId) {
                        return ['tipo' => $tipo, 'indice' => (int) $indice, 'linea' => $linea, 'unidad' => $unidad, 'clave' => $clave];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $linea
     * @param  array<string, mixed>  $respuesta
     * @return array<string, mixed>
     */
    public static function aplicar(array $linea, string $unidadId, array $respuesta, int $actorId, string $actorNombre): array
    {
        $unidades = is_array($linea['unidades'] ?? null) ? $linea['unidades'] : [];
        $encontrada = false;
        foreach ($unidades as $indice => $unidad) {
            if (($unidad['unidad_id'] ?? '') !== $unidadId) {
                continue;
            }
            $encontrada = true;
            $anterior = $unidad['resultado'] ?? null;
            if ($anterior && $anterior !== ($respuesta['resultado'] ?? null)) {
                $unidad['historial'] = array_values(array_merge($unidad['historial'] ?? [], [[
                    'resultado' => $anterior,
                    'por_id' => $unidad['respondida_por_id'] ?? null,
                    'por' => $unidad['respondida_por'] ?? null,
                    'at' => $unidad['respondida_at'] ?? null,
                    'revision_ids' => $unidad['revision_ids'] ?? [],
                ]]));
            }
            if ($anterior === ($respuesta['resultado'] ?? null) && ($respuesta['revision_ids'] ?? $unidad['revision_ids'] ?? []) === ($unidad['revision_ids'] ?? [])) {
                break;
            }
            $unidad['resultado'] = $respuesta['resultado'];
            $unidad['respondida_at'] = now()->toIso8601String();
            $unidad['respondida_por_id'] = $actorId;
            $unidad['respondida_por'] = $actorNombre;
            if (array_key_exists('revision_ids', $respuesta)) {
                $unidad['revision_ids'] = $respuesta['revision_ids'];
            }
            $unidades[$indice] = $unidad;
            break;
        }
        if (! $encontrada) {
            throw ValidationException::withMessages(['unidad_id' => 'La unidad no pertenece a esta consulta.']);
        }
        $linea['unidades'] = $unidades;
        $pendientes = array_filter($unidades, fn ($unidad) => empty($unidad['resultado']));
        if ($pendientes === []) {
            $incidencias = array_filter($unidades, fn ($unidad) => ($unidad['resultado'] ?? '') === 'incidencia');
            $linea['respuesta_cambio'] = $incidencias !== [] ? 'incidencia' : 'correcto';
        }

        return $linea;
    }

    public static function documentoDeLaLinea(array $linea, int $documentoId): bool
    {
        if ($documentoId < 1) {
            return false;
        }
        foreach (array_merge($linea['documentos'] ?? [], $linea['documentos_piezas'] ?? []) as $documento) {
            $id = (int) (is_array($documento) ? ($documento['id'] ?? 0) : $documento);
            if ($id === $documentoId) {
                return true;
            }
        }

        return (int) ($linea['documento_id'] ?? 0) === $documentoId;
    }

    /**
     * @param  list<array<string, mixed>>  $guardadas
     * @param  list<array<string, mixed>>  $derivadas
     * @return list<array<string, mixed>>
     */
    private static function fusionar(array $guardadas, array $derivadas): array
    {
        $porId = [];
        foreach ($guardadas as $unidad) {
            if (is_array($unidad) && ($unidad['unidad_id'] ?? '') !== '') {
                $porId[$unidad['unidad_id']] = $unidad;
            }
        }
        foreach ($derivadas as $unidad) {
            $id = (string) ($unidad['unidad_id'] ?? '');
            if ($id === '' || isset($porId[$id])) {
                continue;
            }
            $documentoId = (int) ($unidad['documento_id'] ?? 0);
            if ($documentoId > 0 && collect($porId)->contains(fn ($fila) => (int) ($fila['documento_id'] ?? 0) === $documentoId)) {
                continue;
            }
            if (($unidad['clase'] ?? '') === 'sku' && collect($porId)->contains(fn ($fila) => ($fila['clase'] ?? '') === 'sku' && empty($fila['documento_id']))) {
                continue;
            }
            $porId[$id] = $unidad;
        }

        return array_values($porId);
    }

    /**
     * @param  array<string, mixed>  $linea
     * @param  array<string, mixed>  $pieza
     * @return array<string, mixed>
     */
    private static function plantilla(string $tipo, array $linea, string $clase, ?int $documentoId, array $pieza = []): array
    {
        return [
            'unidad_id' => self::id($tipo, $linea, $clase, $documentoId, $pieza),
            'clase' => $clase,
            'tipo' => $tipo,
            'solicitud_id' => $linea['solicitud_id'] ?? null,
            'documento_id' => $documentoId,
            'sku' => $pieza['sku'] ?? $linea['sku'] ?? null,
            'descripcion' => $pieza['descripcion'] ?? $linea['descripcion'] ?? null,
            'resultado' => null,
            'respondida_at' => null,
            'respondida_por_id' => null,
            'respondida_por' => null,
            'revision_ids' => [],
            'historial' => [],
        ];
    }

    /**
     * @param  mixed  $documentos
     * @return list<int>
     */
    private static function idsDocumentos(mixed $documentos, mixed $documentoId): array
    {
        $ids = [];
        foreach (is_array($documentos) ? $documentos : [] as $documento) {
            $id = (int) (is_array($documento) ? ($documento['id'] ?? 0) : $documento);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $principal = (int) $documentoId;
        if ($principal > 0) {
            array_unshift($ids, $principal);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return list<array<string, mixed>>
     */
    private static function sellarAtendidas(array $linea): array
    {
        $resultado = ($linea['estado'] ?? '') === 'con_detalle' || ($linea['respuesta_cambio'] ?? '') === 'incidencia'
            ? 'incidencia'
            : 'correcto';
        $unidades = [];
        foreach ($linea['unidades'] ?? [] as $unidad) {
            if (! is_array($unidad)) {
                continue;
            }
            if (empty($unidad['resultado'])) {
                $unidad['resultado'] = $resultado;
                $unidad['respondida_at'] = $linea['respondida_at'] ?? $unidad['respondida_at'] ?? null;
                $unidad['respondida_por'] = $linea['respondida_por'] ?? $unidad['respondida_por'] ?? null;
            }
            $unidades[] = $unidad;
        }

        return $unidades;
    }
}
