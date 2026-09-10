<?php

namespace App\Console\Commands\Tiendanube;

use App\Services\Tiendanube\TiendanubeWebhookInboxService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tiendanube:recuperar-webhooks')]
#[Description('Reencola entregas de webhook pendientes o con arrendamiento vencido')]
class RecoverTiendanubeWebhookDeliveriesCommand extends Command
{
    public function handle(TiendanubeWebhookInboxService $inbox): int
    {
        $recuperadas = $inbox->recuperarPendientes();

        if ($recuperadas > 0) {
            $this->info("{$recuperadas} entrega(s) reencolada(s).");
        }

        return self::SUCCESS;
    }
}
