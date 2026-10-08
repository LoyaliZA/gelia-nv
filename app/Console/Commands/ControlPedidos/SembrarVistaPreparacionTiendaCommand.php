<?php

namespace App\Console\Commands\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\User;
use App\Services\ControlPedidos\AsegurarCumplimientoFisicoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SembrarVistaPreparacionTiendaCommand extends Command
{
    protected $signature = 'control-pedidos:sembrar-vista-tienda
                            {--dry-run : Solo muestra qué se crearía}';

    protected $description = 'Crea tareas de demostración (una por bandeja) para revisar la UI de Preparación Tienda. Solo entornos locales.';

    public function handle(AsegurarCumplimientoFisicoService $cumplimiento): int
    {
        if (! app()->environment(['local', 'development', 'testing']) && ! config('app.debug')) {
            $this->error('Este comando solo está permitido en entorno local o con APP_DEBUG.');

            return self::FAILURE;
        }

        $modalidadTienda = CatalogoModalidadPreparacionPedido::query()
            ->where('codigo', CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA)
            ->where('activo', true)
            ->first();
        $modalidadTransfer = CatalogoModalidadPreparacionPedido::query()
            ->where('codigo', CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA)
            ->where('activo', true)
            ->first();

        if (! $modalidadTienda) {
            $this->error('No hay modalidad RECOGE_TIENDA activa en catálogo.');

            return self::FAILURE;
        }

        $almacen = Almacen::query()->where('activo', true)->where('visible_en_pedidos', true)->orderBy('id')->first();
        if (! $almacen) {
            $this->error('No hay almacén activo visible en pedidos.');

            return self::FAILURE;
        }

        $usuario = User::query()->orderBy('id')->first();
        if (! $usuario) {
            $this->error('No hay usuarios en el sistema.');

            return self::FAILURE;
        }

        $pedidos = PedidoBma::query()
            ->whereDoesntHave('tareasPreparacion')
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($pedidos->count() < 8) {
            $this->error('Se necesitan al menos 8 pedidos sin tarea de preparación (hay '.$pedidos->count().').');

            return self::FAILURE;
        }

        $escenarios = [
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE, 'label' => 'Pendientes', 'md' => true, 'origen' => PedidoBma::ORIGEN_SOLICITUD_CALL_CENTER],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION, 'label' => 'En atención', 'asignada' => true],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA, 'label' => 'Incidencia', 'obs' => 'Faltante en anaquel A3'],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO, 'label' => 'Lista traslado', 'traslado' => true, 'transfer' => true],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA, 'label' => 'Lista carátula', 'municipio' => 'Guadalajara'],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO, 'label' => 'En traslado', 'traslado' => true, 'transfer' => true],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS, 'label' => 'Rechazada CEDIS', 'motivo_cedis' => 'Empaque dañado en recepción'],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA, 'label' => 'Respondida hoy', 'atendida_hoy' => true],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA, 'label' => 'Liberación'],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA, 'label' => 'Devolución pendiente', 'cumplimiento' => PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA, 'label' => 'Devuelta anaquel', 'cumplimiento' => PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL],
            ['estado' => PedidoBmaTareaPreparacion::ESTADO_LIBERADA, 'label' => 'Historial liberada'],
        ];

        if ($this->option('dry-run')) {
            $this->table(['Bandeja', 'Estado', 'Pedido id'], collect($escenarios)->map(function ($e, $i) use ($pedidos) {
                return [$e['label'], $e['estado'], $pedidos[$i]?->id ?? '—'];
            }));

            return self::SUCCESS;
        }

        $creadas = 0;

        DB::transaction(function () use ($escenarios, $pedidos, $modalidadTienda, $modalidadTransfer, $almacen, $usuario, $cumplimiento, &$creadas) {
            foreach ($escenarios as $i => $esc) {
                $pedido = $pedidos[$i];
                $modalidad = (! empty($esc['transfer']) && $modalidadTransfer) ? $modalidadTransfer : $modalidadTienda;
                $now = now()->subHours($i);

                $tarea = PedidoBmaTareaPreparacion::query()->create([
                    'pedido_bma_id' => $pedido->id,
                    'catalogo_modalidad_preparacion_id' => $modalidad->id,
                    'almacen_id' => $almacen->id,
                    'area_responsable_codigo' => 'TIENDA',
                    'estado' => $esc['estado'],
                    'solicitada_por_id' => $usuario->id,
                    'solicitada_at' => $now,
                    'asignada_a_id' => ! empty($esc['asignada']) ? $usuario->id : null,
                    'atendida_at' => ! empty($esc['atendida_hoy']) ? now() : null,
                    'fecha_limite' => now()->addDays(2),
                    'observaciones_solicitud' => 'Semilla UI · '.$esc['label'],
                    'observaciones_respuesta' => $esc['obs'] ?? null,
                    'requiere_traslado_cedis' => ! empty($esc['traslado']),
                    'motivo_rechazo_cedis' => $esc['motivo_cedis'] ?? null,
                    'municipio_destino' => $esc['municipio'] ?? null,
                    'destinatario_nombre' => ! empty($esc['municipio']) ? 'Cliente demo municipal' : null,
                    'version' => 1,
                ]);

                PedidoBmaTareaProducto::query()->create([
                    'pedido_bma_tarea_preparacion_id' => $tarea->id,
                    'orden' => 1,
                    'sku' => 'DEMO-SKU-'.($i + 1),
                    'descripcion_snapshot' => 'Producto demostración '.($i + 1),
                    'cantidad_solicitada' => 2 + $i,
                ]);

                $pedido->update([
                    'contacto_nombre_snapshot' => 'Cliente demo '.($i + 1),
                    'prioridad_md' => ! empty($esc['md']),
                    'origen_solicitud' => $esc['origen'] ?? ($i % 2 === 0 ? PedidoBma::ORIGEN_SOLICITUD_BELLAROMA : null),
                    'almacen_id' => $almacen->id,
                ]);

                if (! empty($esc['cumplimiento'])) {
                    $cf = $cumplimiento->ejecutar($tarea);
                    $cf->update(['estado' => $esc['cumplimiento'], 'cantidad' => 3]);
                }

                $creadas++;
            }
        });

        $this->info("Se crearon {$creadas} tareas de demostración en Preparación Tienda.");
        $this->line('Abra Control de pedidos → Preparación Tienda para revisar bandejas, filtros y tarjetas.');

        return self::SUCCESS;
    }
}
