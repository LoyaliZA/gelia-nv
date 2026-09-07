<?php

namespace App\Notifications;

use App\Models\PuntoVenta\ReportePdvExportacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportePdvExportacionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ReportePdvExportacion $exportacion,
        public bool $exitoso,
    ) {}

    public function via(object $notifiable): array
    {
        if (! ($notifiable instanceof User)) {
            return [];
        }

        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $export = $this->exportacion;

        if ($this->exitoso) {
            return [
                'modulo' => 'punto_venta',
                'tipo' => 'reporte_pdv_exportacion_listo',
                'titulo' => 'Exportación lista',
                'mensaje' => "«{$export->titulo}» está listo para descargar.",
                'mensaje_visible' => "«{$export->titulo}» está listo para descargar.",
                'exportacion_id' => $export->id,
                'accion' => 'descargar',
                'url' => route('punto_venta.reportes.exportaciones.descargar', ['exportacion' => $export->id], false),
                'fecha' => now()->toDateTimeString(),
            ];
        }

        return [
            'modulo' => 'punto_venta',
            'tipo' => 'reporte_pdv_exportacion_fallo',
            'titulo' => 'Error al exportar',
            'mensaje' => "No se pudo generar «{$export->titulo}».",
            'mensaje_visible' => $export->error ?: "No se pudo generar «{$export->titulo}».",
            'exportacion_id' => $export->id,
            'accion' => 'reintentar',
            'url' => '/punto-venta/reportes',
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
