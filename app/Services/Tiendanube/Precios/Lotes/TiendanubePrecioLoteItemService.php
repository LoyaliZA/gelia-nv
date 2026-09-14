<?php

namespace App\Services\Tiendanube\Precios\Lotes;

use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Models\Tiendanube\TiendanubePrecioLoteRevision;
use App\Services\Tiendanube\Precios\TiendanubePrecioImporte;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;

class TiendanubePrecioLoteItemService
{
    public function __construct(
        private readonly TiendanubePrecioLoteService $lotes,
        private readonly TiendanubePrecioLoteSimulacionService $simulacion,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function actualizar(
        string $loteId,
        int $itemId,
        int $storeId,
        int $userId,
        array $datos,
        bool $puedeVerCosto
    ): array {
        $lote = $this->lotes->obtenerAutorizado($loteId, $storeId, $userId);
        $this->lotes->asegurarEditable($lote);
        $origen = $lote->revisionActual();
        if (! $origen) {
            throw new TiendanubePrecioLoteException('El lote no tiene revisión.', 'no_encontrada', 404);
        }

        $itemOrigen = $origen->items()->where('id', $itemId)->first();
        if (! $itemOrigen) {
            throw new TiendanubePrecioLoteException('La fila no pertenece a esta revisión.', 'no_encontrada', 404);
        }

        $accion = (string) ($datos['accion'] ?? 'excluir');

        if ($accion === 'excluir' || $accion === 'reincluir') {
            $excluir = $accion === 'excluir';
            $itemOrigen->excluido = $excluir;
            $itemOrigen->exclusion_motivo = $excluir ? (string) ($datos['motivo'] ?? 'Excluida de la revisión') : null;
            $resultado = $itemOrigen->resultado_final ?? $itemOrigen->resultado_calculado ?? [];
            $itemOrigen->estado_fila = $this->simulacion->clasificar($itemOrigen, $resultado, $excluir);
            $itemOrigen->save();
            $this->simulacion->actualizarChecksumYResumen($origen->fresh());
            $this->lotes->registrarEvento(
                $lote,
                $origen,
                $excluir ? TiendanubePrecioLoteEvento::TIPO_ITEM_EXCLUIDO : TiendanubePrecioLoteEvento::TIPO_ITEM_REINCLUIDO,
                $userId,
                ['variante_id' => (int) $itemOrigen->variante_id, 'motivo' => $itemOrigen->exclusion_motivo]
            );

            return [
                'lote' => $this->lotes->payload($lote->fresh(), $userId, $puedeVerCosto),
                'item' => $this->lotes->serializarItem($itemOrigen->fresh(), $puedeVerCosto),
            ];
        }

        if ($accion !== 'ajustar') {
            throw new TiendanubePrecioLoteException('Acción no reconocida.', 'validacion');
        }

        $destino = TiendanubePrecioDestino::tryFrom((string) ($datos['destino'] ?? ''));
        if (! $destino) {
            throw new TiendanubePrecioLoteException('Indique el campo a ajustar.', 'validacion');
        }
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            throw new TiendanubePrecioLoteException('Indique un motivo para el ajuste manual.', 'validacion');
        }
        $parse = TiendanubePrecioImporte::parse((string) ($datos['valor'] ?? ''));
        if (! $parse['ok'] || $parse['valor'] === null) {
            throw new TiendanubePrecioLoteException('El importe del ajuste no es válido.', 'validacion');
        }

        $revision = $this->lotes->clonarRevision($lote, $origen, null, $userId, false);
        $item = $revision->items()->where('variante_id', $itemOrigen->variante_id)->first();
        if (! $item) {
            throw new TiendanubePrecioLoteException('No se pudo copiar la fila a la nueva revisión.', 'error', 500);
        }

        $ajustes = $item->ajustes_manuales ?? [];
        $calculado = $item->resultado_calculado['campos'][$destino->value]['valor_final'] ?? null;
        $ajustes[$destino->value] = [
            'valor' => $parse['valor'],
            'motivo' => $motivo,
            'actor_id' => $userId,
            'fecha' => now()->toIso8601String(),
            'original_calculado' => $calculado,
        ];
        $item->ajustes_manuales = $ajustes;

        $base = $item->resultado_calculado;
        if (! is_array($base)) {
            throw new TiendanubePrecioLoteException('Simule el lote antes de ajustar un resultado.', 'estado_invalido');
        }
        $final = $this->simulacion->aplicarAjustesAlResultado($base, $ajustes, $item->load('revision.lote'));
        $item->resultado_calculado = $base;
        $item->ajustes_manuales = $ajustes;
        $item->resultado_final = $final;
        $item->errores = $final['errores'] ?? [];
        $item->validaciones = [
            'publicable' => (bool) ($final['publicable'] ?? false),
            'margen_estimado' => $final['margen_estimado'] ?? null,
            'diferencia_absoluta' => $final['diferencia_absoluta'] ?? null,
            'variacion_porcentual' => $final['variacion_porcentual'] ?? null,
        ];
        $item->estado_fila = $this->simulacion->clasificar($item, $final, (bool) $item->excluido);
        $item->save();

        $this->simulacion->actualizarChecksumYResumen($revision->fresh());
        $lote->update(['estado' => TiendanubePrecioLote::ESTADO_SIMULADO]);
        $revision->update(['estado' => TiendanubePrecioLoteRevision::ESTADO_SIMULADO]);
        $this->lotes->registrarEvento(
            $lote,
            $revision,
            TiendanubePrecioLoteEvento::TIPO_ITEM_EDITADO,
            $userId,
            ['variante_id' => (int) $item->variante_id, 'destino' => $destino->value, 'motivo' => $motivo]
        );

        return [
            'lote' => $this->lotes->payload($lote->fresh(), $userId, $puedeVerCosto),
            'item' => $this->lotes->serializarItem($item->fresh(), $puedeVerCosto),
        ];
    }
}
