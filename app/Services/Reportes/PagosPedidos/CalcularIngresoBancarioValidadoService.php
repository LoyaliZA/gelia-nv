<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Support\Reportes\ClasificacionIngresoBancario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Total de ingreso bancario validado (exhibiciones), sin SAF ni pagos no bancarios. */
class CalcularIngresoBancarioValidadoService
{
    /**
     * @param  iterable<PedidoBmaCierrePagoItem>  $items
     * @return array{
     *     total_ingreso_bancario: string,
     *     vouchers_ingreso_bancario: int,
     *     por_banco: list<array{banco_id: ?int, banco: string, total: string, vouchers: int}>,
     *     excluidos: array{pago_no_bancario: string, pendiente: string, rechazado: string, sustituido: string}
     * }
     */
    public function desdeItems(iterable $items): array
    {
        $coleccion = $items instanceof Collection ? $items : collect([...$items]);

        $totalCentavos = 0;
        $conteo = 0;
        /** @var array<string, array{banco_id: ?int, banco: string, centavos: int, vouchers: int}> $porBanco */
        $porBanco = [];
        $excluidos = [
            ClasificacionIngresoBancario::PAGO_NO_BANCARIO => 0,
            ClasificacionIngresoBancario::PAGO_PENDIENTE => 0,
            ClasificacionIngresoBancario::PAGO_RECHAZADO => 0,
            ClasificacionIngresoBancario::SUSTITUIDO => 0,
        ];

        foreach ($coleccion as $item) {
            if (! $item instanceof PedidoBmaCierrePagoItem) {
                continue;
            }

            $clasificacion = ClasificacionIngresoBancario::clasificarItem($item);
            $centavos = (int) round((float) $item->monto_snapshot * 100);

            if ($clasificacion === ClasificacionIngresoBancario::INGRESO_BANCARIO) {
                $totalCentavos += $centavos;
                $conteo++;
                $clave = (string) ($item->catalogo_banco_id ?? 'sin_banco');
                if (! isset($porBanco[$clave])) {
                    $porBanco[$clave] = [
                        'banco_id' => $item->catalogo_banco_id,
                        'banco' => $item->banco_snapshot ?: 'Sin banco',
                        'centavos' => 0,
                        'vouchers' => 0,
                    ];
                }
                $porBanco[$clave]['centavos'] += $centavos;
                $porBanco[$clave]['vouchers']++;
            } elseif (array_key_exists($clasificacion, $excluidos)) {
                $excluidos[$clasificacion] += $centavos;
            }
        }

        return [
            'total_ingreso_bancario' => $this->desdeCentavos($totalCentavos),
            'vouchers_ingreso_bancario' => $conteo,
            'por_banco' => collect($porBanco)
                ->sortByDesc('centavos')
                ->values()
                ->map(fn (array $fila) => [
                    'banco_id' => $fila['banco_id'],
                    'banco' => $fila['banco'],
                    'total' => $this->desdeCentavos($fila['centavos']),
                    'vouchers' => $fila['vouchers'],
                ])
                ->all(),
            'excluidos' => [
                'pago_no_bancario' => $this->desdeCentavos($excluidos[ClasificacionIngresoBancario::PAGO_NO_BANCARIO]),
                'pendiente' => $this->desdeCentavos($excluidos[ClasificacionIngresoBancario::PAGO_PENDIENTE]),
                'rechazado' => $this->desdeCentavos($excluidos[ClasificacionIngresoBancario::PAGO_RECHAZADO]),
                'sustituido' => $this->desdeCentavos($excluidos[ClasificacionIngresoBancario::SUSTITUIDO]),
            ],
        ];
    }

    /**
     * @param  Builder<PedidoBmaCierrePagoItem>  $query
     * @return array<string, mixed>
     */
    public function desdeQuery(Builder $query): array
    {
        return $this->desdeItems($query->get());
    }

    private function desdeCentavos(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
