<?php

namespace App\Services\Clientes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\Cliente;
use App\Models\SolicitudBeneficio;
use App\Models\SolicitudTag;

/** Una solicitud solo frena el dato que de verdad reemplazaría. */
class ProteccionComercialImportacion
{
    /**
     * @return array{conversion: bool, reactivacion: bool, solicitud_id: ?int, motivo: ?string}
     */
    public function evaluar(Cliente $cliente, ?int $listaPropuestaId, ?bool $menudeoPropuesto, bool $quiereReactivar): array
    {
        $vacio = ['conversion' => false, 'reactivacion' => false, 'solicitud_id' => null, 'motivo' => null];
        if (! $cliente->id) {
            return $vacio;
        }

        $beneficios = SolicitudBeneficio::query()
            ->where('cliente_id', $cliente->id)
            ->where('estado', SolicitudBeneficio::ESTADO_VIGENTE)
            ->get();
        foreach ($beneficios as $beneficio) {
            if ($beneficio->campo === SolicitudBeneficio::CAMPO_LISTA && $listaPropuestaId !== null
                && (string) $beneficio->valor_aplicado !== (string) $listaPropuestaId) {
                return ['conversion' => true, 'reactivacion' => false, 'solicitud_id' => (int) $beneficio->solicitud_tag_id, 'motivo' => 'Una autorización vigente sostiene otra lista.'];
            }
            if ($beneficio->campo === SolicitudBeneficio::CAMPO_MODALIDAD && $menudeoPropuesto !== null
                && (string) $beneficio->valor_aplicado !== ($menudeoPropuesto ? '1' : '0')) {
                return ['conversion' => true, 'reactivacion' => false, 'solicitud_id' => (int) $beneficio->solicitud_tag_id, 'motivo' => 'Una autorización vigente sostiene otra modalidad.'];
            }
        }

        $pago = $this->pagoPendiente($cliente);
        if (! $pago) {
            return $vacio;
        }
        $listaDistinta = $pago->catalogo_lista_descuento_id && $listaPropuestaId !== null
            && (int) $pago->catalogo_lista_descuento_id !== $listaPropuestaId;
        $conversionDistinta = $pago->convertir_menudeo_mayoreo && $menudeoPropuesto === true;
        if ($listaDistinta || $conversionDistinta) {
            return ['conversion' => true, 'reactivacion' => false, 'solicitud_id' => (int) $pago->id, 'motivo' => 'Hay un pago pendiente que sostiene otra clasificación.'];
        }
        if ($quiereReactivar && $cliente->es_inactivo && ! $pago->catalogo_lista_descuento_id && ! $pago->convertir_menudeo_mayoreo) {
            return ['conversion' => false, 'reactivacion' => true, 'solicitud_id' => (int) $pago->id, 'motivo' => 'Hay un pago pendiente. La reactivación queda en conflicto.'];
        }

        return $vacio;
    }

    private function pagoPendiente(Cliente $cliente): ?SolicitudTag
    {
        $estados = array_filter([
            CatalogoEstadoSolicitud::idDe('Respondida'),
            CatalogoEstadoSolicitud::idDe('Verificada'),
        ]);
        if ($estados === []) {
            return null;
        }

        return SolicitudTag::query()
            ->where('cliente_id', $cliente->id)
            ->whereIn('catalogo_estado_solicitud_id', $estados)
            ->where('pago_confirmado', false)
            ->where(function ($consulta) {
                $consulta->whereNull('motivo_incorrecta')->orWhere('motivo_incorrecta', '!=', 'vencimiento_pago');
            })
            ->where(function ($consulta) {
                $consulta->where('compra_en_tienda', false)->orWhereNull('compra_en_tienda');
            })
            ->where(function ($consulta) {
                $consulta->where('compra_en_tienda_solo_tag', false)->orWhereNull('compra_en_tienda_solo_tag');
            })
            ->first();
    }
}
