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
        'pedido_guia_retraso' => 'Retraso por corrección de guía',
        'pedido_resguardo_apartado' => 'Resguardo apartado',
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
            'pedido_error_datos' => "Atención {$nombre}, se reportó un error de datos en el pedido {$folio}. No enviar hasta corregir.",
            'pedido_guia_retraso' => "Atención {$nombre}, la guía del pedido {$folio} fue corregida y provoca un retraso.",
            'pedido_resguardo_apartado' => "Atención {$nombre}, CEDIS apartó las piezas de tu pedido en resguardo {$folio}.",
            default => "{$nombre}, tienes una notificación sobre el pedido {$folio}.",
        };
    }
}
