<?php

namespace App\Services\Facturas;

use App\Models\AuditoriaSolicitudFactura;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;
use App\Models\User;
use App\Notifications\AlertaFactura;
use App\Support\Facturas\CamposIncorrectosFactura;
use App\Support\Facturas\FacturaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CorregirSolicitudFacturaEncargadaService
{
    public function __construct(
        private ActualizarDatosFiscalesSolicitudService $actualizarFiscales,
        private ImportarDatosFiscalesService $importarDatosFiscales,
    ) {}

    public function ejecutar(SolicitudFactura $solicitud, array $datos, User $usuario): SolicitudFactura
    {
        return DB::transaction(function () use ($solicitud, $datos, $usuario) {
            $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');
            if ($idPendiente === null || (int) $solicitud->catalogo_estado_solicitud_id !== $idPendiente) {
                abort(422, 'Solo se pueden corregir solicitudes pendientes.');
            }

            $corregidos = CamposIncorrectosFactura::filtrar($datos['campos_corregidos'] ?? []);
            $updates = [];

            if (! empty($datos['razon_social'])) {
                $updates['razon_social'] = $datos['razon_social'];
                $corregidos[] = CamposIncorrectosFactura::RAZON_SOCIAL;
            }

            if (isset($datos['archivo_fiscal']) && $datos['archivo_fiscal'] instanceof UploadedFile && $datos['archivo_fiscal']->isValid()) {
                FacturaStorage::delete($solicitud->archivo_fiscal_path);
                $updates['datos_fiscales'] = $this->importarDatosFiscales->extraer($datos['archivo_fiscal']);
                $updates['archivo_fiscal_path'] = $datos['archivo_fiscal']->store('facturas/fiscales', FacturaStorage::storeDisk());
                $corregidos[] = CamposIncorrectosFactura::ARCHIVO_FISCAL;
            }

            if (! empty($datos['datos_fiscales']) && is_array($datos['datos_fiscales'])) {
                $fiscalesActualizados = $this->actualizarFiscales->mergeEnSolicitud($solicitud, $datos['datos_fiscales']);
                $corregidos = array_merge($corregidos, $fiscalesActualizados);
            }

            $corregidos = array_values(array_unique($corregidos));
            $restantes = CamposIncorrectosFactura::quitarResueltos($solicitud->campos_incorrectos, $corregidos);
            $updates['campos_incorrectos'] = $restantes === [] ? null : $restantes;

            if ($updates !== []) {
                $solicitud->update($updates);
            }

            AuditoriaSolicitudFactura::create([
                'solicitud_factura_id' => $solicitud->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                'motivo_reporte' => $datos['motivo'] ?? 'Corrección manual por encargada.',
                'datos_snapshot' => [
                    'campos_corregidos' => $corregidos,
                    'campos_incorrectos_restantes' => $restantes,
                ],
            ]);

            $solicitud = $solicitud->fresh(['vendedor', 'estado', 'vouchers', 'pdfsEmitidos', 'cliente']);

            if ($solicitud->vendedor && $corregidos !== []) {
                $etiquetas = array_map(fn (string $c) => CamposIncorrectosFactura::etiqueta($c), $corregidos);
                $solicitud->vendedor->notify(new AlertaFactura(
                    $solicitud,
                    'corregida_encargada',
                    "La encargada corrigió campos en {$solicitud->folio}: ".implode(', ', $etiquetas).'.'
                ));
            }

            return $solicitud;
        });
    }
}
