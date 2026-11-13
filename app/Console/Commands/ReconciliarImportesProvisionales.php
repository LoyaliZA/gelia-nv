<?php

namespace App\Console\Commands;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\HistorialMontoCliente;
use App\Models\ImporteProvisionalSolicitud;
use App\Models\SolicitudTag;
use App\Services\Clientes\RegistrarHistorialMontoClienteService;
use App\Services\Solicitudes\CrearSolicitudService;
use App\Services\Solicitudes\ImporteProvisionalSolicitudService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/** En seco por defecto. --aplicar no inserta solicitudes inciertas. */
class ReconciliarImportesProvisionales extends Command
{
    protected $signature = 'solicitudes:reconciliar-importes-provisionales {--aplicar : Escribe solo las filas ciertas}';

    protected $description = 'Reconstruye provisionales de solicitudes ya respondidas, sin inventar los casos inciertos.';

    public function handle(ImporteProvisionalSolicitudService $importes): int
    {
        $estados = array_values(array_filter([
            CatalogoEstadoSolicitud::idDe('Respondida'),
            CatalogoEstadoSolicitud::idDe('Verificada'),
        ]));
        $aplicar = (bool) $this->option('aplicar');
        $ciertas = 0;
        $omitidas = 0;
        $inciertas = 0;

        SolicitudTag::query()
            ->with('proceso')
            ->whereIn('catalogo_estado_solicitud_id', $estados ?: [0])
            ->orderBy('id')
            ->each(function (SolicitudTag $solicitud) use ($importes, $aplicar, &$ciertas, &$omitidas, &$inciertas) {
                if (ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $solicitud->id)->exists()) {
                    $omitidas++;

                    return;
                }

                $importe = $solicitud->pago_confirmado
                    ? (string) $solicitud->monto_aplicado_al_cliente
                    : (string) $solicitud->monto_cotizado;
                $sinImporte = $solicitud->proceso?->esOperativo()
                    || CrearSolicitudService::esFlujoTienda($solicitud)
                    || bccomp(bcadd($importe ?: '0', '0', 2), '0', 2) <= 0;
                if ($sinImporte) {
                    $omitidas++;

                    return;
                }

                $ultimaCarga = HistorialMontoCliente::query()
                    ->where('cliente_id', $solicitud->cliente_id)
                    ->where('origen', RegistrarHistorialMontoClienteService::ORIGEN_CARGA_MASIVA)
                    ->max('created_at');
                if ($ultimaCarga === null || Carbon::parse($ultimaCarga)->greaterThanOrEqualTo($solicitud->created_at)) {
                    $inciertas++;
                    $this->line("incierta {$solicitud->id}");

                    return;
                }

                $ciertas++;
                if (! $aplicar) {
                    return;
                }
                $importes->registrarInicial(
                    $solicitud,
                    $importe,
                    $solicitud->pago_confirmado
                        ? ImporteProvisionalSolicitud::TIPO_PAGO
                        : ImporteProvisionalSolicitud::TIPO_COTIZACION,
                );
            });

        $this->info($aplicar
            ? "Aplicadas {$ciertas}. Omitidas {$omitidas}. Inciertas {$inciertas}."
            : "En seco: ciertas {$ciertas}. Omitidas {$omitidas}. Inciertas {$inciertas}.");

        return self::SUCCESS;
    }
}
