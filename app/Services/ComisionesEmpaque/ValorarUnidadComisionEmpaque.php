<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\IncidenciaComisionEmpaque;
use App\Models\ComisionesEmpaque\TarifaComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ComisionesEmpaque\HuellaFuenteComisionEmpaque;

class ValorarUnidadComisionEmpaque
{
    /** Situaciones que la valoración no debe promover a elegible. */
    private const CONGELADAS = [
        UnidadComisionEmpaque::PENDIENTE_REVISION,
        UnidadComisionEmpaque::RECHAZADA,
        UnidadComisionEmpaque::PENDIENTE_DECISION,
        UnidadComisionEmpaque::REVERTIDA_REVISION,
        UnidadComisionEmpaque::REEMPAQUE_REVISION,
        UnidadComisionEmpaque::SOLAPE_REVISION,
        UnidadComisionEmpaque::EN_CORTE,
        UnidadComisionEmpaque::POSIBLE_DUPLICADO,
    ];

    /** @param  list<string>  $decisiones */
    public function anclaCubre(UnidadComisionEmpaque $unidad, array $decisiones): bool
    {
        return $unidad->ancla_decision
            && $unidad->ancla_huella
            && hash_equals((string) $unidad->ancla_huella, (string) $unidad->huella)
            && in_array($unidad->ancla_decision, $decisiones, true);
    }

    /**
     * La decisión de conservar o excluir cubre solo este hecho de cancelación.
     * No se compara con la huella de la captura manual.
     *
     * @param  list<string>  $decisiones
     */
    public function cancelacionVigente(UnidadComisionEmpaque $unidad, string $hecho, array $decisiones): bool
    {
        return $unidad->ancla_decision
            && $unidad->ancla_huella
            && hash_equals((string) $unidad->ancla_huella, $hecho)
            && in_array($unidad->ancla_decision, $decisiones, true);
    }

    public function fijarAncla(UnidadComisionEmpaque $unidad, string $decision, string $motivo, User $responsable): void
    {
        $unidad->ancla_decision = $decision;
        $unidad->ancla_huella = $unidad->huella;
        $unidad->ancla_motivo = $motivo;
        $unidad->ancla_por_id = $responsable->id;
        $unidad->ancla_at = now();
    }

    public function aplicar(UnidadComisionEmpaque $unidad): UnidadComisionEmpaque
    {
        if ($unidad->corte_id) {
            return $unidad;
        }

        if ($unidad->origen_registro === 'manual'
            && $unidad->ancla_decision === 'excluir'
            && $unidad->pedido_bma_id
            && $unidad->ancla_huella) {
            $pedido = PedidoBma::query()->find($unidad->pedido_bma_id);
            if ($pedido?->cancelado_at) {
                $hecho = HuellaFuenteComisionEmpaque::deCancelacion((int) $pedido->id, $pedido->cancelado_at);
                if ($this->cancelacionVigente($unidad, $hecho, ['excluir'])) {
                    $unidad->situacion = UnidadComisionEmpaque::EXCLUIDA;
                    $unidad->importe_centavos = 0;
                    $unidad->cantidad_comisionable = 0;
                    $unidad->save();

                    return $unidad;
                }
            }
        }

        if ($this->anclaCubre($unidad, ['excluir', 'descartar'])) {
            $unidad->situacion = UnidadComisionEmpaque::EXCLUIDA;
            $unidad->importe_centavos = 0;
            $unidad->cantidad_comisionable = 0;
            $unidad->save();

            return $unidad;
        }

        if (in_array($unidad->situacion, [UnidadComisionEmpaque::PENDIENTE_REVISION, UnidadComisionEmpaque::POSIBLE_DUPLICADO, UnidadComisionEmpaque::RECHAZADA], true)) {
            $unidad->importe_centavos = null;
            $unidad->cantidad_comisionable = null;
            $unidad->save();

            return $unidad;
        }

        $unidad->loadMissing('lineas');
        $suma = (int) $unidad->lineas->sum('cantidad');
        $congelada = in_array($unidad->situacion, self::CONGELADAS, true);

        if ($unidad->lineas->isEmpty() || $suma !== (int) $unidad->cantidad_origen) {
            $unidad->cantidad_comisionable = null;
            $unidad->importe_centavos = null;
            if (! $congelada) {
                $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_CLASIFICACION;
            }
            $unidad->save();

            return $unidad;
        }

        $comisionable = (int) $unidad->lineas->where('es_bolsa', false)->sum('cantidad');
        $unidad->cantidad_comisionable = $comisionable;

        if ($comisionable === 0) {
            $unidad->importe_centavos = 0;
            if (! $congelada) {
                $unidad->situacion = UnidadComisionEmpaque::EXCLUIDA;
            }
            $unidad->save();

            return $unidad;
        }

        $tarifa = TarifaComisionEmpaque::vigente((int) $unidad->area_id, $unidad->empacado_at);
        if (! $tarifa) {
            $unidad->importe_centavos = null;
            if (! $congelada) {
                $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_TARIFA;
            }
            $unidad->save();

            return $unidad;
        }

        $unidad->importe_centavos = $comisionable * (int) $tarifa->importe_centavos;
        if (! $congelada) {
            $unidad->situacion = UnidadComisionEmpaque::ELEGIBLE;
        }
        $unidad->save();

        return $unidad;
    }

    public function marcarTardiaSiCorresponde(UnidadComisionEmpaque $unidad): void
    {
        $cubre = CorteComisionEmpaque::query()
            ->where('area_id', $unidad->area_id)
            ->where('estado', CorteComisionEmpaque::CONFIRMADO)
            ->where('limite_desde', '<=', $unidad->empacado_at)
            ->where('limite_hasta', '>=', $unidad->empacado_at)
            ->exists();

        if ($cubre) {
            $unidad->incorporacion_tardia = true;
            $unidad->save();
        }
    }

    public function abrirIncidencia(UnidadComisionEmpaque $unidad, string $tipo, ?User $responsable, ?string $motivo, array $datos = []): void
    {
        $huella = $datos['huella'] ?? null;
        $existentes = IncidenciaComisionEmpaque::query()
            ->where('unidad_id', $unidad->id)
            ->where('tipo', $tipo)
            ->get();

        foreach ($existentes as $incidencia) {
            if ($incidencia->estado === 'pendiente') {
                return;
            }
            if ($huella && ($incidencia->datos['huella'] ?? null) === $huella && $incidencia->estado === 'resuelta') {
                return;
            }
        }

        IncidenciaComisionEmpaque::query()->create([
            'unidad_id' => $unidad->id,
            'tipo' => $tipo,
            'estado' => 'pendiente',
            'motivo' => $motivo,
            'responsable_id' => $responsable?->id,
            'datos' => $datos,
        ]);
    }
}
