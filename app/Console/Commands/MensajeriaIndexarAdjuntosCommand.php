<?php

namespace App\Console\Commands;

use App\Models\MensajeAdjunto;
use App\Services\Mensajeria\IndexarTextoAdjuntoService;
use Illuminate\Console\Command;

class MensajeriaIndexarAdjuntosCommand extends Command
{
    protected $signature = 'mensajeria:indexar-adjuntos {--limite=500 : Máximo de adjuntos a procesar}';

    protected $description = 'Extrae texto de PDFs, Excel y documentos para el buscador de mensajería';

    public function handle(IndexarTextoAdjuntoService $indexar): int
    {
        $limite = max(1, (int) $this->option('limite'));

        if (!\Illuminate\Support\Facades\Schema::hasColumn('mensaje_adjuntos', 'contenido_indexado')) {
            $this->error('Ejecuta primero: php artisan migrate');

            return self::FAILURE;
        }

        $adjuntos = MensajeAdjunto::query()
            ->with('mensaje:id,tipo,contenido')
            ->where(function ($q) {
                $q->whereNull('contenido_indexado')
                    ->orWhere('contenido_indexado', '');
            })
            ->orderBy('id')
            ->limit($limite)
            ->get();

        if ($adjuntos->isEmpty()) {
            $this->info('No hay adjuntos pendientes de indexar.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($adjuntos->count());
        $bar->start();

        foreach ($adjuntos as $adjunto) {
            $indexar->ejecutar($adjunto);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Indexados {$adjuntos->count()} adjuntos.");

        return self::SUCCESS;
    }
}
