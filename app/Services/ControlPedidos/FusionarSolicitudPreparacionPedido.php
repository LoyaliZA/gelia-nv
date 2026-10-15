<?php

namespace App\Services\ControlPedidos;

/**
 * Merge de la intención de preparación.
 * Una clave ausente conserva el valor previo. null limpia solo esa clave.
 * false y 0 se conservan. No crea tarea ni referencia oficial.
 */
class FusionarSolicitudPreparacionPedido
{
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
        foreach (['codigo_modalidad', 'tipo_referencia', 'folio_referencia'] as $clave) {
            if (($salida[$clave] ?? null) !== null) {
                return false;
            }
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

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
