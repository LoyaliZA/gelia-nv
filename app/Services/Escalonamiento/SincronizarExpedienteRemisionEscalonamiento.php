<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\DocumentoVentaExpedienteRemision;

class SincronizarExpedienteRemisionEscalonamiento
{
    public function __construct(
        private MapearExpedienteRemisionReporte $mapear,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  fila normalizada (reporte_remisiones, datos_fuente, fila_bruta opcional)
     */
    public function desdeDatosImportacion(DocumentoVenta $documento, array $datos): void
    {
        if (! ($datos['reporte_remisiones'] ?? false)) {
            return;
        }

        $atributos = $this->mapear->atributosExpedienteDesdeDatosNormalizados($datos);
        if ($atributos === null || ($atributos['folio'] ?? '') === '') {
            return;
        }

        $this->persistir($documento, $atributos, $datos);
    }

    public function desdeDocumentoYDatosFuente(DocumentoVenta $documento): void
    {
        $fuente = is_array($documento->datos_fuente) ? $documento->datos_fuente : [];
        if ($fuente === []) {
            return;
        }

        $atributos = $this->mapear->atributosExpedienteDesdeDatosFuente($documento, $fuente);
        if ($atributos === null || ($atributos['folio'] ?? '') === '') {
            return;
        }

        $this->persistir($documento, $atributos, null);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<string, mixed>|null  $datos
     */
    private function persistir(DocumentoVenta $documento, array $atributos, ?array $datos): void
    {
        if ($datos !== null && is_array($datos['datos_fuente'] ?? null)) {
            $documento->datos_fuente = $datos['datos_fuente'];
            $documento->save();
        }

        DocumentoVentaExpedienteRemision::query()->updateOrCreate(
            ['documento_venta_id' => $documento->id],
            array_merge($atributos, ['documento_venta_id' => $documento->id]),
        );
    }
}
