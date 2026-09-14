<?php

namespace App\Services\Tiendanube\Precios\Historial;

use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Exceptions\Tiendanube\TiendanubePrecioSeleccionException;
use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteEvento;
use App\Models\Tiendanube\TiendanubePrecioLoteItem;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioEjecucionControlRemotoService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteService;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteSimulacionService;
use App\Services\Tiendanube\Precios\TiendanubePrecioDecimal;
use App\Services\Tiendanube\Precios\TiendanubePrecioSeleccionService;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;
use Illuminate\Support\Facades\DB;

class TiendanubePrecioRestauracionService
{
    public function __construct(
        private readonly TiendanubePrecioHistorialQueryService $historial,
        private readonly TiendanubePrecioLoteService $lotes,
        private readonly TiendanubePrecioLoteSimulacionService $simulacion,
        private readonly TiendanubePrecioSeleccionService $selecciones,
        private readonly TiendanubeApiClient $api,
        private readonly TiendanubePrecioEjecucionControlRemotoService $control,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function preparar(string $loteId, int $storeId, int $userId, array $datos, bool $puedeVerCosto): array
    {
        $this->asegurarHabilitada();
        $lote = $this->historial->obtenerDeTienda($loteId, $storeId);
        $this->asegurarRestaurable($lote);

        return $this->evaluar($lote, $datos, $puedeVerCosto, false);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function confirmar(string $loteId, int $storeId, int $userId, array $datos, bool $puedeVerCosto): array
    {
        $this->asegurarHabilitada();
        $lote = $this->historial->obtenerDeTienda($loteId, $storeId);
        $this->asegurarRestaurable($lote);

        $preview = $this->evaluar($lote, $datos, $puedeVerCosto, true);
        $aceptarConflicto = (bool) ($datos['aceptar_conflicto'] ?? false);
        if ($preview['tiene_conflictos'] && ! $aceptarConflicto) {
            throw new TiendanubePrecioLoteException(
                'Hay cambios posteriores. Revise el conflicto antes de crear la compensación.',
                'conflicto_posterior',
                409,
                ['filas' => $preview['filas']]
            );
        }

        $elegibles = array_values(array_filter(
            $preview['filas'],
            fn (array $fila) => $fila['restaurable'] && $fila['campos_elegidos'] !== []
        ));
        if ($elegibles === []) {
            throw new TiendanubePrecioLoteException(
                'No hay campos restaurables en la selección.',
                'sin_campos_restaurables'
            );
        }

        $varianteIds = array_map(fn (array $fila) => (int) $fila['variante_id'], $elegibles);

        try {
            $seleccion = $this->selecciones->crear($storeId, $userId, [
                'modo' => 'pagina',
                'variante_ids' => $varianteIds,
            ], $puedeVerCosto);
        } catch (TiendanubePrecioSeleccionException $e) {
            throw new TiendanubePrecioLoteException($e->getMessage(), $e->codigo, $e->httpStatus, [], $e);
        }

        $revisionOrigen = $lote->revisionActual();
        $definicion = is_array($revisionOrigen?->definicion) ? $revisionOrigen->definicion : null;
        if (! is_array($definicion)) {
            throw new TiendanubePrecioLoteException('La operación original no tiene definición utilizable.', 'estado_invalido');
        }

        $this->lotes->registrarEvento(
            $lote,
            $revisionOrigen,
            TiendanubePrecioLoteEvento::TIPO_COMPENSACION_SOLICITADA,
            $userId,
            ['variantes' => $varianteIds]
        );

        $compensacion = $this->lotes->crear($storeId, $userId, [
            'selection_id' => $seleccion['selection_id'],
            'selection_version' => $seleccion['version'],
            'definicion' => $definicion,
            'origen' => TiendanubePrecioLote::ORIGEN_RESTAURACION,
            'lote_origen_id' => $lote->id,
            'motivo_restauracion' => (string) ($datos['motivo'] ?? 'Restauración compensatoria'),
        ]);

        $modelo = TiendanubePrecioLote::query()->find($compensacion['lote_id']);
        if (! $modelo) {
            throw new TiendanubePrecioLoteException('No se pudo crear la compensación.', 'error', 500);
        }

        $this->aplicarIntenciones($modelo, $elegibles);
        $this->lotes->registrarEvento(
            $modelo,
            $modelo->revisionActual(),
            TiendanubePrecioLoteEvento::TIPO_COMPENSACION_CREADA,
            $userId,
            ['lote_origen_id' => $lote->id]
        );

        return [
            'lote_origen_id' => $lote->id,
            'lote' => $this->lotes->payload($modelo->fresh(['revisiones.simulacion']), $userId, $puedeVerCosto),
        ];
    }

    private function asegurarHabilitada(): void
    {
        if (! config('tiendanube.precios_restauracion_habilitada', true)) {
            throw new TiendanubePrecioLoteException(
                'La restauración está desactivada. El historial de auditoría sigue disponible.',
                'restauracion_deshabilitada',
                403
            );
        }
    }

    private function asegurarRestaurable(TiendanubePrecioLote $lote): void
    {
        if ($lote->estado !== TiendanubePrecioLote::ESTADO_APROBADO) {
            throw new TiendanubePrecioLoteException(
                'Solo se restauran operaciones con revisión aprobada.',
                'estado_invalido'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function evaluar(TiendanubePrecioLote $lote, array $datos, bool $puedeVerCosto, bool $estricto): array
    {
        $revision = $lote->revisionActual();
        if (! $revision) {
            throw new TiendanubePrecioLoteException('El lote no tiene revisión.', 'no_encontrada', 404);
        }

        $contexto = $this->historial->derivarEvidencia($lote, [
            'ejecucion' => $lote->ejecuciones()->orderByDesc('created_at')->first(),
            'csv' => $lote->csvArtefactos()->orderByDesc('id')->get(),
        ]);
        $incierta = in_array($contexto['codigo'], [
            TiendanubePrecioHistorialQueryService::EVIDENCIA_ARCHIVO_DESCARGADO,
            TiendanubePrecioHistorialQueryService::EVIDENCIA_CSV_GENERADO,
            TiendanubePrecioHistorialQueryService::EVIDENCIA_APROBADO,
            TiendanubePrecioHistorialQueryService::EVIDENCIA_POR_VERIFICAR,
        ], true);
        $tieneConciliacion = $lote->conciliaciones()->exists();
        if ($incierta && ! $tieneConciliacion && $estricto) {
            throw new TiendanubePrecioLoteException(
                'Concilié primero esta operación. Una descarga o una aprobación sin entrega no alcanza para restaurar.',
                'evidencia_insuficiente'
            );
        }

        $seleccion = $this->normalizarSeleccion($datos['filas'] ?? []);
        $items = $revision->items()->where('excluido', false)->get()->keyBy(fn (TiendanubePrecioLoteItem $i) => (int) $i->variante_id);
        if ($seleccion === []) {
            $seleccion = $items->mapWithKeys(fn (TiendanubePrecioLoteItem $item) => [
                (int) $item->variante_id => array_map(
                    fn (TiendanubePrecioDestino $d) => $d->value,
                    TiendanubePrecioDestino::cases()
                ),
            ])->all();
        }

        $filas = [];
        $tieneConflictos = false;
        $cache = [];
        foreach ($seleccion as $varianteId => $campos) {
            $item = $items->get($varianteId);
            if (! $item) {
                continue;
            }
            $fila = $this->evaluarItem($lote, $item, $campos, $cache, $puedeVerCosto);
            if ($fila['conflicto']) {
                $tieneConflictos = true;
            }
            $filas[] = $fila;
        }

        return [
            'lote_id' => $lote->id,
            'evidencia' => $contexto,
            'requiere_conciliacion' => $incierta && ! $tieneConciliacion,
            'tiene_conflictos' => $tieneConflictos,
            'filas' => $filas,
        ];
    }

    /**
     * @param  list<string>  $campos
     * @param  array<int, array<string, mixed>>  $cache
     * @return array<string, mixed>
     */
    private function evaluarItem(
        TiendanubePrecioLote $lote,
        TiendanubePrecioLoteItem $item,
        array $campos,
        array &$cache,
        bool $puedeVerCosto
    ): array {
        $espejo = TiendanubeProductoVariante::query()->find((int) $item->variante_id);
        $tiendaOk = $espejo && (int) $lote->store_id === (int) $lote->store_id;
        $identidadOk = $espejo && (int) $espejo->producto_id === (int) $item->producto_id;

        $detalle = [
            'item_id' => $item->id,
            'variante_id' => (int) $item->variante_id,
            'producto_id' => (int) $item->producto_id,
            'nombre' => $item->producto_nombre,
            'sku' => $item->variante_sku,
            'atributos' => $item->variante_atributos ?? [],
            'restaurable' => true,
            'conflicto' => false,
            'motivo_bloqueo' => null,
            'campos' => [],
            'campos_elegidos' => [],
        ];

        if (! $espejo || ! $identidadOk || ! $tiendaOk) {
            $detalle['restaurable'] = false;
            $detalle['motivo_bloqueo'] = 'El producto o la variante histórica ya no está en esta tienda. No se elige otro destino por SKU.';

            return $detalle;
        }

        $productoId = (int) $item->producto_id;
        if (! isset($cache[$productoId])) {
            $cache[$productoId] = $this->api->consultarProducto($productoId);
        }
        $consulta = $cache[$productoId];
        if ($consulta['estado'] !== 'existe' || ! is_array($consulta['recurso'])) {
            $detalle['restaurable'] = false;
            $detalle['motivo_bloqueo'] = $consulta['estado'] === 'ausente'
                ? 'El producto desapareció en TiendaNube. Se conservan nombre y atributos históricos.'
                : 'No se pudo leer el producto remoto.';

            return $detalle;
        }

        $variante = $this->control->varianteDeProducto($consulta['recurso'], (int) $item->variante_id);
        if (! $variante) {
            $detalle['restaurable'] = false;
            $detalle['motivo_bloqueo'] = 'La variante histórica ya no existe en el producto remoto.';

            return $detalle;
        }

        $remoto = $this->control->preciosDeVariante($variante);
        $anteriores = $item->valores_anteriores ?? [];
        $camposElegidos = [];

        foreach ($campos as $campo) {
            $destino = TiendanubePrecioDestino::tryFrom((string) $campo);
            if (! $destino) {
                continue;
            }
            if ($destino === TiendanubePrecioDestino::CostoRemoto && ! $puedeVerCosto) {
                continue;
            }

            $actual = $remoto[$destino->value] ?? null;
            $restaurarA = $this->control->normalizar($anteriores[$destino->value] ?? null);
            $aplicado = $this->valorAplicado($item, $destino->value);
            $intencion = $this->intencionRestauracion($destino, $restaurarA);
            $noPublicable = $this->costoNoPublicable($destino, $restaurarA, $intencion);
            $conflicto = ! $this->control->iguales($actual, $aplicado)
                && ! $this->control->iguales($actual, $restaurarA);

            $campoFila = [
                'campo' => $destino->value,
                'actual' => $actual,
                'restaurar_a' => $intencion === TiendanubePrecioIntencion::Eliminar ? null : $restaurarA,
                'aplicado' => $aplicado,
                'intencion' => $intencion->value,
                'conflicto' => $conflicto,
                'restaurable' => ! $noPublicable,
                'explicacion' => $noPublicable
                    ? 'El costo remoto antiguo no es publicable (ausente o cero). No se inventa un valor ni se convierte en eliminación de promoción.'
                    : ($conflicto
                        ? 'El valor remoto actual difiere del aplicado. Requiere revisión.'
                        : 'Se puede proponer la restauración de este campo.'),
            ];
            if ($conflicto) {
                $detalle['conflicto'] = true;
            }
            if ($campoFila['restaurable']) {
                $camposElegidos[] = $destino->value;
            }
            $detalle['campos'][$destino->value] = $campoFila;
        }

        $detalle['campos_elegidos'] = $camposElegidos;
        if ($camposElegidos === []) {
            $detalle['restaurable'] = false;
            $detalle['motivo_bloqueo'] = $detalle['motivo_bloqueo'] ?? 'Ningún campo elegido es restaurable.';
        }

        return $detalle;
    }

    private function intencionRestauracion(TiendanubePrecioDestino $destino, ?string $restaurarA): TiendanubePrecioIntencion
    {
        if ($destino === TiendanubePrecioDestino::Promocional && $restaurarA === null) {
            return TiendanubePrecioIntencion::Eliminar;
        }
        if ($restaurarA === null) {
            return TiendanubePrecioIntencion::Conservar;
        }

        return TiendanubePrecioIntencion::Establecer;
    }

    private function costoNoPublicable(TiendanubePrecioDestino $destino, ?string $restaurarA, TiendanubePrecioIntencion $intencion): bool
    {
        if ($destino !== TiendanubePrecioDestino::CostoRemoto) {
            return $intencion === TiendanubePrecioIntencion::Conservar && $destino !== TiendanubePrecioDestino::Promocional;
        }
        if ($restaurarA === null) {
            return true;
        }

        return TiendanubePrecioDecimal::esCero($restaurarA);
    }

    private function valorAplicado(TiendanubePrecioLoteItem $item, string $campo): ?string
    {
        $final = $item->resultado_final['campos'][$campo] ?? [];
        $intencion = $final['intencion'] ?? 'conservar';
        if ($intencion === 'eliminar') {
            return null;
        }
        if ($intencion !== 'conservar') {
            return $this->control->normalizar($final['valor_final'] ?? null);
        }

        return $this->control->normalizar(($item->valores_anteriores ?? [])[$campo] ?? null);
    }

    /**
     * @return array<int, list<string>>
     */
    private function normalizarSeleccion(mixed $filas): array
    {
        if (! is_array($filas)) {
            return [];
        }
        $salida = [];
        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $vid = (int) ($fila['variante_id'] ?? 0);
            if ($vid <= 0) {
                continue;
            }
            $campos = $fila['campos'] ?? [];
            if (! is_array($campos)) {
                continue;
            }
            $salida[$vid] = array_values(array_filter($campos, fn ($c) => is_string($c) && $c !== ''));
        }

        return $salida;
    }

    /**
     * @param  list<array<string, mixed>>  $elegibles
     */
    private function aplicarIntenciones(TiendanubePrecioLote $compensacion, array $elegibles): void
    {
        $revision = $compensacion->revisionActual();
        if (! $revision) {
            return;
        }

        $porVariante = [];
        foreach ($elegibles as $fila) {
            $porVariante[(int) $fila['variante_id']] = $fila;
        }

        DB::transaction(function () use ($revision, $porVariante) {
            foreach ($revision->items()->orderBy('id')->get() as $item) {
                $plan = $porVariante[(int) $item->variante_id] ?? null;
                if (! $plan) {
                    $item->excluido = true;
                    $item->exclusion_motivo = 'Fuera del alcance de la restauración.';
                    $item->save();

                    continue;
                }
                $ajustes = [];
                foreach ($plan['campos'] as $clave => $campo) {
                    if (empty($campo['restaurable'])) {
                        continue;
                    }
                    $intencion = TiendanubePrecioIntencion::tryFrom((string) ($campo['intencion'] ?? 'conservar'))
                        ?? TiendanubePrecioIntencion::Conservar;
                    if ($intencion === TiendanubePrecioIntencion::Conservar) {
                        continue;
                    }
                    $ajustes[$clave] = [
                        'intencion' => $intencion->value,
                        'valor' => $campo['restaurar_a'],
                        'motivo' => 'Restauración compensatoria',
                    ];
                }
                $item->ajustes_manuales = $ajustes;
                $base = ['campos' => []];
                foreach (TiendanubePrecioDestino::cases() as $destino) {
                    $base['campos'][$destino->value] = [
                        'destino' => $destino->value,
                        'intencion' => TiendanubePrecioIntencion::Conservar->value,
                        'valor_bruto' => null,
                        'valor_final' => null,
                        'regla_id' => null,
                        'explicacion' => 'Campo no incluido en la restauración.',
                        'alertas' => [],
                        'errores' => [],
                    ];
                }
                $final = $this->simulacion->aplicarAjustesAlResultado($base, $ajustes, $item);
                $item->resultado_final = $final;
                $item->errores = $final['errores'] ?? [];
                $item->validaciones = [
                    'publicable' => (bool) ($final['publicable'] ?? false),
                    'margen_estimado' => $final['margen_estimado'] ?? null,
                    'diferencia_absoluta' => $final['diferencia_absoluta'] ?? null,
                    'variacion_porcentual' => $final['variacion_porcentual'] ?? null,
                ];
                $item->estado_fila = $this->simulacion->clasificar($item, $final, false);
                $item->save();
            }
            $this->simulacion->actualizarChecksumYResumen($revision->fresh());
        });
    }
}
