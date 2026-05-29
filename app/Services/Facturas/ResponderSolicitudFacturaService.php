<?php

namespace App\Services\Facturas;

use App\Models\AuditoriaSolicitudFactura;
use App\Models\SolicitudFactura;
use App\Models\User;
use App\Notifications\AlertaFactura;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ResponderSolicitudFacturaService
{
    public function ejecutar(SolicitudFactura $solicitud, array $datos, User $usuario): SolicitudFactura
    {
        return DB::transaction(function () use ($solicitud, $datos, $usuario) {
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
            $estadoNuevoId = (int) $datos['catalogo_estado_solicitud_id'];

            $updates = [
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
                'motivo_respuesta' => $datos['motivo'] ?? null,
                'respondida_por_id' => $usuario->id,
                'respondida_at' => now(),
            ];

            if ($estadoNuevoId === 4) {
                $updates['motivo_incorrecta'] = 'error_reportado';
            } else {
                $updates['motivo_incorrecta'] = null;
            }

            if (isset($datos['factura_pdf']) && $datos['factura_pdf'] instanceof UploadedFile && $datos['factura_pdf']->isValid()) {
                if ($solicitud->factura_pdf_path) {
                    Storage::disk('public')->delete($solicitud->factura_pdf_path);
                }
                $updates['factura_pdf_path'] = $datos['factura_pdf']->store("facturas/emitidas/{$solicitud->id}", 'public');
                $updates['factura_pdf_nombre'] = $datos['factura_pdf']->getClientOriginalName();
            }

            if (isset($datos['factura_xml']) && $datos['factura_xml'] instanceof UploadedFile && $datos['factura_xml']->isValid()) {
                if ($solicitud->factura_xml_path) {
                    Storage::disk('public')->delete($solicitud->factura_xml_path);
                }
                $updates['factura_xml_path'] = $datos['factura_xml']->store("facturas/emitidas/{$solicitud->id}", 'public');
                $updates['factura_xml_nombre'] = $datos['factura_xml']->getClientOriginalName();
            }

            if (isset($datos['evidencia_error']) && $datos['evidencia_error'] instanceof UploadedFile && $datos['evidencia_error']->isValid()) {
                if ($solicitud->evidencia_error_path) {
                    Storage::disk('public')->delete($solicitud->evidencia_error_path);
                }
                $updates['evidencia_error_path'] = $datos['evidencia_error']->store("facturas/evidencias/{$solicitud->id}", 'public');
            }

            $solicitud->update($updates);

            AuditoriaSolicitudFactura::create([
                'solicitud_factura_id' => $solicitud->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $datos['motivo'] ?? 'Cambio de estado',
                'datos_snapshot' => [
                    'tiene_pdf' => !empty($updates['factura_pdf_path'] ?? $solicitud->factura_pdf_path),
                    'tiene_xml' => !empty($updates['factura_xml_path'] ?? $solicitud->factura_xml_path),
                ],
            ]);

            if ($solicitud->vendedor) {
                $tipo = $estadoNuevoId === 4 ? 'rechazada' : 'respondida';
                $mensaje = $estadoNuevoId === 4
                    ? 'Se reportó un error en tu solicitud de factura.'
                    : 'Tu solicitud de factura fue procesada. Revisa los archivos adjuntos.';

                $solicitud->vendedor->notify(new AlertaFactura($solicitud, $tipo, $mensaje));
            }

            return $solicitud->fresh(['vendedor', 'estado', 'vouchers', 'respondidaPor']);
        });
    }
}
