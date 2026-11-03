<?php

namespace App\Services\ControlPedidos;

/**
 * Merge de la intención de preparación.
 * Una clave ausente conserva el valor previo. null limpia solo esa clave.
 * false y 0 se conservan. No crea tarea ni referencia oficial.
 */
class FusionarSolicitudPreparacionPedido
{
    /** @var list<string> */
    private const CASOS = [
        'revisar_apartar',
        'consultar_cedis',
        'apartado_pdv',
        'mismo_dia_pdv',
        'traspaso_cedis',
        'consulta_cedis',
    ];
    /**
     * @param  array<string, mixed>|null  $actual
     * @param  array<string, mixed>|null  $entrada
     * @return array<string, mixed>|null
     */
    public function ejecutar(?array $actual, ?array $entrada): ?array
    {
        if ($entrada === null) {
            return null;
        }

        $base = is_array($actual) ? $actual : [];
        $salida = ['version' => 1];

        foreach (['codigo_modalidad', 'tipo_referencia', 'folio_referencia'] as $clave) {
            if (array_key_exists($clave, $entrada)) {
                $salida[$clave] = $this->texto($entrada[$clave]);
            } elseif (array_key_exists($clave, $base)) {
                $salida[$clave] = $this->texto($base[$clave]);
            }
        }

        if (array_key_exists('traslado_interno_pdv', $entrada)) {
            $salida['traslado_interno_pdv'] = filter_var($entrada['traslado_interno_pdv'], FILTER_VALIDATE_BOOLEAN);
        } elseif (array_key_exists('traslado_interno_pdv', $base)) {
            $salida['traslado_interno_pdv'] = filter_var($base['traslado_interno_pdv'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('atencion_tienda', $entrada)) {
            $salida['atencion_tienda'] = $this->atencion($entrada['atencion_tienda']);
        } elseif (array_key_exists('atencion_tienda', $base)) {
            $salida['atencion_tienda'] = $this->atencion($base['atencion_tienda']);
        }

        if (array_key_exists('piezas_tienda', $entrada)) {
            $salida['piezas_tienda'] = $this->piezas($entrada['piezas_tienda']);
        } elseif (array_key_exists('piezas_tienda', $base)) {
            $salida['piezas_tienda'] = $this->piezas($base['piezas_tienda']);
        }

        if (array_key_exists('modalidad_entrega', $entrada)) {
            $salida['modalidad_entrega'] = $this->modalidadEntrega($entrada['modalidad_entrega']);
        } elseif (array_key_exists('modalidad_entrega', $base)) {
            $salida['modalidad_entrega'] = $this->modalidadEntrega($base['modalidad_entrega']);
        }

        if (array_key_exists('area_revision', $entrada)) {
            $salida['area_revision'] = $this->areaRevision($entrada['area_revision']);
        } elseif (array_key_exists('area_revision', $base)) {
            $salida['area_revision'] = $this->areaRevision($base['area_revision']);
        }

        if (array_key_exists('caso_tienda', $entrada)) {
            $salida['caso_tienda'] = $this->casoTienda($entrada['caso_tienda']);
        } elseif (array_key_exists('caso_tienda', $base)) {
            $salida['caso_tienda'] = $this->casoTienda($base['caso_tienda']);
        }

        if (array_key_exists('almacen_revision_id', $entrada)) {
            $salida['almacen_revision_id'] = $this->identificador($entrada['almacen_revision_id']);
        } elseif (array_key_exists('almacen_revision_id', $base)) {
            $salida['almacen_revision_id'] = $this->identificador($base['almacen_revision_id']);
        }

        $revision = null;
        if (is_array($base['revision_disponibilidad'] ?? null)) {
            $revision = $this->revisionDisponibilidad($base['revision_disponibilidad']);
        } elseif ($actual === null && is_array($entrada['revision_disponibilidad'] ?? null)) {
            $revision = $this->revisionDisponibilidad($entrada['revision_disponibilidad']);
        }
        if ($revision !== null) {
            $salida['revision_disponibilidad'] = $revision;
        }

        $this->conservarOperacionTienda($salida, $actual === null ? $entrada : $base);

        if (array_key_exists('entrega_municipal', $entrada)) {
            if ($entrada['entrega_municipal'] === null) {
                $salida['entrega_municipal'] = null;
            } elseif (is_array($entrada['entrega_municipal'])) {
                $previa = is_array($base['entrega_municipal'] ?? null) ? $base['entrega_municipal'] : [];
                $salida['entrega_municipal'] = $this->fusionarMunicipal($previa, $entrada['entrega_municipal']);
            }
        } elseif (array_key_exists('entrega_municipal', $base)) {
            $salida['entrega_municipal'] = is_array($base['entrega_municipal'])
                ? $this->municipalVisible($base['entrega_municipal'])
                : null;
        }

        return $this->vacia($salida) ? null : $salida;
    }

    /**
     * Lectura compatible: no rellena claves que el histórico no trae.
     *
     * @return array<string, mixed>|null
     */
    public function normalizar(mixed $json): ?array
    {
        if (! is_array($json) || $json === []) {
            return null;
        }

        return $this->ejecutar(null, $json);
    }

    /**
     * @param  array<string, mixed>  $previa
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>|null
     */
    private function fusionarMunicipal(array $previa, array $entrada): ?array
    {
        $salida = $this->municipalVisible($previa);

        foreach (['destinatario_nombre', 'destinatario_telefono', 'municipio_destino', 'direccion_referencia', 'modalidad_cobro'] as $clave) {
            if (array_key_exists($clave, $entrada)) {
                $salida[$clave] = $this->texto($entrada[$clave]);
            }
        }

        if (array_key_exists('destinatario_es_cliente', $entrada)) {
            $salida['destinatario_es_cliente'] = filter_var($entrada['destinatario_es_cliente'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('catalogo_paqueteria_id', $entrada)) {
            $id = $entrada['catalogo_paqueteria_id'];
            $salida['catalogo_paqueteria_id'] = ($id === null || $id === '') ? null : (int) $id;
        }

        return $this->municipalVacio($salida) ? null : $salida;
    }

    /**
     * @param  array<string, mixed>  $municipal
     * @return array<string, mixed>
     */
    private function municipalVisible(array $municipal): array
    {
        $salida = [];
        foreach (['destinatario_nombre', 'destinatario_telefono', 'municipio_destino', 'direccion_referencia', 'modalidad_cobro'] as $clave) {
            if (array_key_exists($clave, $municipal)) {
                $salida[$clave] = $this->texto($municipal[$clave]);
            }
        }
        if (array_key_exists('destinatario_es_cliente', $municipal)) {
            $salida['destinatario_es_cliente'] = filter_var($municipal['destinatario_es_cliente'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('catalogo_paqueteria_id', $municipal) && $municipal['catalogo_paqueteria_id'] !== null && $municipal['catalogo_paqueteria_id'] !== '') {
            $salida['catalogo_paqueteria_id'] = (int) $municipal['catalogo_paqueteria_id'];
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $salida
     */
    private function vacia(array $salida): bool
    {
        if (array_key_exists('modalidad_entrega', $salida)) {
            return false;
        }
        if (in_array($salida['area_revision'] ?? null, ['preparacion_tienda', 'control_pedidos'], true)) {
            return false;
        }
        if (in_array($salida['caso_tienda'] ?? null, self::CASOS, true)) {
            return false;
        }
        if (is_array($salida['revision_disponibilidad'] ?? null)) {
            return false;
        }
        foreach ([
            'cobertura_tienda', 'segundo_traslado', 'credito_wizerp', 'tienda_md_reporte',
            'retiro_recoleccion', 'liberacion_fisica_20', 'cambio_modalidad',
            'continuacion_ejecutor', 'entrega_custodia', 'compromisos_inventario', 'complemento_pdv', 'anexo_integracion',
        ] as $clave) {
            if (is_array($salida[$clave] ?? null) && $salida[$clave] !== []) {
                return false;
            }
        }

        foreach (['codigo_modalidad', 'tipo_referencia', 'folio_referencia'] as $clave) {
            if (($salida[$clave] ?? null) !== null) {
                return false;
            }
        }
        if (array_key_exists('traslado_interno_pdv', $salida)) {
            return false;
        }
        if (in_array($salida['atencion_tienda'] ?? null, ['despues', 'prevista'], true)) {
            return false;
        }
        if (is_array($salida['piezas_tienda'] ?? null) && $salida['piezas_tienda'] !== []) {
            return false;
        }

        return $this->municipalVacio(is_array($salida['entrega_municipal'] ?? null) ? $salida['entrega_municipal'] : []);
    }

    /**
     * @param  array<string, mixed>  $municipal
     */
    private function municipalVacio(array $municipal): bool
    {
        if ($municipal === []) {
            return true;
        }
        if (array_key_exists('destinatario_es_cliente', $municipal) && $municipal['destinatario_es_cliente'] === false) {
            return false;
        }
        foreach (['destinatario_nombre', 'destinatario_telefono', 'municipio_destino', 'direccion_referencia', 'catalogo_paqueteria_id', 'modalidad_cobro'] as $clave) {
            if (($municipal[$clave] ?? null) !== null && $municipal[$clave] !== '') {
                return false;
            }
        }

        return true;
    }

    private function atencion(mixed $valor): ?string
    {
        return in_array($valor, ['despues', 'prevista'], true) ? $valor : null;
    }

    private function modalidadEntrega(mixed $valor): ?string
    {
        return in_array($valor, ['tienda', 'municipal', 'nacional'], true) ? $valor : null;
    }

    private function areaRevision(mixed $valor): ?string
    {
        return in_array($valor, ['preparacion_tienda', 'control_pedidos'], true) ? $valor : null;
    }

    private function casoTienda(mixed $valor): ?string
    {
        return in_array($valor, self::CASOS, true) ? $valor : null;
    }

    /**
     * @param  array<string, mixed>  $valor
     * @return array{estado: string, confirmada: bool, respondida_at: ?string, respondida_por_id: ?int}|null
     */
    private function revisionDisponibilidad(array $valor): ?array
    {
        $estado = $valor['estado'] ?? null;
        if (! in_array($estado, ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'], true)) {
            return null;
        }

        $respondida = $valor['respondida_at'] ?? null;
        $decision = $valor['decision_at'] ?? null;

        $salida = [
            'estado' => $estado,
            'confirmada' => (bool) ($valor['confirmada'] ?? false),
            'respondida_at' => is_string($respondida) ? $respondida : null,
            'respondida_por_id' => $this->identificador($valor['respondida_por_id'] ?? null),
            'decision_at' => is_string($decision) ? $decision : null,
            'decision_por_id' => $this->identificador($valor['decision_por_id'] ?? null),
        ];
        if (array_key_exists('aceptacion_vigente', $valor)) {
            $salida['aceptacion_vigente'] = (bool) $valor['aceptacion_vigente'];
        }
        if (array_key_exists('vigente', $valor)) {
            $salida['vigente'] = (bool) $valor['vigente'];
        }
        if (is_string($valor['sustituida_at'] ?? null) && $valor['sustituida_at'] !== '') {
            $salida['sustituida_at'] = $valor['sustituida_at'];
        }

        return $salida;
    }

    /**
     * La captura no reescribe cobertura, traslado, crédito ni corte. La lectura sí los muestra.
     *
     * @param  array<string, mixed>  $salida
     * @param  array<string, mixed>  $origen
     */
    private function conservarOperacionTienda(array &$salida, array $origen): void
    {
        foreach ([
            'cobertura_tienda',
            'segundo_traslado',
            'credito_wizerp',
            'tienda_md_reporte',
            'tienda_md',
            'retiro_recoleccion',
            'anexo_sin_remision',
            'anexos_relacionados',
            'retiro_piezas',
            'liberacion_fisica_20',
            'cambio_modalidad',
            'continuacion_ejecutor',
            'entrega_custodia',
            'compromisos_inventario',
            'complemento_pdv',
            'anexo_integracion',
        ] as $clave) {
            if (array_key_exists($clave, $origen)) {
                $salida[$clave] = $origen[$clave];
            }
        }
        foreach (['anexo_integrado', 'origen_apartado'] as $clave) {
            if (array_key_exists($clave, $origen)) {
                $salida[$clave] = $origen[$clave];
            }
        }
    }

    private function identificador(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    /**
     * @return list<array{sku: string, nombre: ?string, cantidad: int}>|null
     */
    private function piezas(mixed $entrada): ?array
    {
        if (! is_array($entrada)) {
            return null;
        }

        $salida = [];
        foreach ($entrada as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $sku = $this->texto($fila['sku'] ?? null);
            $cantidad = (int) ($fila['cantidad'] ?? 0);
            if ($sku === null || $cantidad < 1) {
                continue;
            }
            $salida[] = [
                'sku' => $sku,
                'nombre' => $this->texto($fila['nombre'] ?? null),
                'cantidad' => $cantidad,
            ];
            if (count($salida) >= 40) {
                break;
            }
        }

        return $salida === [] ? null : $salida;
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
