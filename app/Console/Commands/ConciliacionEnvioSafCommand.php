<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionSistema;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\SaldosAFavor\SafPedidoAplicacion;
use App\Services\SaldosAFavor\RegistrarMovimientoSafService;
use App\Support\SaldosAFavor\PoliticaUsoCreditoSaf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Impacto y puesta en marcha de la protección de saldos de envío (S-06). Por omisión SOLO LEE: lista los créditos históricos
 * de motivo de envío, sus montos y las reservas vigentes sobre pedidos con mercancía. No cambia montos, estados, reservas
 * ni aplicaciones. Marcar los créditos y activar la protección son pasos explícitos que exigen --confirmo.
 */
class ConciliacionEnvioSafCommand extends Command
{
    protected $signature = 'saf:conciliacion-envio
        {--materializar : Marca como pendientes de conciliación los créditos históricos de motivo de envío (exige --confirmo)}
        {--activar-proteccion : Activa la regla que trata esos créditos como pendientes aunque no estén marcados (exige --confirmo)}
        {--confirmo : Confirma que se revisó el reporte de impacto}';

    protected $description = 'Reporte de impacto y conciliación de saldos a favor de envío (S-06). Sin opciones solo lee.';

    public function handle(RegistrarMovimientoSafService $movimientos): int
    {
        $candidatos = $this->candidatos();
        $this->reporte($candidatos);

        if (! $this->option('materializar') && ! $this->option('activar-proteccion')) {
            $this->info('Reporte de solo lectura: no se modificó nada.');

            return self::SUCCESS;
        }
        if (! $this->option('confirmo')) {
            $this->error('Revise el reporte y repita con --confirmo para aplicar.');

            return self::FAILURE;
        }

        if ($this->option('materializar')) {
            DB::transaction(function () use ($candidatos, $movimientos) {
                foreach ($candidatos as $credito) {
                    $credito->update([
                        'uso_permitido' => SafCredito::USO_PENDIENTE_CONCILIACION,
                        'origen_economico' => SafCredito::ORIGEN_NO_DETERMINADO,
                        'clasificacion_fuente' => 'migracion_conciliacion',
                    ]);
                    $disponible = (float) $credito->monto_disponible;
                    $movimientos->handle($credito, SafMovimiento::TIPO_CLASIFICACION, 0, $disponible, $disponible, null, [
                        'observaciones' => 'Pendiente de conciliación de origen (motivo de envío). Montos, reservas y aplicaciones sin cambios.',
                    ]);
                }
            });
            $this->info($candidatos->count().' créditos marcados como pendientes de conciliación. Sin cambios de monto, reserva ni aplicación.');
        }
        if ($this->option('activar-proteccion')) {
            ConfiguracionSistema::query()->updateOrCreate(
                ['clave' => PoliticaUsoCreditoSaf::CLAVE_PROTEGER_HISTORICOS],
                ['valor' => '1', 'tipo' => 'booleano', 'grupo' => 'saldos_favor', 'descripcion' => 'Trata los créditos históricos de motivo de envío como pendientes de conciliación.']
            );
            \Illuminate\Support\Facades\Cache::forget('saldos_favor.reglas');
            $this->info('Protección activada.');
        }

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, SafCredito> */
    private function candidatos()
    {
        $consulta = SafCredito::query()
            ->with('motivo')
            ->whereHas('motivo', fn ($q) => $q->where('categoria', PoliticaUsoCreditoSaf::CATEGORIA_ENVIO))
            ->orderBy('id');
        // Antes de la migración de esquema las columnas de clasificación no existen: el reporte igual puede leer el impacto.
        if (Schema::hasColumn('saf_creditos', 'uso_permitido')) {
            $consulta->whereNull('clasificado_at')->where('uso_permitido', SafCredito::USO_GENERAL);
        } elseif ($this->option('materializar') || $this->option('activar-proteccion')) {
            throw new \RuntimeException('Ejecute primero la migración de esquema de clasificación.');
        }

        return $consulta->get();
    }

    private function reporte($candidatos): void
    {
        $this->info('Créditos históricos de motivo de envío sin clasificación confirmada: '.$candidatos->count());
        if ($candidatos->isEmpty()) {
            return;
        }
        $this->table(
            ['Folio', 'Cliente', 'Motivo', 'Pedido', 'Original', 'Disponible', 'Reservado', 'Aplicado'],
            $candidatos->map(fn (SafCredito $c) => [
                $c->folio, $c->cliente_id, $c->motivo?->codigo, $c->pedido_bma_id ?? '—',
                number_format((float) $c->monto_original, 2), number_format((float) $c->monto_disponible, 2),
                number_format((float) $c->monto_reservado, 2), number_format((float) $c->monto_aplicado, 2),
            ])->all()
        );
        $this->line(sprintf(
            'Totales: disponible %s · reservado %s · aplicado %s',
            number_format((float) $candidatos->sum('monto_disponible'), 2),
            number_format((float) $candidatos->sum('monto_reservado'), 2),
            number_format((float) $candidatos->sum('monto_aplicado'), 2),
        ));

        $reservas = SafPedidoAplicacion::query()
            ->with('pedido:id,folio,total_mercancia')
            ->where('estado', SafPedidoAplicacion::ESTADO_RESERVADO)
            ->whereIn('saf_credito_id', $candidatos->pluck('id'))
            ->get();
        $this->warn('Reservas vigentes de estos créditos sobre pedidos: '.$reservas->count().' (no se modifican; quedan para decisión de negocio).');
        if ($reservas->isNotEmpty()) {
            $this->table(['Crédito', 'Pedido', 'Mercancía del pedido', 'Monto reservado'], $reservas->map(fn ($r) => [
                $r->saf_credito_id, $r->pedido?->folio ?? $r->pedido_bma_id, number_format((float) ($r->pedido?->total_mercancia ?? 0), 2), number_format((float) $r->monto, 2),
            ])->all());
        }
    }
}
