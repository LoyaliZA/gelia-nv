<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafCuenta;
use App\Models\SaldosAFavor\SafMovimiento;

class ConsultarCuentaClienteSafService
{
    public function __construct(
        private ObtenerOCrearCuentaSafService $cuentas,
    ) {}

    public function handle(int $clienteId, string $moneda = 'MXN'): array
    {
        $cuenta = $this->cuentas->handle($clienteId, $moneda);

        $creditos = SafCredito::query()
            ->with(['motivo', 'generadoPor:id,name'])
            ->where('saf_cuenta_id', $cuenta->id)
            ->orderByRaw("CASE estado_financiero
                WHEN 'disponible' THEN 1
                WHEN 'parcialmente_aplicado' THEN 1
                WHEN 'reservado' THEN 2
                WHEN 'aplicado' THEN 3
                WHEN 'vencido' THEN 4
                ELSE 5 END")
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->get();

        $usables = $creditos->filter(fn (SafCredito $c) => $c->puedeUsarse()
            && $c->fecha_vencimiento->gte(now()->startOfDay()));

        $disponible = round($usables->sum(fn (SafCredito $c) => (float) $c->monto_disponible), 2);
        $reservado = round($creditos->sum(fn (SafCredito $c) => (float) $c->monto_reservado), 2);
        $aplicado = round($creditos->sum(fn (SafCredito $c) => (float) $c->monto_aplicado), 2);
        $vencido = round(
            $creditos
                ->where('estado_financiero', SafCredito::ESTADO_VENCIDO)
                ->sum(fn (SafCredito $c) => (float) $c->monto_disponible),
            2
        );

        $movimientos = SafMovimiento::query()
            ->with(['credito:id,folio', 'usuario:id,name'])
            ->where('cliente_id', $clienteId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'cuenta' => $cuenta,
            'disponible' => $disponible,
            'reservado' => $reservado,
            'aplicado' => $aplicado,
            'vencido' => $vencido,
            'creditos' => $creditos,
            'creditos_usables' => $usables->values(),
            'movimientos' => $movimientos,
        ];
    }

    public function sugerirAplicacion(int $clienteId, float $montoDeseado): array
    {
        $montoDeseado = round($montoDeseado, 2);
        $cuenta = $this->handle($clienteId);
        $restante = $montoDeseado;
        $sugerencia = [];

        foreach ($cuenta['creditos_usables'] as $credito) {
            if ($restante <= 0) {
                break;
            }
            $tomar = min((float) $credito->monto_disponible, $restante);
            if ($tomar <= 0) {
                continue;
            }
            $sugerencia[] = [
                'saf_credito_id' => $credito->id,
                'folio' => $credito->folio,
                'canal_origen' => $credito->canal_origen,
                'disponible' => (float) $credito->monto_disponible,
                'fecha_vencimiento' => $credito->fecha_vencimiento?->toDateString(),
                'monto' => round($tomar, 2),
            ];
            $restante = round($restante - $tomar, 2);
        }

        return [
            'disponible_total' => $cuenta['disponible'],
            'monto_solicitado' => $montoDeseado,
            'monto_cubierto' => round($montoDeseado - max($restante, 0), 2),
            'faltante' => max($restante, 0),
            'items' => $sugerencia,
        ];
    }
}
