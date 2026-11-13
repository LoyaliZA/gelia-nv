<?php

namespace App\Services\Clientes;

use App\Models\ImportacionCliente;

class PresentarSeguimientoImportacionCliente
{
    public function progreso(ImportacionCliente $importacion): array
    {
        $resumen = is_array($importacion->resumen) ? $importacion->resumen : [];
        $revision = is_array($resumen['revision'] ?? null) ? $resumen['revision'] : [];
        $leidas = (int) $importacion->filas_leidas;
        $procesadas = (int) $importacion->filas_procesadas;
        $omitidas = (int) $importacion->filas_omitidas;
        $errores = (int) $importacion->errores;
        $atendidas = $procesadas + $omitidas + $errores;
        $fase = $this->fase($importacion->estado);
        $porcentaje = null;
        $indeterminado = true;
        if ($leidas > 0 && in_array($importacion->estado, ['analizando', 'en_curso', 'en_cola', 'completo', 'parcial', 'fallido'], true)) {
            $tope = in_array($importacion->estado, ['completo', 'parcial'], true) ? 100 : 99;
            $porcentaje = (int) min($tope, floor(($atendidas / $leidas) * 100));
            $indeterminado = false;
        }

        return [
            'id' => $importacion->id,
            'estado' => $importacion->estado,
            'fase' => $fase,
            'nombre_archivo' => $importacion->nombre_archivo_original,
            'filas_leidas' => $leidas,
            'filas_procesadas' => $procesadas,
            'filas_omitidas' => $omitidas,
            'pendientes' => $leidas > 0 ? max(0, $leidas - $atendidas) : null,
            'creados' => (int) ($resumen['creados'] ?? 0),
            'actualizados' => (int) ($resumen['actualizados'] ?? 0),
            'sin_cambios' => (int) ($resumen['sin_cambios'] ?? ($resumen['conteos']['sin_cambios'] ?? 0)),
            'errores' => $errores,
            'conteos' => $resumen['conteos'] ?? null,
            'bloqueos' => $resumen['bloqueos'] ?? [],
            'avisos' => $resumen['avisos'] ?? [],
            'bloqueado' => (bool) ($revision['bloqueado'] ?? false),
            'token' => $importacion->estado === 'previsualizada' ? ($revision['token'] ?? null) : null,
            'porcentaje' => $porcentaje,
            'indeterminado' => $indeterminado,
            'mensaje' => $importacion->mensaje_fatal,
            'puede_confirmar' => $importacion->estado === 'previsualizada' && empty($revision['bloqueado']),
        ];
    }

    public function vistaFlash(ImportacionCliente $importacion): array
    {
        $resumen = is_array($importacion->resumen) ? $importacion->resumen : [];
        $revision = is_array($resumen['revision'] ?? null) ? $resumen['revision'] : [];

        return [
            'importacion_id' => $importacion->id,
            'token' => $revision['token'] ?? null,
            'bloqueado' => (bool) ($revision['bloqueado'] ?? false),
            'bloqueos' => $resumen['bloqueos'] ?? [],
            'avisos' => $resumen['avisos'] ?? [],
            'conteos' => $resumen['conteos'] ?? [],
            'muestras' => $resumen['muestras'] ?? [],
        ];
    }

    public function fase(?string $estado): string
    {
        return match ($estado) {
            'recibido' => 'archivo_recibido',
            'analizando' => 'analizando',
            'previsualizada' => 'esperando_confirmacion',
            'en_cola', 'en_curso' => 'procesando',
            'completo' => 'completado',
            'parcial' => 'completado_con_incidencias',
            'fallido' => 'error',
            default => 'preparando',
        };
    }
}
