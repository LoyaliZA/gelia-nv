<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\SolicitudTag;

class AlertaSolicitud extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public SolicitudTag $solicitud;
    public string $tipoAlerta;
    public string $mensaje;
    public array $extras;
    public string $titulo;
    public string $mensajeVisible;

    private string $mensajeBase;

    private const ETIQUETAS_TIPO = [
        'nueva' => 'Nueva solicitud',
        'reparada' => 'Solicitud reparada',
        'rechazada' => 'Error reportado',
        'pago_rechazado' => 'Pago vencido',
        'pago_confirmado' => 'Pago confirmado',
        'actualizacion' => 'Actualización',
        'alerta_pago_insuficiente' => 'Pago insuficiente',
        'alerta_ascenso_lista' => 'Ascenso de lista',
        'consulta_nueva' => 'Consulta TAG/Lista',
        'consulta_respondida' => 'Consulta respondida',
        'rollback_confirmado' => 'Reversión confirmada',
        'cancelacion_solicitada' => 'Cancelación solicitada',
        'cancelada' => 'Solicitud cancelada',
    ];

    public function __construct(SolicitudTag $solicitud, string $tipoAlerta, string $mensaje, array $extras = [])
    {
        $this->solicitud = $solicitud->loadMissing([
            'cliente',
            'proceso',
            'estado',
            'vendedor',
            'listaDescuento',
            'banco',
        ]);
        $this->tipoAlerta = $tipoAlerta;
        $this->extras = $extras;
        $this->mensajeBase = $mensaje;
        $this->titulo = $this->construirTitulo();
        $this->mensajeVisible = $this->construirMensajeVisible();
        $this->mensaje = $this->enriquecerMensaje($mensaje);
    }

    public function via(object $notifiable): array
    {
        // database y broadcast primero: si el correo falla (SMTP), no debe bloquear alertas en vivo.
        $channels = ['database', 'broadcast'];

        if (config('alertas.enviar_correo', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/solicitudes?folio=' . $this->solicitud->id);
        $proceso = $this->nombreProceso();

        $mail = (new MailMessage)
            ->subject("GELIA · {$proceso} · FOL-{$this->solicitud->id}")
            ->greeting('Hola, ' . $notifiable->name . '!')
            ->line($this->titulo)
            ->line('**Tipo de solicitud:** ' . $proceso)
            ->line('**Detalle:** ' . $this->mensajeVisible)
            ->line('**Estado:** ' . ($this->solicitud->estado->nombre ?? 'N/A'))
            ->action('Ver solicitud en el ERP', $url);

        if (in_array($this->tipoAlerta, ['pago_rechazado', 'rechazada', 'alerta_pago_insuficiente', 'cancelada'], true)) {
            $mail->error();
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
            ['mensaje_voz' => $this->construirMensajeVoz($notifiable)]
        ));
    }

    private function construirPayload(): array
    {
        $proceso = $this->solicitud->proceso;

        return array_merge([
            'solicitud_id' => $this->solicitud->id,
            'tipo' => $this->tipoAlerta,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensajeVisible,
            'mensaje_visible' => $this->mensajeVisible,
            'proceso' => $proceso->nombre ?? null,
            'proceso_categoria' => $proceso->categoria_flujo ?? 'financiero',
            'estado' => $this->solicitud->estado->nombre ?? null,
            'vendedora' => $this->solicitud->vendedor->name ?? null,
            'fecha' => now()->toDateTimeString(),
        ], $this->extras);
    }

    private function nombreProceso(): string
    {
        return $this->solicitud->proceso->nombre ?? 'Solicitud';
    }

    private function construirTitulo(): string
    {
        $etiqueta = self::ETIQUETAS_TIPO[$this->tipoAlerta] ?? 'Notificación';
        return "{$etiqueta}: {$this->nombreProceso()}";
    }

    private function construirMensajeVisible(): string
    {
        return "{$this->mensajeBase} · FOL-{$this->solicitud->id}";
    }

    private function enriquecerMensaje(string $mensaje): string
    {
        $proceso = $this->nombreProceso();
        $folio = "FOL-{$this->solicitud->id}";

        if (stripos($mensaje, $proceso) !== false && stripos($mensaje, $folio) !== false) {
            return $mensaje;
        }

        return "{$mensaje} · {$proceso} · {$folio}";
    }

    private function construirMensajeVoz(object $notifiable): string
    {
        $nombreDestinatario = explode(' ', trim($notifiable->name))[0];
        $nombreVendedor = explode(' ', trim($this->solicitud->vendedor->name ?? 'un colaborador'))[0];
        $esVendedorOriginal = ($this->solicitud->vendedor_id === $notifiable->id);
        $proceso = $this->nombreProceso();
        $folio = "FOL-{$this->solicitud->id}";

        switch ($this->tipoAlerta) {
            case 'nueva':
                return "Atención {$nombreDestinatario}, {$nombreVendedor} envió una solicitud de {$proceso}, {$folio}.";
            case 'alerta_ascenso_lista':
                return "Atención {$nombreDestinatario}, el pago de {$nombreVendedor} en {$proceso} permite ascender al cliente de categoría, {$folio}.";
            case 'rechazada':
            case 'pago_rechazado':
                if ($esVendedorOriginal) {
                    return "{$nombreDestinatario}, se reportó un error en tu solicitud de {$proceso}, {$folio}. Revisa o inicia una nueva si el pago venció.";
                }
                return "{$nombreDestinatario}, {$nombreVendedor} recibió una observación en su solicitud de {$proceso}, {$folio}.";
            case 'reparada':
                return "Atención {$nombreDestinatario}, {$nombreVendedor} reparó su solicitud de {$proceso}, {$folio}, y está lista para revisión.";
            case 'pago_confirmado':
                return "{$nombreDestinatario}, {$nombreVendedor} confirmó el pago de la solicitud de {$proceso}, {$folio}.";
            case 'consulta_nueva':
                $temas = implode(' y ', $this->extras['consulta_temas'] ?? ['TAG o lista']);
                return "Atención {$nombreDestinatario}, {$nombreVendedor} consulta {$temas} en la solicitud de {$proceso}, {$folio}.";
            case 'consulta_respondida':
                $resultado = ($this->extras['respuesta_positiva'] ?? false) ? 'confirmada' : 'rechazada';
                return "{$nombreDestinatario}, tu consulta de {$proceso}, {$folio}, fue {$resultado}.";
            case 'rollback_confirmado':
                return "{$nombreDestinatario}, se confirmó la reversión de la solicitud de {$proceso}, {$folio}. Debes iniciar una nueva solicitud.";
            case 'cancelacion_solicitada':
                return "Atención {$nombreDestinatario}, {$nombreVendedor} solicita cancelar la solicitud de {$proceso}, {$folio}.";
            case 'cancelada':
                if ($esVendedorOriginal) {
                    return "{$nombreDestinatario}, tu solicitud de {$proceso}, {$folio}, fue cancelada.";
                }
                return "{$nombreDestinatario}, se confirmó la cancelación de la solicitud de {$proceso}, {$folio}.";
            case 'alerta_pago_insuficiente':
                return "Atención {$nombreDestinatario}, el pago de {$nombreVendedor} en {$proceso} es insuficiente para la lista solicitada, {$folio}.";
            case 'actualizacion':
                if ($esVendedorOriginal) {
                    return "{$nombreDestinatario}, el área administrativa respondió tu solicitud de {$proceso}, {$folio}.";
                }
                return "{$nombreDestinatario}, hay una actualización en la solicitud de {$proceso} de {$nombreVendedor}, {$folio}.";
            default:
                return "{$nombreDestinatario}, tienes una notificación sobre la solicitud de {$proceso}, {$folio}.";
        }
    }
}
