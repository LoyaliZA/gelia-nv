<?php

namespace App\Console\Commands;

use App\Jobs\Solicitudes\RecuperarAplicacionClienteJob;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTag;
use Illuminate\Console\Command;

class RecuperarAplicacionesClienteCommand extends Command
{
    protected $signature = 'clientes:recuperar-aplicaciones';

    protected $description = 'Reintenta aplicaciones autorizadas que no se escribieron en el cliente.';

    public function handle(): int
    {
        $estados = array_filter([
            CatalogoEstadoSolicitud::idDe('Respondida'),
            CatalogoEstadoSolicitud::idDe('Verificada'),
        ]);
        if ($estados === []) {
            return self::SUCCESS;
        }
        SolicitudTag::query()
            ->whereIn('aplicacion_cliente', ['pendiente', 'fallida'])
            ->whereIn('catalogo_estado_solicitud_id', $estados)
            ->where('aplicacion_intentos', '<', 5)
            ->where(function ($consulta) {
                $consulta->whereNull('motivo_incorrecta')->orWhere('motivo_incorrecta', '!=', 'vencimiento_pago');
            })
            ->orderBy('id')
            ->limit(50)
            ->pluck('id')
            ->each(fn (int $id) => RecuperarAplicacionClienteJob::dispatchSync($id));

        return self::SUCCESS;
    }
}
