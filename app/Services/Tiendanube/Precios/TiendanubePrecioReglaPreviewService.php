<?php

namespace App\Services\Tiendanube\Precios;

use App\Exceptions\Tiendanube\TiendanubePrecioReglaException;
use App\Exceptions\Tiendanube\TiendanubePrecioSeleccionException;
use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Support\Tiendanube\Precios\TiendanubePrecioCampoCondicion;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;

final class TiendanubePrecioReglaPreviewService
{
    public const MUESTRA_MAX = 20;

    public function __construct(
        private readonly ?TiendanubePrecioSeleccionService $selecciones = null,
        private readonly ?TiendanubePrecioFuenteResolverService $fuentes = null,
        private readonly ?TiendanubePrecioMotorCalculoService $motor = null,
        private readonly ?TiendanubePrecioReglaService $reglas = null,
        private readonly ?TiendanubePrecioReglaVersionService $versiones = null,
    ) {}

    private function selecciones(): TiendanubePrecioSeleccionService
    {
        return $this->selecciones ?? app(TiendanubePrecioSeleccionService::class);
    }

    private function fuentes(): TiendanubePrecioFuenteResolverService
    {
        return $this->fuentes ?? app(TiendanubePrecioFuenteResolverService::class);
    }

    private function motor(): TiendanubePrecioMotorCalculoService
    {
        return $this->motor ?? app(TiendanubePrecioMotorCalculoService::class);
    }

    private function reglas(): TiendanubePrecioReglaService
    {
        return $this->reglas ?? app(TiendanubePrecioReglaService::class);
    }

