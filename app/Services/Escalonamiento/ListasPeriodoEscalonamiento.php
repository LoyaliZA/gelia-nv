<?php

namespace App\Services\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoReglaVersion;
use Illuminate\Support\Collection;

class ListasPeriodoEscalonamiento
{
    public function __construct(
        private ConstruirSnapshotListasEscalonamiento $construirSnapshot,
    ) {}

    /**
     * @return Collection<int, CatalogoListaDescuento>
     */
    public function listasParticipantes(EscalonamientoPeriodo $periodo): Collection
    {
        return $this->filasSnapshot($periodo)
            ->filter(fn (array $fila) => ($fila['participa_escalonamiento'] ?? false) && ($fila['activo'] ?? false))
            ->map(fn (array $fila) => $this->filaALista($fila))
            ->sortByDesc(fn (CatalogoListaDescuento $lista) => (float) $lista->monto_requerido)
            ->values();
    }

    public function montoRequerido(EscalonamientoPeriodo $periodo, ?int $listaId): ?string
    {
        if (! $listaId) {
            return null;
        }

        foreach ($this->filasSnapshot($periodo) as $fila) {
            if ((int) ($fila['id'] ?? 0) !== $listaId) {
                continue;
            }
            if ($fila['monto_requerido'] === null) {
                return null;
            }

            return bcadd((string) $fila['monto_requerido'], '0', 2);
        }

        return null;
    }

    public function mejorListaId(EscalonamientoPeriodo $periodo, ?int $actualId, ?int $candidataId): ?int
    {
        if (! $candidataId) {
            return $actualId;
        }
        if (! $actualId || $actualId === $candidataId) {
            return $candidataId;
        }

        $maximo = $this->montoRequerido($periodo, $actualId);
        $candidata = $this->montoRequerido($periodo, $candidataId);
        if ($maximo === null || ($candidata !== null && bccomp($candidata, $maximo, 2) > 0)) {
            return $candidataId;
        }

        return $actualId;
    }

    public function clasificacionBajo(EscalonamientoPeriodo $periodo, ?int $maxId, ?int $nuevaId): bool
    {
        return $this->compararJerarquia($periodo, $nuevaId, $maxId) < 0;
    }

    /**
     * Jerarquía por monto requerido del período (no por ID de fila en catálogo).
     *
     * @return int negativo si A es tier inferior a B, positivo si A es superior, 0 si iguales o indeterminado
     */
    public function compararJerarquia(EscalonamientoPeriodo $periodo, ?int $listaAId, ?int $listaBId): int
    {
        if (! $listaAId || ! $listaBId || $listaAId === $listaBId) {
            return 0;
        }

        $montoA = $this->montoRequerido($periodo, $listaAId);
        $montoB = $this->montoRequerido($periodo, $listaBId);

        if ($montoA === null && $montoB === null) {
            return 0;
        }
        if ($montoA === null) {
            return -1;
        }
        if ($montoB === null) {
            return 1;
        }

        return bccomp($montoA, $montoB, 2);
    }

    public function listaParticipaEnPeriodo(EscalonamientoPeriodo $periodo, int $listaId): bool
    {
        foreach ($this->filasSnapshot($periodo) as $fila) {
            if ((int) ($fila['id'] ?? 0) !== $listaId) {
                continue;
            }

            return (bool) ($fila['participa_escalonamiento'] ?? false)
                && (bool) ($fila['activo'] ?? false);
        }

        return false;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function filasSnapshot(EscalonamientoPeriodo $periodo): Collection
    {
        // Misma regla que ParticipacionClienteEscalonamiento: catálogo vigente mientras el período admite movimientos.
        if ($periodo->permiteEscrituraMovimientos()) {
            return collect($this->construirSnapshot->desdeCatalogo());
        }

        $versionId = (int) ($periodo->escalonamiento_regla_version_id ?? 0);
        $snapshot = $versionId > 0
            ? EscalonamientoReglaVersion::query()->whereKey($versionId)->value('snapshot')
            : null;

        if (! is_array($snapshot)) {
            return collect();
        }

        return collect($snapshot)->filter(fn ($fila) => is_array($fila))->values();
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function filaALista(array $fila): CatalogoListaDescuento
    {
        $lista = new CatalogoListaDescuento;
        $lista->forceFill([
            'id' => (int) ($fila['id'] ?? 0),
            'nombre' => (string) ($fila['nombre'] ?? ''),
            'monto_requerido' => $fila['monto_requerido'],
            'porcentaje_descuento' => $fila['porcentaje_descuento'] ?? null,
            'monto_minimo' => $fila['monto_minimo'] ?? null,
            'monto_maximo' => $fila['monto_maximo'] ?? null,
            'activo' => (bool) ($fila['activo'] ?? false),
            'participa_escalonamiento' => (bool) ($fila['participa_escalonamiento'] ?? false),
        ]);
        $lista->exists = true;

        return $lista;
    }
}
