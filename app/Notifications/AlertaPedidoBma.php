<?php

namespace App\Notifications;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertaPedidoBma extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public PedidoBma $pedido;

    public string $tipoAlerta;

    public string $mensaje;

    public string $titulo;

    public string $mensajeVisible;

    public array $extras;

    private string $mensajeBase;

    private const ETIQUETAS_TIPO = [
        'pedido_error_datos' => 'Error de datos en pedido',
        'pedido_error_remision' => 'Error de remisión en pedido',
        'pedido_error_guia' => 'Error de guía en pedido',
        'pedido_error_cedis' => 'Error CEDIS en pedido',
        'pedido_error_estado' => 'Estado de error en pedido',
        'pedido_guia_retraso' => 'Retraso por corrección de guía',
        'pedido_resguardo_apartado' => 'Resguardo apartado',
        'pedido_consulta_pesaje' => 'Consulta de pesaje',
        'pedido_pesaje_listo' => 'Pesaje listo',
        'pedido_pendiente_auxiliar' => 'Pedido pendiente de auditoría',
        'pedido_aprobado' => 'Pedido aprobado',
        'pedido_rechazado_auxiliar' => 'Pedido rechazado',
        'pedido_incidencia_cedis' => 'Error de empaque',
        'pedido_pendiente_guia' => 'Pedido pendiente de guía',
        'pedido_pendiente_envio' => 'Pedido pendiente de recolección',
        'pedido_guia_asignada' => 'Guía asignada',
        'pedido_enviado' => 'Paquetería recogió el pedido',
        'pedido_resguardo_liberado' => 'Resguardo liberado',
        'pedido_sin_existencia' => 'Producto sin existencias',
    ];

    public function __construct(PedidoBma $pedido, string $tipoAlerta, string $mensaje, array $extras = [])
    {
        $this->pedido = $pedido->loadMissing(['cliente', 'vendedor', 'estatus']);
        $this->tipoAlerta = $tipoAlerta;
        $this->extras = $extras;
        $this->mensajeBase = $mensaje;
        $this->titulo = $this->construirTitulo();
        $this->mensajeVisible = $this->construirMensajeVisible();
        $this->mensaje = $this->mensajeVisible;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (config('alertas.enviar_correo', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->extras['url'] ?? url('/control-pedidos');

        return (new MailMessage)
            ->subject("GELIA · Control de pedidos · {$this->folio()}")
            ->greeting('Hola, '.$notifiable->name.'!')
            ->line($this->titulo)
            ->line($this->mensajeVisible)
            ->action('Ver en el ERP', $url);
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
            'pedido_bma_id' => $this->pedido->id,
            'folio' => $this->folio(),
            'tipo' => $this->tipoAlerta,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'proceso' => 'Control de pedidos',
            'vendedora' => $this->pedido->vendedor->name ?? null,
            'fecha' => now()->toDateTimeString(),
            'url' => $this->extras['url'] ?? '/control-pedidos',
            'modulo' => 'control_pedidos',
        ], $this->extras);
    }

    private function folio(): string
    {
        return $this->pedido->folio_remision
            ?: $this->pedido->folio
            ?: 'PED-'.$this->pedido->id;
    }

    private function construirTitulo(): string
    {
        if ($this->tipoAlerta === 'pedido_sin_existencia') {
            return 'Sin existencias: '.$this->folio();
        }

        if ($this->tipoAlerta === 'pedido_pesaje_listo' && ! empty($this->extras['con_observaciones_fisicas'])) {
            return 'Pesaje con observaciones: '.$this->folio();
        }

        $etiqueta = self::ETIQUETAS_TIPO[$this->tipoAlerta] ?? 'Notificación de pedido';

        return "{$etiqueta}: {$this->folio()}";
    }

    private function construirMensajeVisible(): string
    {
        return "{$this->mensajeBase} · {$this->folio()}";
    }

    private function construirMensajeVoz(object $notifiable): string
    {
        $nombre = explode(' ', trim($notifiable->name ?? 'Usuario'))[0];
        $folio = $this->folio();

        return match ($this->tipoAlerta) {
            'pedido_error_datos' => "Atención {$nombre}, se reportó un error de datos en el pedido {$folio}. Corrígelo y reenvía.",
            'pedido_error_remision' => "Atención {$nombre}, hay un error de remisión en el pedido {$folio}. Corrígelo antes de aprobar.",
            'pedido_error_guia' => "Atención {$nombre}, hay un error grave de guía en el pedido {$folio}. No enviar hasta corregir.",
            'pedido_error_cedis' => "Atención {$nombre}, hay un error CEDIS en el pedido {$folio}. Corrígelo en empaque o pesaje.",
            'pedido_error_estado' => "Atención {$nombre}, se reportó un error en el pedido {$folio}. Solo el responsable puede corregirlo.",
            'pedido_guia_retraso' => "Atención {$nombre}, la guía del pedido {$folio} fue corregida y provoca un retraso.",
            'pedido_resguardo_apartado' => "Atención {$nombre}, CEDIS apartó las piezas de tu pedido en resguardo {$folio}.",
            'pedido_consulta_pesaje' => "Atención {$nombre}, hay una consulta de pesaje pendiente para el pedido {$folio}.",
            'pedido_pesaje_listo' => ! empty($this->extras['con_observaciones_fisicas'])
                ? "Atención {$nombre}, CEDIS respondió el pesaje del pedido {$folio} con observaciones. Revísalas antes de cotizar el envío."
                : "Atención {$nombre}, CEDIS respondió el pesaje del pedido {$folio}. Ya puedes cotizar el envío.",
            'pedido_pendiente_auxiliar' => "Atención {$nombre}, el pedido {$folio} está pendiente de auditoría.",
            'pedido_aprobado' => "Atención {$nombre}, el pedido {$folio} fue aprobado.",
            'pedido_rechazado_auxiliar' => "Atención {$nombre}, tu pedido {$folio} fue rechazado, corrígelo y reenvía.",
            'pedido_incidencia_cedis' => "Atención {$nombre}, hay un error de empaque en el pedido {$folio}.",
            'pedido_pendiente_guia' => "Atención {$nombre}, el pedido {$folio} está pendiente de guía.",
            'pedido_pendiente_envio' => "Atención {$nombre}, el pedido {$folio} está empacado, pendiente de recolección.",
            'pedido_guia_asignada' => "Atención {$nombre}, se asignó guía al pedido {$folio}, pendiente de recolección.",
            'pedido_enviado' => "Atención {$nombre}, la paquetería recogió el pedido {$folio}.",
            'pedido_resguardo_liberado' => "Atención {$nombre}, se liberó el resguardo del pedido {$folio}, listo para CEDIS.",
            'pedido_sin_existencia' => "Atención {$nombre}, CEDIS reportó producto sin existencias en el pedido {$folio}. El pedido está detenido hasta que elijas una acción.",
            default => "{$nombre}, tienes una notificación sobre el pedido {$folio}.",
        };
    }
}
