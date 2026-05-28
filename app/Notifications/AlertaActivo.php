<?php

namespace App\Notifications;

use App\Models\Activo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AlertaActivo extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public Activo $activo;

    public string $tipoAlerta;

    public string $mensaje;

    public string $titulo;

    public string $mensajeVisible;

    public array $extras;

    private const ETIQUETAS_TIPO = [
        'activo_asignado' => 'Activo asignado',
        'activo_devuelto' => 'Activo devuelto',
        'activo_transferido' => 'Activo transferido',
        'activo_mantenimiento' => 'Mantenimiento de activo',
        'activo_baja' => 'Activo dado de baja',
        'activo_vencimiento' => 'Vencimiento de activo',
        'activo_mantenimiento_proximo' => 'Mantenimiento próximo',
        'resumen_activos' => 'Resumen de activos',
    ];

    public function __construct(Activo $activo, string $tipoAlerta, string $mensaje, array $extras = [])
    {
        $this->activo = $activo->loadMissing(['tipo', 'departamento', 'responsable']);
        $this->tipoAlerta = $tipoAlerta;
        $this->mensaje = $mensaje;
        $this->extras = $extras;
        $this->titulo = $this->construirTitulo();
        $this->mensajeVisible = $this->construirMensajeVisible();
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (config('alertas.enviar_correo', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->construirPayload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge(
            $this->construirPayload(),
            ['mensaje_voz' => $this->construirMensajeVoz($notifiable)]
        ));
    }

    private function construirPayload(): array
    {
        return array_merge([
            'activo_id' => $this->activo->id,
            'folio' => $this->activo->folio,
            'tipo' => $this->tipoAlerta,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'modulo' => 'activos',
            'nombre_activo' => $this->activo->nombre,
            'tipo_activo' => $this->activo->tipo?->nombre,
            'departamento' => $this->activo->departamento?->nombre,
            'responsable' => $this->activo->responsable?->name,
            'fecha' => now()->toDateTimeString(),
        ], $this->extras);
    }

    private function construirTitulo(): string
    {
        $etiqueta = self::ETIQUETAS_TIPO[$this->tipoAlerta] ?? 'Notificación de activo';

        return "{$etiqueta}: {$this->activo->nombre}";
    }

    private function construirMensajeVisible(): string
    {
        return "{$this->mensaje} · {$this->activo->folio}";
    }

    private function construirMensajeVoz(object $notifiable): string
    {
        $nombre = explode(' ', trim($notifiable->name))[0];
        $folio = $this->activo->folio;
        $nombreActivo = $this->activo->nombre;

        return match ($this->tipoAlerta) {
            'activo_asignado' => "Atención {$nombre}, se te asignó el activo {$nombreActivo}, {$folio}.",
            'activo_devuelto' => "Atención {$nombre}, el activo {$nombreActivo}, {$folio}, fue devuelto al inventario.",
            'activo_transferido' => "Atención {$nombre}, el activo {$nombreActivo}, {$folio}, fue transferido de departamento.",
            'activo_mantenimiento' => "Atención {$nombre}, el activo {$nombreActivo}, {$folio}, entró en mantenimiento.",
            'activo_baja' => "Atención {$nombre}, el activo {$nombreActivo}, {$folio}, fue dado de baja.",
            'activo_vencimiento' => "Atención {$nombre}, el activo {$nombreActivo}, {$folio}, está por vencer o venció.",
            'activo_mantenimiento_proximo' => "Atención {$nombre}, el activo {$nombreActivo}, {$folio}, requiere mantenimiento próximo.",
            'resumen_activos' => "Atención {$nombre}, hay un resumen de alertas de activos pendientes de revisión.",
            default => "Atención {$nombre}, tienes una notificación sobre el activo {$nombreActivo}, {$folio}.",
        };
    }
}
