<?php

namespace App\Notifications;

use App\Models\ListadoGenerado;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ListadoGeneradoNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public ListadoGenerado $listado;
    public string $titulo;
    public string $mensajeVisible;
    public ?string $nombreDestinatario;

    public function __construct(ListadoGenerado $listado, ?string $nombreDestinatario = null)
    {
        $this->listado = $listado;
        $this->nombreDestinatario = $nombreDestinatario;
        $this->titulo = 'Listado Generado';
        $this->mensajeVisible = "Se ha generado un nuevo listado: {$listado->nombre_archivo}";
    }

    public function via(object $notifiable): array
    {
        if (!($notifiable instanceof User)) {
            return ['mail'];
        }

        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nombre = $this->nombreDestinatario
            ?? ($notifiable->name ?? 'destinatario');

        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name', 'GELIA');

        $mail = (new MailMessage)
            ->subject('Nuevo Listado Generado')
            ->greeting('¡Hola ' . $nombre . '!')
            ->line('Se ha generado un nuevo archivo de listado operativo.')
            ->line('Archivo: ' . $this->listado->nombre_archivo)
            ->line('Tamaño: ' . ($this->listado->tamano_kb ?? 'N/D'));

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName);
        }

        if (Storage::disk('public')->exists($this->listado->ruta_fisica)) {
            $mail->attach(Storage::disk('public')->path($this->listado->ruta_fisica), [
                'as' => $this->listado->nombre_archivo,
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
            ['mensaje_voz' => "Atención, se ha generado un nuevo listado llamado {$this->listado->nombre_archivo}."]
        ));
    }

    private function construirPayload(): array
    {
        return [
            'modulo' => 'funciones',
            'tipo' => 'listado_generado',
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'listado_id' => $this->listado->id,
            'nombre_archivo' => $this->listado->nombre_archivo,
            'fecha' => now()->toDateTimeString(),
        ];
    }
}
