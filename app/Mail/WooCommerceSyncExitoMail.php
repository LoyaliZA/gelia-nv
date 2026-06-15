<?php

namespace App\Mail;

use App\Models\Woocommerce\WoocommerceSyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WooCommerceSyncExitoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WoocommerceSyncLog $log,
        public string $csvPath
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sincronización WooCommerce Completada #' . $this->log->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.woocommerce_sync_exito',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->csvPath)
                ->as('auditoria-precios-' . $this->log->id . '.csv')
                ->withMime('text/csv'),
        ];
    }
}