    private function versiones(): TiendanubePrecioReglaVersionService
    {
        return $this->versiones ?? app(TiendanubePrecioReglaVersionService::class);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function previsualizar(
        int $storeId,
        int $userId,
        array $datos,
        bool $puedeVerCosto
    ): array {
        $selectionId = (string) ($datos['selection_id'] ?? '');
        if ($selectionId === '') {
            throw new TiendanubePrecioReglaException('Se requiere una selección de catálogo.', 'validacion', 422);
        }

        try {
            $resolucion = $this->selecciones()->resolver($selectionId, $storeId, $userId, 1, self::MUESTRA_MAX);
        } catch (TiendanubePrecioSeleccionException $e) {
            throw new TiendanubePrecioReglaException($e->getMessage(), $e->codigo, $e->httpStatus, [], $e);
        }

        $varianteIds = $resolucion['variante_ids'] ?? [];
        $definicion = $this->resolverDefinicion($datos, $storeId);
        $this->versiones()->validarDefinicion($definicion);
        $this->versiones()->validarListasTienda($storeId, $definicion);

        $tipos = $this->tiposNecesarios($definicion);
        $fuentesResueltas = $this->fuentes()->resolver($storeId, $varianteIds, $tipos);
        $fuentesIndex = collect($fuentesResueltas)->keyBy('variante_id');

        $variantes = TiendanubeProductoVariante::query()
            ->with('producto:id,nombre')
            ->whereIn('id', $varianteIds)
            ->get()
            ->keyBy('id');

        $filas = [];
        foreach ($varianteIds as $varianteId) {
            $variante = $variantes->get($varianteId);
            $fuenteRow = $fuentesIndex->get($varianteId);
            if (! $variante || ! $fuenteRow) {
                continue;
            }

            $snapshot = [
                'tienda_id' => $storeId,
                'producto_id' => (int) $variante->producto_id,
                'variante_id' => $varianteId,
                'fuentes' => $fuenteRow['fuentes'] ?? [],
            ];

            $resultado = $this->motor()->calcular($snapshot, [$definicion]);
            $fila = $this->serializarFila($variante, $definicion, $resultado, $fuenteRow, $puedeVerCosto);
            $filas[] = $fila;
        }

        return [
            'mensaje' => 'Muestra; aún no revisada para aplicar',
            'muestra_max' => self::MUESTRA_MAX,
            'total_seleccion' => (int) ($resolucion['total_variantes'] ?? 0),
            'mostradas' => count($filas),
            'definicion' => $definicion,
            'contract_version' => TiendanubePrecioMotorCalculoService::MOTOR_VERSION,
            'filas' => $filas,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function resolverDefinicion(array $datos, int $storeId): array
    {
        if (isset($datos['definicion']) && is_array($datos['definicion'])) {
            return $datos['definicion'];
        }

        $reglaId = isset($datos['regla_id']) ? (int) $datos['regla_id'] : 0;
        if ($reglaId <= 0) {
            throw new TiendanubePrecioReglaException('Se requiere definición o regla_id.', 'validacion', 422);
        }

        $regla = $this->reglas()->buscar($reglaId, $storeId);
        $regla->loadMissing('versionActual');
        $definicion = $regla->versionActual?->definicion;
        if (! is_array($definicion)) {
            throw new TiendanubePrecioReglaException('La regla no tiene versión utilizable.', 'no_encontrada', 404);
        }

        return $definicion;
    }

    /**
     * @param  array<string, mixed>  $definicion
     * @return list<string>
     */
    private function tiposNecesarios(array $definicion): array
    {
        $tipos = [
            TiendanubePrecioFuenteVersion::TIPO_PRECIO_NORMAL_ACTUAL,
            TiendanubePrecioFuenteVersion::TIPO_PRECIO_PROMOCIONAL_ACTUAL,
            TiendanubePrecioFuenteVersion::TIPO_COSTO_REMOTO_ACTUAL,
            TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
            TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA,
        ];

        return array_values(array_unique($tipos));
    }

    /**
     * @param  array<string, mixed>  $fuenteRow
     * @return array<string, mixed>
     */
    private function serializarFila(
        TiendanubeProductoVariante $variante,
        array $definicion,
        TiendanubePrecioMotorResultadoDto $resultado,
        array $fuenteRow,
        bool $puedeVerCosto
    ): array {
        $destino = TiendanubePrecioDestino::from((string) ($definicion['destino'] ?? 'normal'));
        $campoDestino = $destino->campoSnapshot();
        $antes = $this->valorFuente($fuenteRow, $campoDestino->value, $definicion['base_lista_id'] ?? null);

        $baseCampo = TiendanubePrecioCampoCondicion::from((string) ($definicion['base'] ?? ''));
        $baseListaId = isset($definicion['base_lista_id']) ? (int) $definicion['base_lista_id'] : null;
        $baseValor = $this->valorFuente($fuenteRow, $baseCampo->value, $baseListaId);
        $baseFaltante = $baseValor === null;

        $campoResultado = $resultado->campo($destino);
        $despues = $campoResultado?->valorFinal;

        $fila = [
            'variante_id' => (int) $variante->id,
            'producto_id' => (int) $variante->producto_id,
            'nombre' => $variante->producto instanceof TiendanubeProducto ? $variante->producto->nombre : null,
            'sku' => $variante->sku,
            'base' => $definicion['base'] ?? null,
            'base_lista_id' => $baseListaId,
            'base_faltante' => $baseFaltante,
            'antes' => $antes,
            'despues' => $despues,
            'destino' => $destino->value,
            'publicable' => $resultado->publicable,
            'errores' => $resultado->errores,
            'explicacion' => $campoResultado?->explicacion,
        ];

        if (! $puedeVerCosto) {
            if ($baseCampo->esCosto()) {
                $fila['base_faltante'] = true;
                unset($fila['antes'], $fila['despues']);
            }
            if ($destino === TiendanubePrecioDestino::CostoRemoto) {
                unset($fila['antes'], $fila['despues']);
            }
        }

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $fuenteRow
     */
    private function valorFuente(array $fuenteRow, string $tipo, ?int $listaId): ?string
    {
        foreach ($fuenteRow['fuentes'] ?? [] as $fuente) {
            if (($fuente['tipo'] ?? '') !== $tipo) {
                continue;
            }
            if ($tipo === TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA
                && (int) ($fuente['lista_id'] ?? 0) !== (int) $listaId) {
                continue;
            }
            if (! empty($fuente['faltante'])) {
                return null;
            }

            return $fuente['valor_decimal'] ?? null;
        }

        return null;
    }
}
