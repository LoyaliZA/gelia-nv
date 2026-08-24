<?php

namespace App\Console\Commands;

use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnosticarEnviosCajasPedidoBmaCommand extends Command
{
    protected $signature = 'control-pedidos:diagnosticar-envios-cajas
                            {--reparar-docs : Vincular pedido_bma_caja_id cuando relacion_id aún existe}';

    protected $description = 'Reporta documentos huérfanos de envío/caja y cajas sin uuid_operativo';

    public function handle(): int
    {
        if (! Schema::hasTable('pedido_bma_cajas')) {
            $this->error('No existe pedido_bma_cajas.');

            return self::FAILURE;
        }

        $sinUuid = DB::table('pedido_bma_cajas')->whereNull('uuid_operativo')->count();
        $retiradas = DB::table('pedido_bma_cajas')->where('estado_operativo', 'retirada')->count();
        $activas = DB::table('pedido_bma_cajas')
            ->where(function ($q) {
                $q->where('estado_operativo', 'activa')->orWhereNull('estado_operativo');
            })
            ->count();

        $docsEnvio = PedidoBmaDocumento::query()
            ->where('relacion_tipo', PedidoBmaDocumento::RELACION_ENVIO_CAJA)
            ->whereNotNull('relacion_id');
        $huerfanos = (clone $docsEnvio)
            ->where(function ($q) {
                $q->whereNull('pedido_bma_caja_id')
                    ->orWhereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('pedido_bma_cajas')
                            ->whereColumn('pedido_bma_cajas.id', 'pedido_bma_documentos.relacion_id');
                    });
            })
            ->count();

        $sinFk = (clone $docsEnvio)->whereNull('pedido_bma_caja_id')->count();

        $this->info("Cajas activas: {$activas}");
        $this->info("Cajas retiradas: {$retiradas}");
        $this->info("Cajas sin uuid_operativo: {$sinUuid}");
        $this->info("Documentos envio_caja sin FK: {$sinFk}");
        $this->info("Documentos envio_caja huérfanos (caja inexistente o sin FK): {$huerfanos}");

        if ($this->option('reparar-docs')) {
            $reparados = 0;
            PedidoBmaDocumento::query()
                ->where('relacion_tipo', PedidoBmaDocumento::RELACION_ENVIO_CAJA)
                ->whereNotNull('relacion_id')
                ->whereNull('pedido_bma_caja_id')
                ->orderBy('id')
                ->chunkById(100, function ($docs) use (&$reparados) {
                    foreach ($docs as $doc) {
                        $existe = DB::table('pedido_bma_cajas')->where('id', $doc->relacion_id)->exists();
                        if (! $existe) {
                            continue;
                        }
                        $doc->update(['pedido_bma_caja_id' => $doc->relacion_id]);
                        $reparados++;
                    }
                });
            $this->info("Documentos reparados: {$reparados}");
        }

        return self::SUCCESS;
    }
}
