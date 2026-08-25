<?php

namespace App\Console\Commands\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaTareaHistorial;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecordatorioVencimientoPreparacionTiendaCommand extends Command
{
    protected $signature = 'control-pedidos:recordatorio-vencimiento-preparacion-tienda';

    protected $description = 'Notifica vencimiento próximo de resguardo en preparación Tienda (transferencia). No cancela ni libera.';

    public function handle(PreparacionTiendaConfig $config, NotificarPedidoBmaService $notificar): int
    {
        if (! $config->activo()) {
            $this->info('Preparación Tienda desactivada; omitiendo recordatorios.');

            return self::SUCCESS;
        }

        $zona = $config->zonaHoraria();
        $hoy = Carbon::now($zona)->startOfDay();
        $manana = $hoy->copy()->addDay()->endOfDay();

        $tareas = PedidoBmaTareaPreparacion::query()
            ->with(['pedido.cliente', 'pedido.vendedor', 'modalidad'])
            ->where('estado', PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA)
            ->whereNotNull('fecha_limite')
            ->whereBetween('fecha_limite', [$hoy, $manana])
            ->whereHas('modalidad', fn ($q) => $q->where('codigo', CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA))
            ->get();

        $enviados = 0;

        foreach ($tareas as $tarea) {
            if ($this->yaNotificadoHoy($tarea, $zona)) {
                continue;
            }

            $pedido = $tarea->pedido;
            if (! $pedido) {
                continue;
            }

            $folio = $pedido->folio_remision ?: $pedido->folio ?: $pedido->id;
            $fecha = $tarea->fecha_limite?->timezone($zona)->format('d/m/Y H:i') ?? '—';
            $mensaje = "El resguardo del pedido {$folio} vence el {$fecha}. Coordine recolección o liberación con el cliente.";

            $notificar->ejecutar(
                $pedido,
                'pedido_preparacion_tienda_vencimiento',
                $mensaje,
                ['control_pedidos.tienda.ver'],
                null,
                true,
                ['url' => '/control-pedidos/tienda?tarea='.$tarea->id]
            );

            PedidoBmaTareaHistorial::query()->create([
                'pedido_bma_tarea_preparacion_id' => $tarea->id,
                'usuario_id' => $pedido->vendedor_id ?? $pedido->created_by ?? 1,
                'estado_anterior' => $tarea->estado,
                'estado_nuevo' => $tarea->estado,
                'accion' => 'recordatorio_vencimiento',
                'comentario' => $mensaje,
                'meta_json' => ['fecha_limite' => $tarea->fecha_limite?->toIso8601String()],
            ]);

            $enviados++;
        }

        $this->info("Recordatorios enviados: {$enviados}");

        return self::SUCCESS;
    }

    private function yaNotificadoHoy(PedidoBmaTareaPreparacion $tarea, string $zona): bool
    {
        $inicio = Carbon::now($zona)->startOfDay();
        $fin = Carbon::now($zona)->endOfDay();

        return PedidoBmaTareaHistorial::query()
            ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
            ->where('accion', 'recordatorio_vencimiento')
            ->whereBetween('created_at', [$inicio, $fin])
            ->exists();
    }
}
