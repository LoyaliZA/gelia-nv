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

    private const TIPOS_ACTOR_CEDIS = [
        'pedido_pesaje_listo',
        'pedido_sin_existencia',
        'pedido_resguardo_apartado',
        'pedido_incidencia_cedis',
    ];

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
        'pedido_preparacion_tienda_nueva' => 'Nueva preparación en Tienda',
        'pedido_preparacion_tienda_respondida' => 'Preparación en Tienda respondida',
        'pedido_preparacion_tienda_incidencia' => 'Incidencia en preparación Tienda',
        'pedido_preparacion_tienda_corregida' => 'Preparación Tienda corregida',
        'pedido_preparacion_tienda_liberada' => 'Mercancía liberada en Tienda',
        'pedido_preparacion_tienda_vencimiento' => 'Vencimiento de resguardo en Tienda',
        'pedido_preparacion_tienda_lista_traslado' => 'Lista para traslado a CEDIS',
        'pedido_preparacion_tienda_en_traslado' => 'Mercancía en traslado a CEDIS',
        'pedido_preparacion_tienda_recibida_cedis' => 'CEDIS recibió traslado de Tienda',
        'pedido_preparacion_tienda_rechazada_cedis' => 'CEDIS rechazó traslado de Tienda',
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

    private function nombreActorCedis(): string
    {
        $nombre = trim((string) ($this->extras['actor_nombre'] ?? ''));

        return $nombre !== '' ? $nombre : 'CEDIS';
    }

    private function construirMensajeVisible(): string
    {
        $base = $this->mensajeBase;
        if (in_array($this->tipoAlerta, self::TIPOS_ACTOR_CEDIS, true)) {
            $actor = $this->nombreActorCedis();
            if (str_starts_with($base, 'CEDIS')) {
                $base = $actor.substr($base, strlen('CEDIS'));
            } elseif ($this->tipoAlerta === 'pedido_incidencia_cedis') {
                $base = "{$actor} reportó: {$base}";
            }
        }

        return "{$base} · {$this->folio()}";
    }

    private function construirMensajeVoz(object $notifiable): string
    {
        $nombre = explode(' ', trim($notifiable->name ?? 'Usuario'))[0];
        $folio = $this->folio();
        $actor = $this->nombreActorCedis();

        return match ($this->tipoAlerta) {
            'pedido_error_datos' => "Atención {$nombre}, se reportó un error de datos en el pedido {$folio}. Corrígelo y reenvía.",
            'pedido_error_remision' => "Atención {$nombre}, hay un error de remisión en el pedido {$folio}. Corrígelo antes de aprobar.",
            'pedido_error_guia' => "Atención {$nombre}, hay un error grave de guía en el pedido {$folio}. No enviar hasta corregir.",
            'pedido_error_cedis' => "Atención {$nombre}, hay un error CEDIS en el pedido {$folio}. Corrígelo en empaque o pesaje.",
            'pedido_error_estado' => "Atención {$nombre}, se reportó un error en el pedido {$folio}. Solo el responsable puede corregirlo.",
            'pedido_guia_retraso' => "Atención {$nombre}, la guía del pedido {$folio} fue corregida y provoca un retraso.",
            'pedido_resguardo_apartado' => "Atención {$nombre}, {$actor} apartó las piezas de tu pedido en resguardo {$folio}.",
            'pedido_consulta_pesaje' => "Atención {$nombre}, hay una consulta de pesaje pendiente para el pedido {$folio}.",
            'pedido_pesaje_listo' => ! empty($this->extras['con_observaciones_fisicas'])
                ? "Atención {$nombre}, {$actor} respondió el pesaje del pedido {$folio} con observaciones. Revísalas antes de cotizar el envío."
                : "Atención {$nombre}, {$actor} respondió el pesaje del pedido {$folio}. Ya puedes cotizar el envío.",
            'pedido_pendiente_auxiliar' => "Atención {$nombre}, el pedido {$folio} está pendiente de auditoría.",
            'pedido_aprobado' => "Atención {$nombre}, el pedido {$folio} fue aprobado.",
            'pedido_rechazado_auxiliar' => "Atención {$nombre}, tu pedido {$folio} fue rechazado, corrígelo y reenvía.",
            'pedido_incidencia_cedis' => "Atención {$nombre}, {$actor} reportó un error de empaque en el pedido {$folio}.",
            'pedido_pendiente_guia' => "Atención {$nombre}, el pedido {$folio} está pendiente de guía.",
            'pedido_pendiente_envio' => "Atención {$nombre}, el pedido {$folio} está empacado, pendiente de recolección.",
            'pedido_guia_asignada' => "Atención {$nombre}, se asignó guía al pedido {$folio}, pendiente de recolección.",
            'pedido_enviado' => "Atención {$nombre}, la paquetería recogió el pedido {$folio}.",
            'pedido_resguardo_liberado' => "Atención {$nombre}, se liberó el resguardo del pedido {$folio}, listo para CEDIS.",
            'pedido_sin_existencia' => "Atención {$nombre}, {$actor} reportó producto sin existencias en el pedido {$folio}. El pedido está detenido hasta que elijas una acción.",
            default => "{$nombre}, tienes una notificación sobre el pedido {$folio}.",
        };
    }
}
