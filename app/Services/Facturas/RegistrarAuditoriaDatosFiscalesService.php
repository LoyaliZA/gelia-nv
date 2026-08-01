<?php

namespace App\Services\Facturas;

use App\Services\Auditoria\RegistrarAuditoriaConfiguracionService;

/** Auditoría de salidas/cambios fiscales sin volcar PII completa. */
class RegistrarAuditoriaDatosFiscalesService
{
    /**
     * @param  list<string>  $campos
     */
    public function clienteActualizado(int $clienteId, array $campos): void
    {
        RegistrarAuditoriaConfiguracionService::ejecutar(
            'Datos fiscales',
            'Actualización padrón cliente',
            [
                'cliente_id' => $clienteId,
                'campos' => array_values($campos),
            ]
        );
    }

    /**
     * @param  list<string>  $campos
     */
    public function receptorCambiado(int $receptorId, string $accion, array $campos = []): void
    {
        RegistrarAuditoriaConfiguracionService::ejecutar(
            'Datos fiscales',
            $accion,
            [
                'receptor_fiscal_id' => $receptorId,
                'campos' => array_values($campos),
            ]
        );
    }

    /**
     * @param  array{actualizados?: int, omitidos?: int, errores?: list<string>}  $stats
     */
    public function importMasivo(string $tipo, array $stats): void
    {
        RegistrarAuditoriaConfiguracionService::ejecutar(
            'Datos fiscales',
            'Importación masiva '.$tipo,
            [
                'actualizados' => (int) ($stats['actualizados'] ?? 0),
                'omitidos' => (int) ($stats['omitidos'] ?? 0),
                'errores' => count($stats['errores'] ?? []),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function exportSolicitudes(int $filas, array $filtros = []): void
    {
        RegistrarAuditoriaConfiguracionService::ejecutar(
            'Datos fiscales',
            'Exportación Excel solicitudes factura',
            [
                'filas' => $filas,
                'filtros' => $filtros,
            ]
        );
    }

    public function apiCampoSensible(int $aplicacionId, string $slug, bool $habilitado): void
    {
        RegistrarAuditoriaConfiguracionService::ejecutar(
            'Datos fiscales',
            $habilitado ? 'Activación campo API sensible' : 'Desactivación campo API sensible',
            [
                'api_aplicacion_id' => $aplicacionId,
                'slug' => $slug,
                'habilitado' => $habilitado,
            ]
        );
    }
}
