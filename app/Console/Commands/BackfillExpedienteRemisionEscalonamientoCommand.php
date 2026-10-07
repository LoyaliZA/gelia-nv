<?php

namespace App\Console\Commands;

use App\Models\Escalonamiento\DocumentoVenta;
use App\Services\Escalonamiento\SincronizarExpedienteRemisionEscalonamiento;
use Illuminate\Console\Command;

class BackfillExpedienteRemisionEscalonamientoCommand extends Command
{
    protected $signature = 'escalonamiento:backfill-expediente-remision';

    protected $description = 'Rellena documento_venta_expediente_remision desde datos_fuente de documentos existentes.';

    public function handle(SincronizarExpedienteRemisionEscalonamiento $sincronizar): int
    {
        $procesados = 0;

        DocumentoVenta::query()
            ->whereNotNull('datos_fuente')
            ->orderBy('id')
            ->chunkById(200, function ($documentos) use ($sincronizar, &$procesados) {
                foreach ($documentos as $documento) {
                    $fuente = is_array($documento->datos_fuente) ? $documento->datos_fuente : [];
                    if ($fuente === []) {
                        continue;
                    }

                    $sincronizar->desdeDocumentoYDatosFuente($documento);
                    $procesados++;
                }
            });

        $this->info("Expedientes sincronizados: {$procesados}");

        return self::SUCCESS;
    }
}
