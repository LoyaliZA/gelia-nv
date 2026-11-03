<?php

namespace App\Services\Mobile\Serializar;

use App\Models\RhBancoTiempo;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhHorasExtra;
use App\Models\RhPrestamoPagoFijo;
use App\Models\RhSalidaPersonal;

class RhMovilSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function colaborador(RhColaborador $colaborador, bool $conCompensacion = true): array
    {
        $datos = [
            'id' => $colaborador->id,
            'folio' => $colaborador->folio,
            'nombre' => $colaborador->nombre,
            'apellido_paterno' => $colaborador->apellido_paterno,
            'apellido_materno' => $colaborador->apellido_materno,
            'nombre_completo' => $colaborador->nombre_completo,
            'activo' => (bool) $colaborador->activo,
            'departamento' => $this->nombre($colaborador->departamento),
            'area' => $this->nombre($colaborador->area),
            'puesto' => $this->nombre($colaborador->puesto),
            'turno' => $this->nombre($colaborador->turno),
            'usuario' => $colaborador->usuario ? [
                'id' => $colaborador->usuario->id,
                'name' => $colaborador->usuario->name,
                'email' => $colaborador->usuario->email,
            ] : null,
        ];

        if ($conCompensacion) {
            $datos['compensacion'] = [
                'salario_base' => $colaborador->salario_base,
                'salario_diario' => $colaborador->salario_diario,
                'bono_productividad' => $colaborador->bono_productividad,
                'bono_puntualidad' => $colaborador->bono_puntualidad,
                'saldo_comisiones' => $colaborador->saldo_comisiones,
                'horas_laboradas_oficiales' => $colaborador->horas_laboradas_oficiales,
            ];
        }

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    public function horasExtra(RhHorasExtra $registro): array
    {
        return [
            'id' => $registro->id,
            'folio' => $registro->folio,
            'fecha_turno' => $registro->fecha_turno?->toDateString() ?? (string) $registro->fecha_turno,
            'hora_entrada' => $registro->hora_entrada,
            'hora_salida' => $registro->hora_salida,
            'horas_extra_a_pagar' => $registro->horas_extra_a_pagar,
            'total_economico' => $registro->total_economico,
            'estado_pago' => $registro->estado_pago,
            'motivo' => $registro->motivo,
            'colaborador' => $this->colaboradorCorto($registro->colaborador),
            'supervisor' => $registro->supervisor ? [
                'id' => $registro->supervisor->id,
                'name' => $registro->supervisor->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function salida(RhSalidaPersonal $salida): array
    {
        return [
            'id' => $salida->id,
            'folio' => $salida->folio,
            'fecha_evento' => $salida->fecha_evento?->toDateString(),
            'motivo' => $salida->motivo,
            'hora_salida' => $salida->hora_salida,
            'hora_regreso' => $salida->hora_regreso,
            'minutos_ausente' => $salida->minutos_ausente,
            'monto_a_deducir' => $salida->monto_a_deducir,
            'fecha_deduccion_nomina' => $salida->fecha_deduccion_nomina?->toDateString(),
            'colaborador' => $this->colaboradorCorto($salida->colaborador),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deduccion(RhDeduccion $deduccion): array
    {
        return [
            'id' => $deduccion->id,
            'folio' => $deduccion->folio,
            'fecha_ocurrencia' => $deduccion->fecha_ocurrencia?->toDateString() ?? (string) $deduccion->fecha_ocurrencia,
            'estado_deduccion' => $deduccion->estado_deduccion,
            'origen_deduccion' => $deduccion->origen_deduccion,
            'total_deduccion' => $deduccion->total_deduccion,
            'producto_sku' => $deduccion->producto_sku_snapshot,
            'regla' => $deduccion->regla_nombre_snapshot ?: $deduccion->reglaIncidencia?->nombre,
            'descripcion' => $deduccion->descripcion_detallada,
            'colaborador' => $this->colaboradorCorto($deduccion->colaborador),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function prestamo(RhPrestamoPagoFijo $prestamo): array
    {
        return [
            'id' => $prestamo->id,
            'folio' => $prestamo->folio,
            'concepto' => $prestamo->concepto,
            'monto_cuota' => $prestamo->monto_cuota,
            'num_pagos_total' => $prestamo->num_pagos_total,
            'pagos_realizados' => $prestamo->pagos_realizados,
            'modalidad' => $prestamo->modalidad,
            'estado' => $prestamo->estado,
            'fecha_inicio' => $prestamo->fecha_inicio?->toDateString() ?? (string) $prestamo->fecha_inicio,
            'colaborador' => $this->colaboradorCorto($prestamo->colaborador),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bancoTiempo(RhBancoTiempo $banco): array
    {
        return [
            'id' => $banco->id,
            'folio' => $banco->folio,
            'horas_pendientes' => $banco->horas_pendientes,
            'origen_deuda' => $banco->origen_deuda,
            'estado' => $banco->estado,
            'nombre_estado' => $banco->nombre_estado,
            'fecha_acuerdo' => $banco->fecha_acuerdo?->toDateString(),
            'fecha_devolucion' => $banco->fecha_devolucion?->toDateString(),
            'colaborador' => $this->colaboradorCorto($banco->colaborador),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function colaboradorCorto(?RhColaborador $colaborador): ?array
    {
        if ($colaborador === null) {
            return null;
        }

        return [
            'id' => $colaborador->id,
            'folio' => $colaborador->folio,
            'nombre_completo' => $colaborador->nombre_completo,
            'departamento' => $colaborador->departamento?->nombre,
            'area' => $colaborador->area?->nombre,
        ];
    }

    /**
     * @return array{id: int, nombre: string}|null
     */
    private function nombre(mixed $modelo): ?array
    {
        if ($modelo === null) {
            return null;
        }

        return [
            'id' => $modelo->id,
            'nombre' => $modelo->nombre,
        ];
    }
}
