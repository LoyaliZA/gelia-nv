<?php

namespace App\Notifications;

use App\Models\BellaromaTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class PlantillaBellaromaGenerada extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public BellaromaTemplate $template;
    public string $titulo;
    public string $mensajeVisible;

    /**
     * Create a new notification instance.
     */
    public function __construct(BellaromaTemplate $template)
    {
        $this->template = $template;
        $this->titulo = 'Plantilla Pedidos Generada';
        $this->mensajeVisible = "Se ha generado una nueva plantilla de pedidos: {$template->nombre_archivo}";
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Nueva Plantilla de Pedidos Generada')
            ->greeting('¡Hola ' . $notifiable->name . '!')
            ->line('Se ha generado una nueva plantilla de Excel para pedidos.')
            ->line('Archivo: ' . $this->template->nombre_archivo)
            ->line('Tamaño: ' . $this->template->tamano_kb);

        if (Storage::disk('public')->exists($this->template->ruta_fisica)) {
            $mail->attach(Storage::disk('public')->path($this->template->ruta_fisica), [
                'as' => $this->template->nombre_archivo,
            ]);
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->construirPayload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge(
            $this->construirPayload(),
            ['mensaje_voz' => "Atención, se ha generado una nueva plantilla de pedidos llamada {$this->template->nombre_archivo}."]
        ));
    }

    private function construirPayload(): array
    {
        return [
            'modulo' => 'funciones',
            'tipo' => 'plantilla_bellaroma',
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'template_id' => $this->template->id,
            'nombre_archivo' => $this->template->nombre_archivo,
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
