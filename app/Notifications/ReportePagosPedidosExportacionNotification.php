<?php

namespace App\Notifications;

use App\Models\Reportes\ReportePagosPedidosExportacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ReportePagosPedidosExportacionNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public ReportePagosPedidosExportacion $exportacion,
        public bool $exitoso,
    ) {}

    public function via(object $notifiable): array
    {
        if (! ($notifiable instanceof User)) {
            return [];
        }

        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->payload();
        if ($this->exitoso) {
            $data['mensaje_voz'] = "Su reporte {$this->exportacion->titulo} está listo para descargar.";
        }

        return new BroadcastMessage($data);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $export = $this->exportacion;

        if ($this->exitoso) {
            return [
                'modulo' => 'reportes',
                'tipo' => 'pagos_pedidos_exportacion_listo',
                'titulo' => 'Reporte listo',
                'mensaje' => "«{$export->titulo}» está listo para descargar.",
                'mensaje_visible' => "«{$export->titulo}» está listo para descargar.",
                'exportacion_id' => $export->id,
                'accion' => 'descargar',
                'url' => route('reportes.pagos_pedidos.exportar.descargar', ['exportacion' => $export->id], false),
                'fecha' => now()->toDateTimeString(),
            ];
        }

        return [
            'modulo' => 'reportes',
            'tipo' => 'pagos_pedidos_exportacion_fallo',
            'titulo' => 'Error al generar reporte',
            'mensaje' => "No se pudo generar «{$export->titulo}».",
            'mensaje_visible' => $export->error ?: "No se pudo generar «{$export->titulo}».",
            'exportacion_id' => $export->id,
            'accion' => 'reintentar',
            'url' => '/reportes/pagos-pedidos',
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
