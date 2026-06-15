<?php

namespace App\Mail;

use App\Models\Woocommerce\WoocommerceSyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WooCommerceSyncFalloMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WoocommerceSyncLog $log) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Alerta: Sincronización WooCommerce Fallida #' . $this->log->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.woocommerce_sync_fallo',
        );
    }
}
