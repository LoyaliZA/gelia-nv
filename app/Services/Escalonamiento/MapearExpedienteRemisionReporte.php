<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\DocumentoVenta;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Normaliza filas del reporte ERP de remisiones a datos_fuente (strings) y atributos del expediente tipado.
 */
class MapearExpedienteRemisionReporte
{
    /**
     * @param  array<string, mixed>  $bruto
     * @return array<string, string>
     */
    public function datosFuenteDesdeBruto(array $bruto): array
    {
        $porClave = $this->indicePorClave($bruto);

        $mapa = [
            'subtotal' => 'subtotal',
            'descuento' => 'descuento',
            'i_v_a' => 'iva',
            'iva' => 'iva',
            'imp_ieps' => 'imp_ieps',
            'importe_ieps' => 'imp_ieps',
            'ret_i_v_a' => 'ret_iva',
            'ret_iva' => 'ret_iva',
            'ret_i_s_r' => 'ret_isr',
            'ret_isr' => 'ret_isr',
            'ret_ieps' => 'ret_ieps',
            'utilidad' => 'utilidad',
            'facturada' => 'facturada',
            'email' => 'email',
            'condicion_de_pago' => 'condicion_pago',
            'condicion_pago' => 'condicion_pago',
            'status' => 'status',
            'status_pago' => 'status_pago',
            'vencimiento' => 'vencimiento',
            'metodo_de_pago' => 'metodo_pago',
            'metodo_pago' => 'metodo_pago',
            'origen' => 'origen_documento',
            'almacen' => 'almacen',
            'vendedor' => 'vendedor',
            'plataforma' => 'plataforma',
            'no_de_venta_en_plataforma' => 'numero_venta_plataforma',
            'numero_venta_plataforma' => 'numero_venta_plataforma',
        ];
        $monetarios = ['subtotal', 'descuento', 'iva', 'imp_ieps', 'ret_iva', 'ret_isr', 'ret_ieps', 'utilidad'];
        $salida = [];
        foreach ($mapa as $origen => $destino) {
            if (! array_key_exists($origen, $porClave) || array_key_exists($destino, $salida)) {
                continue;
            }
            $texto = $this->textoFuente($porClave[$origen], in_array($destino, $monetarios, true) || $destino === 'imp_ieps');
            if ($texto === null) {
                continue;
            }
            $salida[$destino] = $texto;
        }

        $hora = $this->fechaHoraTexto($porClave['fecha'] ?? null);
        if ($hora !== null) {
            $salida['fecha_hora'] = $hora;
        }
        $nombre = $this->texto($porClave['cliente'] ?? '');
        if ($nombre !== '') {
            $salida['nombre'] = $nombre;
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $bruto
     * @param  array<string, mixed>  $datosNormalizados  fila tras normalizarFila (folio, total, moneda, etc.)
     * @return array<string, mixed>|null
     */
    public function atributosExpedienteDesdeReporte(array $bruto, array $datosNormalizados): ?array
    {
        if (! ($datosNormalizados['reporte_remisiones'] ?? false)) {
            return null;
        }

        $porClave = $this->indicePorClave($bruto);
        $fuente = is_array($datosNormalizados['datos_fuente'] ?? null)
            ? $datosNormalizados['datos_fuente']
            : $this->datosFuenteDesdeBruto($bruto);

        return $this->atributosDesdeFuenteYEncabezado($fuente, $datosNormalizados, $porClave);
    }

    /**
     * @param  array<string, mixed>  $datosNormalizados
     * @return array<string, mixed>|null
     */
    public function atributosExpedienteDesdeDatosNormalizados(array $datosNormalizados): ?array
    {
        if (! ($datosNormalizados['reporte_remisiones'] ?? false)) {
            return null;
        }

        $fuente = is_array($datosNormalizados['datos_fuente'] ?? null)
            ? $datosNormalizados['datos_fuente']
            : [];

        $bruto = is_array($datosNormalizados['fila_bruta'] ?? null)
            ? $datosNormalizados['fila_bruta']
            : [];

        return $this->atributosDesdeFuenteYEncabezado($fuente, $datosNormalizados, $this->indicePorClave($bruto));
    }

    /**
     * Reconstruye expediente desde datos_fuente guardados y columnas del documento.
     *
     * @param  array<string, mixed>  $datosFuente
     * @return array<string, mixed>|null
     */
    public function atributosExpedienteDesdeDatosFuente(DocumentoVenta $documento, array $datosFuente): ?array
    {
        if ($datosFuente === []) {
            return null;
        }

        $encabezado = [
            'folio' => $documento->folio,
            'sucursal' => $documento->sucursal,
            'moneda' => $documento->moneda,
            'total' => (string) $documento->total,
            'fecha_emision' => $documento->fecha_emision?->format('Y-m-d'),
        ];

        return $this->atributosDesdeFuenteYEncabezado($datosFuente, $encabezado, []);
    }

    /**
     * @param  array<string, mixed>  $fuente
     * @param  array<string, mixed>  $encabezado
     * @param  array<string, mixed>  $porClave
     * @return array<string, mixed>
     */
    private function atributosDesdeFuenteYEncabezado(array $fuente, array $encabezado, array $porClave): array
    {
        $fechaRaw = $porClave['fecha'] ?? ($fuente['fecha_hora'] ?? null);
        $fecha = $this->fechaHora($fechaRaw);

        $cliente = trim((string) ($fuente['nombre'] ?? ''));
        if ($cliente === '' && isset($porClave['cliente'])) {
            $cliente = $this->texto($porClave['cliente']);
        }

        $moneda = strtoupper(trim((string) ($encabezado['moneda'] ?? 'MXN')));
        if ($moneda === '') {
            $moneda = 'MXN';
        }

        return [
            'folio' => (string) ($encabezado['folio'] ?? ''),
            'fecha' => $fecha,
            'cliente' => $cliente !== '' ? $cliente : null,
            'sucursal' => $this->vacio($encabezado['sucursal'] ?? null),
            'moneda' => substr($moneda, 0, 3),
            'subtotal' => $this->decimalFuente($fuente, 'subtotal'),
            'descuento' => $this->decimalFuente($fuente, 'descuento'),
            'iva' => $this->decimalFuente($fuente, 'iva'),
            'importe_ieps' => $this->decimalFuente($fuente, 'imp_ieps'),
            'retencion_iva' => $this->decimalFuente($fuente, 'ret_iva'),
            'retencion_isr' => $this->decimalFuente($fuente, 'ret_isr'),
            'retencion_ieps' => $this->decimalFuente($fuente, 'ret_ieps'),
            'total' => $this->decimalValor($encabezado['total'] ?? null) ?? $this->decimalFuente($fuente, 'total'),
            'utilidad' => $this->decimalFuente($fuente, 'utilidad'),
            'facturada' => $this->booleanFuente($fuente['facturada'] ?? ($porClave['facturada'] ?? null)),
            'email' => $this->vacio($fuente['email'] ?? null),
            'condicion_pago' => $this->vacio($fuente['condicion_pago'] ?? null),
            'status' => $this->vacio($fuente['status'] ?? null),
            'vencimiento' => $this->vacio($fuente['vencimiento'] ?? null),
            'status_pago' => $this->vacio($fuente['status_pago'] ?? null),
            'metodo_pago' => $this->vacio($fuente['metodo_pago'] ?? null),
            'origen' => $this->vacio($fuente['origen_documento'] ?? null),
            'almacen' => $this->vacio($fuente['almacen'] ?? null),
            'vendedor' => $this->vacio($fuente['vendedor'] ?? null),
            'plataforma' => $this->vacio($fuente['plataforma'] ?? null),
            'numero_venta_plataforma' => $this->vacio($fuente['numero_venta_plataforma'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $bruto
     * @return array<string, mixed>
     */
    private function indicePorClave(array $bruto): array
    {
        $porClave = [];
        foreach ($bruto as $clave => $valor) {
            $porClave[$this->clave((string) $clave)] = $valor;
        }

        return $porClave;
    }

    /**
     * @param  array<string, mixed>  $fuente
     */
    private function decimalFuente(array $fuente, string $clave): ?string
    {
        if (! array_key_exists($clave, $fuente)) {
            return null;
        }

        return $this->decimalValor($fuente[$clave]);
    }

    private function decimalValor(mixed $valor): ?string
    {
        $total = $this->total($valor);

        return $total;
    }

    private function booleanFuente(mixed $valor): ?bool
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_bool($valor)) {
            return $valor;
        }
        $texto = mb_strtolower(trim($this->texto($valor)), 'UTF-8');
        if (in_array($texto, ['1', 'si', 'sí', 'true', 'yes', 'y', 'verdadero'], true)) {
            return true;
        }
        if (in_array($texto, ['0', 'no', 'false', 'n', 'falso'], true)) {
            return false;
        }

        return null;
    }

    private function fechaHora(mixed $valor): ?string
    {
        $texto = $this->fechaHoraTexto($valor);
        if ($texto === null) {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i'] as $formato) {
            $fecha = DateTimeImmutable::createFromFormat('!'.$formato, $texto);
            $errores = DateTimeImmutable::getLastErrors();
            if ($fecha instanceof DateTimeImmutable && ($errores === false || ($errores['warning_count'] === 0 && $errores['error_count'] === 0))) {
                return $fecha->format('Y-m-d H:i:s');
            }
        }

        $soloFecha = $this->fechaSolo($valor);

        return $soloFecha !== null ? $soloFecha.' 00:00:00' : $texto;
    }

    private function fechaHoraTexto(mixed $valor): ?string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function fechaSolo(mixed $valor): ?string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $formato) {
            $fecha = DateTimeImmutable::createFromFormat('!'.$formato, $texto);
            $errores = DateTimeImmutable::getLastErrors();
            if ($fecha instanceof DateTimeImmutable && ($errores === false || ($errores['warning_count'] === 0 && $errores['error_count'] === 0))) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    private function textoFuente(mixed $valor, bool $monetario): ?string
    {
        if ($monetario) {
            return $this->total($valor);
        }
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        $texto = $this->texto($valor);

        return $texto === '' ? null : $texto;
    }

    private function clave(string $nombre): string
    {
        $nombre = preg_replace('/^\xEF\xBB\xBF/', '', $nombre) ?: '';
        $nombre = mb_strtolower(trim($nombre));
        $nombre = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $nombre,
        );
        $nombre = preg_replace('/[^a-z0-9]+/', '_', $nombre) ?: '';

        return trim($nombre, '_');
    }

    private function texto(mixed $valor): string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        if (is_int($valor)) {
            return (string) $valor;
        }
        if (is_float($valor)) {
            if (floor($valor) == $valor) {
                return sprintf('%.0f', $valor);
            }

            return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
        }

        return trim((string) $valor);
    }

    private function total(mixed $valor): ?string
    {
        if ($valor instanceof DateTimeInterface || $valor === null) {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return number_format((float) $valor, 2, '.', '');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $limpio = preg_replace('/[^\d,.\-]/', '', $texto) ?: '';
        if ($limpio === '' || $limpio === '-') {
            return null;
        }

        if (str_contains($limpio, ',') && str_contains($limpio, '.')) {
            if (strrpos($limpio, ',') > strrpos($limpio, '.')) {
                $limpio = str_replace('.', '', $limpio);
                $limpio = str_replace(',', '.', $limpio);
            } else {
                $limpio = str_replace(',', '', $limpio);
            }
        } elseif (str_contains($limpio, ',')) {
            $partes = explode(',', $limpio);
            $limpio = strlen((string) end($partes)) === 2
                ? str_replace(',', '.', $limpio)
                : str_replace(',', '', $limpio);
        }

        if (! is_numeric($limpio)) {
            return null;
        }

        return bcadd($limpio, '0', 2);
    }

    private function vacio(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
