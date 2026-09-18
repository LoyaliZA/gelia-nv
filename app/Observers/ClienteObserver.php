<?php

namespace App\Observers;

use App\Models\Cliente;
use App\Services\Mobile\MobileClienteAlcanceService;
use App\Services\Mobile\MobileSyncPublicationService;
use App\Services\Mobile\MobileSyncSuppression;

class ClienteObserver
{
    /** @var array<int, array<string, mixed>> */
    private static array $scopeAntes = [];

    public function __construct(
        protected MobileClienteAlcanceService $alcance,
        protected MobileSyncPublicationService $publicaciones
    ) {}

    public function created(Cliente $cliente): void
    {
        if (MobileSyncSuppression::activa()) {
            return;
        }

        $this->publicaciones->publicarCliente($cliente, null, true);
    }

    public function updating(Cliente $cliente): void
    {
        if (MobileSyncSuppression::activa()) {
            return;
        }

        self::$scopeAntes[spl_object_id($cliente)] = $this->alcance->snapshotDesdeOriginales($cliente);
    }

    public function updated(Cliente $cliente): void
    {
        if (MobileSyncSuppression::activa()) {
            return;
        }

        $oid = spl_object_id($cliente);
        $antes = self::$scopeAntes[$oid] ?? $this->alcance->snapshotDesdeOriginales($cliente);
        unset(self::$scopeAntes[$oid]);
        $this->publicaciones->publicarCliente($cliente, $antes);
    }

    public function deleted(Cliente $cliente): void
    {
        if (MobileSyncSuppression::activa()) {
            return;
        }

        $this->publicaciones->publicarCliente($cliente, $this->alcance->snapshotAlcance($cliente), false, true);
    }
}
