<?php

namespace App\Services\Tiendanube\Precios\Lotes;

use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Models\Tiendanube\TiendanubePrecioLoteItem;
use App\Models\Tiendanube\TiendanubePrecioLoteRevision;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;

class TiendanubePrecioLoteAprobacionService
{
    public function __construct(
        private readonly TiendanubePrecioLoteService $lotes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function aprobar(
        string $loteId,
        int $storeId,
        int $userId,
        string $checksum,
        bool $puedeAprobar,
        bool $puedeVerCosto
    ): array {
        if (! config('tiendanube.precios_aprobacion_habilitada', true)) {
            throw new TiendanubePrecioLoteException('La aprobación no está habilitada.', 'deshabilitado', 403);
        }
        if (! $puedeAprobar) {
            throw new TiendanubePrecioLoteException('No tiene permiso para aprobar revisiones masivas.', 'sin_permiso', 403);
        }

        $lote = $this->lotes->obtenerAutorizado($loteId, $storeId, $userId);
        $revision = $lote->revisionActual();
        if (! $revision) {
            throw new TiendanubePrecioLoteException('El lote no tiene revisión.', 'no_encontrada', 404);
        }

        if ($lote->estado === TiendanubePrecioLote::ESTADO_APROBADO
            && $revision->estado === TiendanubePrecioLoteRevision::ESTADO_APROBADO
            && hash_equals((string) $revision->checksum, $checksum)) {
            return $this->lotes->payload($lote, $userId, $puedeVerCosto);
        }

        if ($lote->estado === TiendanubePrecioLote::ESTADO_CANCELADO) {
            throw new TiendanubePrecioLoteException('El lote está cancelado.', 'estado_invalido');
        }
        if ($lote->estado !== TiendanubePrecioLote::ESTADO_SIMULADO) {
            throw new TiendanubePrecioLoteException('Simule el lote antes de aprobar.', 'estado_invalido');
        }
        if (! hash_equals((string) $revision->checksum, $checksum)) {
            throw new TiendanubePrecioLoteException(
                'La revisión cambió. Recargue e intente de nuevo.',
                'conflicto_checksum',
                409
            );
        }

        $resumen = $revision->resumen ?? [];
        if (empty($resumen['puede_aprobar'])) {
            throw new TiendanubePrecioLoteException(
                (string) ($resumen['motivo_sin_aprobacion'] ?? 'No hay cambios válidos para aprobar.'),
                'sin_cambios_validos'
            );
        }

        $revision->update([
            'estado' => TiendanubePrecioLoteRevision::ESTADO_APROBADO,
            'aprobado_por' => $userId,
            'aprobado_at' => now(),
        ]);
        $lote->update(['estado' => TiendanubePrecioLote::ESTADO_APROBADO]);
        $this->lotes->registrarEvento(
            $lote,
            $revision,
            TiendanubePrecioLoteEvento::TIPO_REVISION_APROBADA,
            $userId,
            [
                'checksum' => $revision->checksum,
                'variantes_normal' => $resumen['variantes_normal'] ?? 0,
                'variantes_promo' => $resumen['variantes_promo'] ?? 0,
                'variantes_costo' => $resumen['variantes_costo'] ?? 0,
                'excluidas' => $resumen['excluidas'] ?? 0,
            ]
        );

        return $this->lotes->payload($lote->fresh(), $userId, $puedeVerCosto);
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerRevisionAprobada(string $loteId, int $storeId, int $userId, ?int $revisionId = null): array
    {
        $lote = $this->lotes->obtenerAutorizado($loteId, $storeId, $userId);
        $revision = $revisionId
            ? $lote->revisiones()->where('id', $revisionId)->first()
            : $lote->revisionActual();

        if (! $revision || $revision->estado !== TiendanubePrecioLoteRevision::ESTADO_APROBADO) {
            throw new TiendanubePrecioLoteException('No hay una revisión aprobada para consultar.', 'no_encontrada', 404);
        }

        $items = $revision->items()->orderBy('variante_id')->get()->map(function (TiendanubePrecioLoteItem $item) {
            $campos = [];
            foreach (TiendanubePrecioDestino::cases() as $destino) {
                $clave = $destino->value;
                $campo = $item->resultado_final['campos'][$clave] ?? [];
                $campos[$clave] = [
                    'intencion' => $campo['intencion'] ?? TiendanubePrecioIntencion::Conservar->value,
                    'valor_final' => $campo['valor_final'] ?? null,
                    'valor_anterior' => ($item->valores_anteriores ?? [])[$clave === 'costo_remoto' ? 'costo_remoto' : $clave] ?? null,
                    'regla_id' => $campo['regla_id'] ?? null,
                    'ajuste_manual' => ($item->ajustes_manuales ?? [])[$clave] ?? null,
                ];
            }

            return [
                'producto_id' => (int) $item->producto_id,
                'variante_id' => (int) $item->variante_id,
                'sku' => $item->variante_sku,
                'nombre' => $item->producto_nombre,
                'atributos' => $item->variante_atributos ?? [],
                'imagen_url' => $item->imagen_url,
                'excluido' => (bool) $item->excluido,
                'exclusion_motivo' => $item->exclusion_motivo,
                'publicable' => (bool) ($item->validaciones['publicable'] ?? false) && ! $item->excluido,
                'campos' => $campos,
                'valores_anteriores' => $item->valores_anteriores,
            ];
        })->all();

        return [
            'lote_id' => $lote->id,
            'revision_id' => $revision->id,
            'revision_numero' => (int) $revision->numero,
            'checksum' => $revision->checksum,
            'huella_conflicto_remoto' => $revision->huella_conflicto_remoto,
            'store_id' => (int) $lote->store_id,
            'config_generation' => (int) $lote->config_generation,
            'api_version' => $lote->api_version,
            'motor_contract_version' => $lote->motor_contract_version,
            'moneda' => $lote->moneda,
            'fecha_lectura' => $lote->fecha_lectura?->toIso8601String(),
            'aprobado_at' => $revision->aprobado_at?->toIso8601String(),
            'aprobado_por' => $revision->aprobado_por,
            'definicion' => $revision->definicion,
            'resumen' => $revision->resumen,
            'items' => $items,
        ];
    }
}
