<?php

namespace App\Http\Requests\Reportes;

use App\Models\SaldosAFavor\PedidoBmaPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarReportePagosPedidosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'busqueda' => 'nullable|string|max:120',
            'tipo_fecha' => 'nullable|in:validacion,reportada,pago,pedido',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'fecha_validacion_desde' => 'nullable|date',
            'fecha_validacion_hasta' => 'nullable|date|after_or_equal:fecha_validacion_desde',
            'fecha_pedido_desde' => 'nullable|date',
            'fecha_pedido_hasta' => 'nullable|date|after_or_equal:fecha_pedido_desde',
            'fecha_reportada_desde' => 'nullable|date',
            'fecha_reportada_hasta' => 'nullable|date|after_or_equal:fecha_reportada_desde',
            'fecha_pago_desde' => 'nullable|date',
            'fecha_pago_hasta' => 'nullable|date|after_or_equal:fecha_pago_desde',
            'departamento_id' => 'nullable|integer|exists:departamentos,id',
            'vendedor_id' => 'nullable|integer|exists:users,id',
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'banco_id' => 'nullable|integer|exists:catalogo_bancos,id',
            'banco_ids' => 'nullable|array',
            'banco_ids.*' => 'integer|exists:catalogo_bancos,id',
            'sin_banco' => 'nullable|in:0,1,true,false',
            'forma_pago' => ['nullable', Rule::in(PedidoBmaPago::FORMAS_PAGO)],
            'formas_pago' => 'nullable|array',
            'formas_pago.*' => Rule::in(PedidoBmaPago::FORMAS_PAGO),
            'estado_cierre' => 'nullable|in:vigente,revocado,reconstruido,todos',
            'estado_exhibicion' => ['nullable', Rule::in([...PedidoBmaPago::ESTADOS_REVISION, 'sustituido'])],
            'estados_exhibicion' => 'nullable|array',
            'estados_exhibicion.*' => Rule::in([...PedidoBmaPago::ESTADOS_REVISION, 'sustituido']),
            'estado_cobertura' => 'nullable|in:cubierto,parcial,con_excedente,sin_pago',
            'estados_cobertura' => 'nullable|array',
            'estados_cobertura.*' => 'in:cubierto,parcial,con_excedente,sin_pago',
            'referencia_bancaria' => 'nullable|string|max:80',
            'origen_pedido' => 'nullable|string|max:80',
            'almacen_id' => 'nullable|integer|exists:almacenes,id',
            'con_remision' => 'nullable|in:0,1',
            'con_evidencia' => 'nullable|in:0,1',
            'incluir_vouchers' => 'nullable|in:0,1',
            'incluir_evidencias_rechazadas_sustituidas' => 'nullable|in:0,1',
            'incluir_referencias_remision' => 'nullable|in:0,1',
            'incluir_observaciones_historial' => 'nullable|in:0,1',
            'orden' => 'nullable|in:asc,desc',
            'agrupar_por' => 'nullable|in:dia,banco,vendedora,movimiento,forma_pago',
            'formato' => 'nullable|in:pdf,csv_resumen,csv_detalle',
            'fecha_incompleta' => 'nullable|in:pedido,pago,reportada,validacion',
            'tipo_reporte' => 'nullable|in:pedido,vouchers',
            'reportado_posteriormente' => 'nullable|in:0,1',
            'posible_duplicado' => 'nullable|in:0,1',
            'con_saf_relacionado' => 'nullable|in:0,1',
            'con_observaciones' => 'nullable|in:0,1',
            'capturado_por_id' => 'nullable|integer|exists:users,id',
            'validado_por_id' => 'nullable|integer|exists:users,id',
            'monto_desde' => 'nullable|numeric|min:0',
            'monto_hasta' => 'nullable|numeric|min:0',
            'folio_pedido' => 'nullable|string|max:80',
            'folio_remision' => 'nullable|string|max:80',
        ];
    }

    /** @return array<string, mixed> */
    public function filtrosNormalizados(): array
    {
        $data = $this->validated();
        $tipoReporte = $data['tipo_reporte'] ?? 'pedido';

        $out = [
            'page' => $data['page'] ?? 1,
            'tipo_reporte' => $tipoReporte,
            'tipo_fecha' => $data['tipo_fecha'] ?? null,
            'busqueda' => isset($data['busqueda']) ? trim($data['busqueda']) : null,
            'departamento_id' => $data['departamento_id'] ?? null,
            'vendedor_id' => $data['vendedor_id'] ?? null,
            'cliente_id' => $data['cliente_id'] ?? null,
            'almacen_id' => $data['almacen_id'] ?? null,
            'estado_cierre' => $data['estado_cierre'] ?? 'vigente',
            'con_remision' => $data['con_remision'] ?? null,
            'con_evidencia' => $data['con_evidencia'] ?? null,
            'referencia_bancaria' => isset($data['referencia_bancaria']) ? trim($data['referencia_bancaria']) : null,
            'origen_pedido' => isset($data['origen_pedido']) ? trim($data['origen_pedido']) : null,
            'sin_banco' => filter_var($data['sin_banco'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'banco_ids' => $this->normalizarIds($data['banco_ids'] ?? [], $data['banco_id'] ?? null),
            'formas_pago' => $this->normalizarLista($data['formas_pago'] ?? [], $data['forma_pago'] ?? null),
            'estados_exhibicion' => $this->normalizarLista($data['estados_exhibicion'] ?? [], $data['estado_exhibicion'] ?? null),
            'estados_cobertura' => $this->normalizarLista($data['estados_cobertura'] ?? [], $data['estado_cobertura'] ?? null),
            'incluir_vouchers' => ($data['incluir_vouchers'] ?? '1') !== '0',
            'incluir_evidencias_rechazadas_sustituidas' => ($data['incluir_evidencias_rechazadas_sustituidas'] ?? '1') !== '0',
            'incluir_referencias_remision' => ($data['incluir_referencias_remision'] ?? '1') !== '0',
            'incluir_observaciones_historial' => ($data['incluir_observaciones_historial'] ?? '1') !== '0',
            'orden' => $data['orden'] ?? 'desc',
            'agrupar_por' => $data['agrupar_por'] ?? ($tipoReporte === 'vouchers' ? 'movimiento' : 'dia'),
            'fecha_incompleta' => $data['fecha_incompleta'] ?? null,
            'reportado_posteriormente' => ($data['reportado_posteriormente'] ?? '0') === '1',
            'posible_duplicado' => ($data['posible_duplicado'] ?? '0') === '1',
            'con_saf_relacionado' => ($data['con_saf_relacionado'] ?? '0') === '1',
            'con_observaciones' => ($data['con_observaciones'] ?? '0') === '1',
            'capturado_por_id' => $data['capturado_por_id'] ?? null,
            'validado_por_id' => $data['validado_por_id'] ?? null,
            'monto_desde' => isset($data['monto_desde']) ? (string) $data['monto_desde'] : null,
            'monto_hasta' => isset($data['monto_hasta']) ? (string) $data['monto_hasta'] : null,
            'folio_pedido' => isset($data['folio_pedido']) ? trim($data['folio_pedido']) : null,
            'folio_remision' => isset($data['folio_remision']) ? trim($data['folio_remision']) : null,
            'banco_id' => $data['banco_id'] ?? null,
        ];

        $out = array_merge($out, $this->normalizarFechas($data));

        if (empty($out['tipo_fecha'])) {
            $out['tipo_fecha'] = $tipoReporte === 'vouchers' ? 'pago' : 'pedido';
            if (! empty($out['fecha_validacion_desde']) || ! empty($out['fecha_validacion_hasta'])) {
                $out['tipo_fecha'] = 'validacion';
            } elseif (! empty($out['fecha_reportada_desde']) || ! empty($out['fecha_reportada_hasta'])) {
                $out['tipo_fecha'] = 'reportada';
            } elseif (! empty($out['fecha_pago_desde']) || ! empty($out['fecha_pago_hasta'])) {
                $out['tipo_fecha'] = 'pago';
            }
        }

        $filtrado = array_filter($out, fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== false);
        $filtrado['tipo_reporte'] = $tipoReporte;

        return $filtrado;
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function normalizarFechas(array $data): array
    {
        $campos = [
            'fecha_validacion_desde' => null,
            'fecha_validacion_hasta' => null,
            'fecha_pedido_desde' => null,
            'fecha_pedido_hasta' => null,
            'fecha_reportada_desde' => null,
            'fecha_reportada_hasta' => null,
            'fecha_pago_desde' => null,
            'fecha_pago_hasta' => null,
        ];

        $tipo = $data['tipo_fecha'] ?? null;
        $desde = $data['fecha_desde'] ?? null;
        $hasta = $data['fecha_hasta'] ?? null;

        if ($tipo && ($desde || $hasta)) {
            $prefijo = match ($tipo) {
                'pedido' => 'fecha_pedido',
                'reportada' => 'fecha_reportada',
                'pago' => 'fecha_pago',
                default => 'fecha_validacion',
            };
            $campos["{$prefijo}_desde"] = $desde;
            $campos["{$prefijo}_hasta"] = $hasta;

            return $campos;
        }

        foreach ([
            'fecha_validacion_desde', 'fecha_validacion_hasta',
            'fecha_pedido_desde', 'fecha_pedido_hasta',
            'fecha_reportada_desde', 'fecha_reportada_hasta',
            'fecha_pago_desde', 'fecha_pago_hasta',
        ] as $key) {
            if (! empty($data[$key])) {
                $campos[$key] = $data[$key];
            }
        }

        return $campos;
    }

    /** @param  list<mixed>  $lista */
    private function normalizarIds(array $lista, mixed $unico): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $lista))));
        if ($unico) {
            $ids[] = (int) $unico;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** @param  list<mixed>  $lista */
    private function normalizarLista(array $lista, mixed $unico): array
    {
        $items = array_values(array_filter(array_map('strval', $lista)));
        if ($unico) {
            $items[] = (string) $unico;
        }

        return array_values(array_unique(array_filter($items)));
    }
}
