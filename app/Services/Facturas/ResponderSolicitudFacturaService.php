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

class ResponderSolicitudFacturaService
{
    public function __construct(
        private GenerarEnlaceDatosFiscalesService $generarEnlace,
    ) {}

    public function ejecutar(SolicitudFactura $solicitud, array $datos, User $usuario): SolicitudFactura
    {
        return DB::transaction(function () use ($solicitud, $datos, $usuario) {
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
            $estadoNuevoId = (int) $datos['catalogo_estado_solicitud_id'];
            $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
            $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');

            $updates = [
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
                'motivo_respuesta' => $datos['motivo'] ?? null,
                'respondida_por_id' => $usuario->id,
                'respondida_at' => now(),
            ];

            $esError = $idIncorrecta !== null && $estadoNuevoId === $idIncorrecta;
            $esAprobacion = $idRespondida !== null && $estadoNuevoId === $idRespondida;

            if ($esError) {
                $updates['motivo_incorrecta'] = 'error_reportado';
                $updates['campos_incorrectos'] = CamposIncorrectosFactura::filtrar($datos['campos_incorrectos'] ?? []);
            } else {
                $updates['motivo_incorrecta'] = null;
                $updates['campos_incorrectos'] = null;
            }

            $enlaceUrl = null;
            if ($esError && ! empty($datos['generar_enlace_fiscal'])) {
                $fiscales = CamposIncorrectosFactura::soloFiscales($updates['campos_incorrectos'] ?? []);
                if ($fiscales !== []) {
                    $resultado = $this->generarEnlace->ejecutar($solicitud, [
                        'accion' => EnlaceDatosFiscales::ACCION_ACTUALIZAR,
                        'campos' => $fiscales,
                        'usuario_id' => $usuario->id,
                    ]);
                    $enlaceUrl = $resultado['url'];
                }
            }

            if ($esAprobacion && ! empty($datos['factura_pdfs'])) {
                foreach ($solicitud->pdfsEmitidos as $pdf) {
                    FacturaStorage::delete($pdf->path);
                    $pdf->delete();
                }
                $orden = 0;
                foreach ($datos['factura_pdfs'] as $pdf) {
                    if (! $pdf instanceof UploadedFile || ! $pdf->isValid()) {
                        continue;
                    }
                    $solicitud->pdfsEmitidos()->create([
                        'path' => $pdf->store("facturas/emitidas/{$solicitud->id}", FacturaStorage::storeDisk()),
                        'nombre_original' => $pdf->getClientOriginalName(),
                        'mime' => $pdf->getMimeType() ?: 'application/pdf',
                        'orden' => ++$orden,
                    ]);
                }
            }

            if (isset($datos['factura_xml']) && $datos['factura_xml'] instanceof UploadedFile && $datos['factura_xml']->isValid()) {
                FacturaStorage::delete($solicitud->factura_xml_path);
                $updates['factura_xml_path'] = $datos['factura_xml']->store("facturas/emitidas/{$solicitud->id}", FacturaStorage::storeDisk());
                $updates['factura_xml_nombre'] = $datos['factura_xml']->getClientOriginalName();
            }

            if (isset($datos['evidencia_error']) && $datos['evidencia_error'] instanceof UploadedFile && $datos['evidencia_error']->isValid()) {
                FacturaStorage::delete($solicitud->evidencia_error_path);
                $updates['evidencia_error_path'] = $datos['evidencia_error']->store("facturas/evidencias/{$solicitud->id}", FacturaStorage::storeDisk());
            }

            $solicitud->update($updates);

            $pdfsCount = $esAprobacion ? $solicitud->pdfsEmitidos()->count() : $solicitud->pdfsEmitidos()->count();

            AuditoriaSolicitudFactura::create([
                'solicitud_factura_id' => $solicitud->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $datos['motivo'] ?? 'Cambio de estado',
                'datos_snapshot' => [
                    'tiene_pdf' => $pdfsCount > 0,
                    'pdfs_count' => $pdfsCount,
                    'tiene_xml' => ! empty($updates['factura_xml_path'] ?? $solicitud->factura_xml_path),
                    'campos_incorrectos' => $updates['campos_incorrectos'] ?? null,
                    'enlace_fiscal_url' => $enlaceUrl,
                ],
            ]);

            if ($solicitud->vendedor) {
                $tipo = $esError ? 'rechazada' : 'respondida';
                $mensaje = $esError
                    ? 'Se reportó un error en tu solicitud de factura.'
                    : 'Tu solicitud de factura fue procesada. Revisa los archivos adjuntos.';

                if ($esError && ! empty($updates['campos_incorrectos'])) {
                    $etiquetas = array_map(
                        fn (string $c) => CamposIncorrectosFactura::etiqueta($c),
                        $updates['campos_incorrectos']
                    );
                    $mensaje .= ' Campos: '.implode(', ', $etiquetas).'.';
                }

                $solicitud->vendedor->notify(new AlertaFactura($solicitud, $tipo, $mensaje));
            }

            return $solicitud->fresh(['vendedor', 'estado', 'vouchers', 'pdfsEmitidos', 'respondidaPor']);
        });
    }
}
