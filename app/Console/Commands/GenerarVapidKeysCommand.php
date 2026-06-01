<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerarVapidKeysCommand extends Command
{
    protected $signature = 'webpush:vapid {--show : Solo mostrar claves sin instrucciones .env}';

    protected $description = 'Genera un par de claves VAPID para Web Push (añádelas al .env)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('WEBPUSH_VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->newLine();
        $this->info('Añade estas líneas a tu archivo .env');
        $this->line('WEBPUSH_VAPID_SUBJECT=' . config('app.url', 'mailto:admin@example.com'));

        return self::SUCCESS;
    }
}
