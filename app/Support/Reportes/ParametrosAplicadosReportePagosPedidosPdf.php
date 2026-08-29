<?php

namespace App\Support\Reportes;

use App\Models\CatalogoBanco;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Models\Almacen;
use Illuminate\Support\Carbon;

/**
 * Filas legibles de parámetros aplicados para el PDF de pagos de pedidos.
 */
final class ParametrosAplicadosReportePagosPedidosPdf
{
    /** @var array<string, string> */
    private const ESTADOS_EXHIBICION = [
        'pendiente' => 'Pendientes',
        'en_revision' => 'En revisión',
        'verificado' => 'Verificados',
        'con_observaciones' => 'Con observaciones',
        'rechazado' => 'Rechazados',
    ];

    /** @var array<string, string> */
    private const ESTADOS_COBERTURA = [
        'cubierto' => 'Cubierto',
        'parcial' => 'Parcial',
        'con_excedente' => 'Excedente',
        'sin_pago' => 'Sin pago',
    ];

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{parametro: string, seleccion: string}>
     */
    public static function filas(array $filtros): array
    {
        $tipoFecha = EncabezadoReportePagosPedidosPdf::tipoFechaPublico($filtros);

        $filas = [
            ['parametro' => 'Periodo', 'seleccion' => self::periodoCompacto($filtros, $tipoFecha)],
            ['parametro' => 'Fecha utilizada', 'seleccion' => $tipoFecha],
            ['parametro' => 'Bancos', 'seleccion' => self::bancos($filtros)],
            ['parametro' => 'Formas de pago', 'seleccion' => self::formasPago($filtros)],
            ['parametro' => 'Estados', 'seleccion' => self::estados($filtros)],
            ['parametro' => 'Evidencias', 'seleccion' => self::evidencias($filtros)],
            ['parametro' => 'Agrupación', 'seleccion' => self::agrupacion()],
        ];

        foreach (self::filasAdicionales($filtros) as $extra) {
            $filas[] = $extra;
        }

        return $filas;
    }

    /** @param  array<string, mixed>  $filtros */
    private static function periodoCompacto(array $filtros, string $tipoFecha): string
    {
        $esPedido = $tipoFecha === 'Fecha de pedido';
        $desde = $esPedido ? ($filtros['fecha_pedido_desde'] ?? null) : ($filtros['fecha_validacion_desde'] ?? null);
        $hasta = $esPedido ? ($filtros['fecha_pedido_hasta'] ?? null) : ($filtros['fecha_validacion_hasta'] ?? null);

        if (! $desde && ! $hasta) {
            return 'Sin rango (histórico completo)';
        }
        if ($desde && $hasta) {
            $d1 = Carbon::parse($desde)->locale('es');
            $d2 = Carbon::parse($hasta)->locale('es');
            if ($d1->year === $d2->year && $d1->month === $d2->month) {
                return $d1->day.'–'.$d2->day.' de '.$d1->isoFormat('MMMM [de] YYYY');
            }
            if ($d1->year === $d2->year) {
                return $d1->isoFormat('D MMM').' – '.$d2->isoFormat('D MMM [de] YYYY');
            }

            return $d1->isoFormat('D MMM YYYY').' – '.$d2->isoFormat('D MMM YYYY');
        }
        if ($desde) {
            return 'Desde '.Carbon::parse($desde)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        }

        return 'Hasta '.Carbon::parse($hasta)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    }

    /** @param  array<string, mixed>  $filtros */
    private static function bancos(array $filtros): string
    {
        if (empty($filtros['banco_id'])) {
            return 'Todos';
        }
        $nombre = CatalogoBanco::query()->whereKey((int) $filtros['banco_id'])->value('nombre');

        return $nombre ?: 'Banco #'.$filtros['banco_id'];
    }

    /** @param  array<string, mixed>  $filtros */
    private static function formasPago(array $filtros): string
    {
        if (empty($filtros['forma_pago'])) {
            return 'Todas';
        }

        return PedidoBmaPago::labelForma((string) $filtros['forma_pago'])
            ?? ucfirst((string) $filtros['forma_pago']);
    }

    /** @param  array<string, mixed>  $filtros */
    private static function estados(array $filtros): string
    {
        $partes = [];

        if (! empty($filtros['estado_exhibicion'])) {
            $partes[] = self::ESTADOS_EXHIBICION[$filtros['estado_exhibicion']]
                ?? ucfirst(str_replace('_', ' ', (string) $filtros['estado_exhibicion']));
        }
        if (! empty($filtros['estado_cobertura'])) {
            $partes[] = self::ESTADOS_COBERTURA[$filtros['estado_cobertura']]
                ?? ucfirst(str_replace('_', ' ', (string) $filtros['estado_cobertura']));
        }

        $estadoCierre = $filtros['estado_cierre'] ?? 'vigente';
        if ($estadoCierre !== 'vigente') {
            $partes[] = match ($estadoCierre) {
                'revocado' => 'Cierres revocados',
                'todos' => 'Todos los cierres',
                default => ucfirst((string) $estadoCierre),
            };
        }

        return $partes === [] ? 'Todos' : implode(' · ', $partes);
    }

    /** @param  array<string, mixed>  $filtros */
    private static function evidencias(array $filtros): string
    {
        if (! isset($filtros['con_evidencia']) || $filtros['con_evidencia'] === '') {
            return 'Todas';
        }

        return match ((string) $filtros['con_evidencia']) {
            '1' => 'Vouchers incluidos',
            '0' => 'Sin vouchers',
            default => 'Todas',
        };
    }

    private static function agrupacion(): string
    {
        return 'Día de validación';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{parametro: string, seleccion: string}>
     */
    private static function filasAdicionales(array $filtros): array
    {
        $extras = [];

        if (! empty($filtros['busqueda'])) {
            $extras[] = ['parametro' => 'Búsqueda', 'seleccion' => (string) $filtros['busqueda']];
        }
        if (! empty($filtros['con_remision'])) {
            $extras[] = [
                'parametro' => 'Remisión',
                'seleccion' => $filtros['con_remision'] === '1' ? 'Con remisión' : 'Sin remisión',
            ];
        }
        if (! empty($filtros['departamento_id'])) {
            $nombre = Departamento::query()->whereKey((int) $filtros['departamento_id'])->value('nombre');
            $extras[] = ['parametro' => 'Departamento', 'seleccion' => $nombre ?: '#'.$filtros['departamento_id']];
        }
        if (! empty($filtros['vendedor_id'])) {
            $nombre = User::query()->whereKey((int) $filtros['vendedor_id'])->value('name');
            $extras[] = ['parametro' => 'Atendió', 'seleccion' => $nombre ?: '#'.$filtros['vendedor_id']];
        }
        if (! empty($filtros['cliente_id'])) {
            $nombre = Cliente::query()->whereKey((int) $filtros['cliente_id'])->value('nombre');
            $extras[] = ['parametro' => 'Cliente', 'seleccion' => $nombre ?: '#'.$filtros['cliente_id']];
        }
        if (! empty($filtros['almacen_id'])) {
            $nombre = Almacen::query()->whereKey((int) $filtros['almacen_id'])->value('nombre');
            $extras[] = ['parametro' => 'Almacén', 'seleccion' => $nombre ?: '#'.$filtros['almacen_id']];
        }

        return $extras;
    }
}
