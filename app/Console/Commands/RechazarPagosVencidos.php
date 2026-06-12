<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolicitudTag;
use App\Models\AuditoriaSolicitud;
use App\Models\HistorialMontoCliente;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AlertaSolicitud;
use App\Notifications\ResumenPagosVencidos;

class RechazarPagosVencidos extends Command
{
    protected $signature = 'pagos:rechazar-vencidos';
    protected $description = 'Rechaza solicitudes sin pago tras 48 horas y envía un reporte consolidado a las encargadas a las 9:00 AM.';

    public function handle()
    {
        if (Cache::get('import_clientes_en_curso')) {
            $this->warn('Importación de clientes en curso. Se omite rechazo de pagos vencidos para evitar conflictos de montos.');
            return;
        }

        $solicitudesVencidas = SolicitudTag::with(['cliente.vendedor', 'cliente.listaDescuento', 'cliente.tipo', 'proceso'])
            ->sujetasAPlazoDePago()
            ->where('created_at', '<', now()->subHours(48))
            ->get();

        if ($solicitudesVencidas->isEmpty()) {
            $this->info("Operación completada: No hay solicitudes vencidas.");
            return;
        }

        $listas = CatalogoListaDescuento::orderBy('monto_requerido', 'desc')->get();
        $encargadas = User::permission('solicitudes.verificar')->get();
        $contador = 0;

        foreach ($solicitudesVencidas as $solicitud) {
            if ($solicitud->esProcesoOperativo()) {
                continue;
            }

            DB::transaction(function () use ($solicitud, $listas, &$contador) {
                $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
                $cliente = $solicitud->cliente;
                $snapshotDiff = [];

                if ($cliente) {
                    $snapshotDiff['antes'] = $this->capturarSnapshotCliente($cliente);
                }

                $solicitud->update([
                    'catalogo_estado_solicitud_id' => 4,
                    'motivo_incorrecta' => 'vencimiento_pago',
                ]);

                if ($cliente) {
                    if ($estadoAnteriorId == 2) {
                        $montoAnterior = $cliente->monto_venta_actual;
                        $montoNuevo = max(0, $montoAnterior - $solicitud->monto_cotizado);
                        $nuevaListaId = $this->determinarListaPorMonto($montoNuevo, $listas);

                        HistorialMontoCliente::create([
                            'cliente_id' => $cliente->id,
                            'monto_anterior' => $montoAnterior,
                            'monto_nuevo' => $montoNuevo,
                            'diferencia_aplicada' => -abs($solicitud->monto_cotizado),
                        ]);

                        $cliente->update([
                            'monto_venta_actual' => $montoNuevo,
                            'lista_actual_id' => $nuevaListaId,
                            'catalogo_tipo_cliente_id' => $solicitud->catalogo_tipo_cliente_id ? null : $cliente->catalogo_tipo_cliente_id,
                        ]);
                    } else {
                        $cliente->update([
                            'catalogo_tipo_cliente_id' => $solicitud->catalogo_tipo_cliente_id ? null : $cliente->catalogo_tipo_cliente_id,
                        ]);
                    }

                    $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);
                    $snapshotDiff['despues'] = $this->capturarSnapshotCliente($cliente);
                }

                AuditoriaSolicitud::create([
                    'solicitud_id' => $solicitud->id,
                    'usuario_id' => 1,
                    'estado_anterior_id' => $estadoAnteriorId,
                    'estado_nuevo_id' => 4,
                    'motivo_reporte' => 'SISTEMA AUTOMÁTICO: Plazo de pago expirado (48h).',
                    'datos_snapshot' => !empty($snapshotDiff) ? $snapshotDiff : null,
                ]);

                if ($solicitud->vendedor) {
                    $solicitud->vendedor->notify(new AlertaSolicitud(
                        $solicitud,
                        'pago_rechazado',
                        'El tiempo expiró. Solicitud rebotada por falta de pago (48h). Debes iniciar una nueva solicitud.'
                    ));
                }

                $contador++;
            });
        }

        if ($contador > 0 && $encargadas->isNotEmpty()) {
            Notification::send($encargadas, new ResumenPagosVencidos($contador));
        }

        $this->info("Se procesaron {$contador} solicitudes vencidas.");
    }

    private function capturarSnapshotCliente(?Cliente $cliente): array
    {
        if (!$cliente) {
            return [];
        }

        $cliente->loadMissing(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'monto_venta' => $cliente->monto_venta_actual,
            'lista_id' => $cliente->lista_actual_id,
            'lista_nombre' => $cliente->listaDescuento?->nombre,
            'tag_vendedor_id' => $cliente->vendedor_id,
            'tag_vendedor_nombre' => $cliente->vendedor?->name,
            'tipo_cliente_id' => $cliente->catalogo_tipo_cliente_id,
            'tipo_cliente_nombre' => $cliente->tipo?->nombre,
        ];
    }

    private function determinarListaPorMonto(float $monto, $listas): int
    {
        foreach ($listas as $lista) {
            if ($lista->nombre === 'COLABORADORES') continue;
            if ($monto >= $lista->monto_requerido) {
                return $lista->id;
            }
        }
        return $listas->where('nombre', 'PUBLICO GENERAL')->first()->id ?? 1;
    }
}
