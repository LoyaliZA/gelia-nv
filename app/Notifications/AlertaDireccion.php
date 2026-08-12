<?php

namespace App\Notifications;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertaDireccion extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public Cliente $cliente;

    public ClienteDireccion $direccion;

    public string $tipoAlerta;

    public string $mensaje;

    public string $titulo;

    public string $mensajeVisible;

    private const ETIQUETAS = [
        'direccion_formulario_respondido' => 'Formulario de dirección respondido',
    ];

    public function __construct(Cliente $cliente, ClienteDireccion $direccion, string $tipoAlerta, string $mensaje)
    {
        $this->cliente = $cliente;
        $this->direccion = $direccion;
        $this->tipoAlerta = $tipoAlerta;
        $this->mensaje = $mensaje;
        $numero = $cliente->numero_cliente ?: (string) $cliente->id;
        $this->titulo = (self::ETIQUETAS[$tipoAlerta] ?? 'Notificación de dirección').' · '.$numero;
        $this->mensajeVisible = "{$mensaje} · Cliente {$numero}";
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
        return (new MailMessage)
            ->subject("GELIA · Dirección · {$this->cliente->numero_cliente}")
            ->greeting('Hola, '.$notifiable->name.'!')
            ->line($this->titulo)
            ->line($this->mensajeVisible)
            ->action('Ver direcciones del cliente', $this->urlFicha());
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge(
            $this->payload(),
            ['mensaje_voz' => $this->construirMensajeVoz($notifiable)]
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'cliente_id' => $this->cliente->id,
            'cliente_direccion_id' => $this->direccion->id,
            'numero_cliente' => $this->cliente->numero_cliente,
            'tipo' => $this->tipoAlerta,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'fecha' => now()->toDateTimeString(),
            'modulo' => 'direcciones',
            'url' => '/control-pedidos/direcciones/cliente/'.$this->cliente->id,
        ];
    }

    private function urlFicha(): string
    {
        return url('/control-pedidos/direcciones/cliente/'.$this->cliente->id);
    }

    private function construirMensajeVoz(object $notifiable): string
    {
        $nombre = explode(' ', trim($notifiable->name))[0];
        $numero = $this->cliente->numero_cliente ?: 'cliente';

        return match ($this->tipoAlerta) {
            'direccion_formulario_respondido' => "{$nombre}, el cliente {$numero} llenó el formulario de dirección de envío.",
            default => "{$nombre}, tienes una notificación sobre una dirección de envío.",
        };
    }
}
