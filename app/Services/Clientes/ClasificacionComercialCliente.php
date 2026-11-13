<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteModalidadEvento;

class ClasificacionComercialCliente
{
    public const ORIGEN_IMPORTACION = 'importacion';

    public const ORIGEN_MIGRACION = 'migracion_conservadora';

    public const ORIGEN_CONVERSION = 'conversion_autorizada';

    public const ORIGEN_REVERSION = 'reversion';

    public const ORIGEN_ADMINISTRACION = 'administracion';

    public static function bandera(mixed $valor): int
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    public function esMenudeo(Cliente $cliente): bool
    {
        return (bool) $cliente->es_menudeo;
    }

    public function esListaMayorista(?CatalogoListaDescuento $lista): bool
    {
        return str_starts_with(strtoupper(trim((string) $lista?->nombre)), 'MAYOREO ');
    }

    public function esPublicoGeneral(?CatalogoListaDescuento $lista): bool
    {
        return strtoupper(trim((string) $lista?->nombre)) === 'PUBLICO GENERAL';
    }

    public function conversionVigente(Cliente $cliente): bool
    {
        return ! $this->esMenudeo($cliente)
            && $cliente->modalidad_origen === self::ORIGEN_CONVERSION
            && (bool) $cliente->conversion_solicitud_id;
    }

    public function asignar(Cliente $cliente, bool $esMenudeo, string $origen, ?int $solicitudId = null, ?int $usuarioId = null): void
    {
        $cliente->es_menudeo = $esMenudeo;
        $cliente->modalidad_origen = $origen;
        if ($origen === self::ORIGEN_CONVERSION && ! $esMenudeo) {
            $cliente->conversion_solicitud_id = $solicitudId;
            $cliente->conversion_usuario_id = $usuarioId;
            $cliente->conversion_autorizada_at = now();

            return;
        }

        $cliente->conversion_solicitud_id = null;
        $cliente->conversion_usuario_id = null;
        $cliente->conversion_autorizada_at = null;
    }

    public function registrarEvento(Cliente $cliente, ?int $usuarioId, ?int $solicitudId): void
    {
        if (! $cliente->id || $cliente->modalidad_origen === null) {
            return;
        }

        ClienteModalidadEvento::query()->create([
            'cliente_id' => $cliente->id,
            'es_menudeo' => $this->esMenudeo($cliente),
            'origen' => $cliente->modalidad_origen,
            'solicitud_id' => $solicitudId,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * El código explícito fija la lista de un cliente nuevo o sin condición de menudeo.
     * Un menudeo sin conversión no adopta una lista mayorista. El monto no asciende.
     */
    public function aplicarEnImportacion(Cliente $cliente, CatalogoListaDescuento $listaCodigo, ?string $monto): ?string
    {
        if ($this->esMenudeo($cliente) && ! $this->conversionVigente($cliente) && $this->esListaMayorista($listaCodigo)) {
            return 'lista_mayorista_sin_conversion';
        }
        if ($this->conversionVigente($cliente) && $this->esPublicoGeneral($listaCodigo)) {
            return 'conversion_autorizada';
        }

        $cliente->lista_actual_id = $listaCodigo->id;
        if (
            ! $this->conversionVigente($cliente)
            && $this->esPublicoGeneral($listaCodigo)
            && $monto !== null
            && bccomp($monto, '0', 2) > 0
        ) {
            $this->asignar($cliente, true, self::ORIGEN_IMPORTACION);
        }

        return null;
    }

    public function idPublicoGeneral(): ?int
    {
        $id = CatalogoListaDescuento::query()
            ->whereIn('nombre', ['PUBLICO GENERAL', 'Público General'])
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function buscarBronce(): ?CatalogoListaDescuento
    {
        return CatalogoListaDescuento::query()
            ->where('nombre', 'MAYOREO BRONCE')
            ->where('activo', true)
            ->first();
    }

    public function listaEfectivaId(Cliente $cliente): ?int
    {
        if ($cliente->es_inactivo || $this->esMenudeo($cliente)) {
            return $this->idPublicoGeneral() ?? ($cliente->lista_actual_id ? (int) $cliente->lista_actual_id : null);
        }

        return $cliente->lista_actual_id ? (int) $cliente->lista_actual_id : null;
    }

    /**
     * @return array{es_menudeo: bool, modalidad_origen: ?string, conversion_autorizada: bool, lista_almacenada_id: ?int, lista_almacenada_nombre: ?string, lista_efectiva_id: ?int, lista_efectiva_nombre: ?string}
     */
    public function presentacion(Cliente $cliente): array
    {
        $cliente->loadMissing('listaDescuento');
        $efectivaId = $this->listaEfectivaId($cliente);
        $efectivaNombre = null;
        if ($efectivaId) {
            $efectivaNombre = (int) $cliente->lista_actual_id === $efectivaId
                ? $cliente->listaDescuento?->nombre
                : CatalogoListaDescuento::query()->whereKey($efectivaId)->value('nombre');
        }

        return [
            'es_menudeo' => $this->esMenudeo($cliente),
            'es_inactivo' => (bool) $cliente->es_inactivo,
            'modalidad_origen' => $cliente->modalidad_origen,
            'conversion_autorizada' => $this->conversionVigente($cliente),
            'lista_almacenada_id' => $cliente->lista_actual_id ? (int) $cliente->lista_actual_id : null,
            'lista_almacenada_nombre' => $cliente->listaDescuento?->nombre,
            'lista_efectiva_id' => $efectivaId,
            'lista_efectiva_nombre' => $efectivaNombre,
        ];
    }
};
