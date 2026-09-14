<?php

namespace App\Services\Tiendanube\Precios\Lotes;

use App\Models\Tiendanube\TiendanubePrecioLote;
use App\Models\Tiendanube\TiendanubePrecioLoteItem;
use App\Models\Tiendanube\TiendanubePrecioLoteRevision;
use App\Models\Tiendanube\TiendanubePrecioLoteSimulacion;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorCalculoService;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorMetricasService;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorPoliticaDto;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorResultadoCampoDto;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorResultadoDto;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorSnapshotDto;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorValidacionFinalService;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;
use Illuminate\Support\Str;

class TiendanubePrecioLoteSimulacionService
{
    public function __construct(
        private readonly TiendanubePrecioMotorCalculoService $motor = new TiendanubePrecioMotorCalculoService,
        private readonly TiendanubePrecioMotorMetricasService $metricas = new TiendanubePrecioMotorMetricasService,
        private readonly TiendanubePrecioMotorValidacionFinalService $validacionFinal = new TiendanubePrecioMotorValidacionFinalService,
    ) {}

    public function ejecutar(TiendanubePrecioLoteRevision $revision): void
    {
        $lote = $revision->lote;
        $definicion = $revision->definicion ?? [];
        $sim = $revision->simulacion;
        if (! $sim) {
            $sim = TiendanubePrecioLoteSimulacion::query()->create([
                'revision_id' => $revision->id,
                'estado' => TiendanubePrecioLoteSimulacion::ESTADO_EN_CURSO,
                'total' => $revision->items()->count(),
                'procesados' => 0,
                'lease_token' => (string) Str::uuid(),
                'lease_expires_at' => now()->addMinutes(10),
                'started_at' => now(),
            ]);
        } else {
            $sim->update([
                'estado' => TiendanubePrecioLoteSimulacion::ESTADO_EN_CURSO,
                'procesados' => 0,
                'error' => null,
                'lease_token' => (string) Str::uuid(),
                'lease_expires_at' => now()->addMinutes(10),
                'started_at' => now(),
                'completed_at' => null,
            ]);
        }

        $procesados = 0;
        $revision->items()->orderBy('id')->chunkById(50, function ($items) use ($lote, $definicion, $sim, &$procesados) {
            foreach ($items as $item) {
                $this->simularItem($lote, $item, $definicion);
                $procesados++;
            }
            $sim->update([
                'procesados' => $procesados,
                'lease_expires_at' => now()->addMinutes(10),
            ]);
        });

        $this->actualizarChecksumYResumen($revision->fresh());
        $sim->update([
            'estado' => TiendanubePrecioLoteSimulacion::ESTADO_COMPLETADA,
            'procesados' => $procesados,
            'completed_at' => now(),
        ]);
        $revision->update(['estado' => TiendanubePrecioLoteRevision::ESTADO_SIMULADO]);
        $lote->update(['estado' => TiendanubePrecioLote::ESTADO_SIMULADO]);
    }

    /**
     * @param  array<string, mixed>  $definicion
     */
    public function simularItem(TiendanubePrecioLote $lote, TiendanubePrecioLoteItem $item, array $definicion): void
    {
        $snapshot = [
            'tienda_id' => (int) $lote->store_id,
            'producto_id' => (int) $item->producto_id,
            'variante_id' => (int) $item->variante_id,
            'fuentes' => $item->fuentes_snapshot ?? [],
        ];

        $resultado = $this->motor->calcular($snapshot, [$definicion]);
        $this->persistirResultado($item, $resultado, $item->excluido);
    }

    public function persistirResultado(
        TiendanubePrecioLoteItem $item,
        TiendanubePrecioMotorResultadoDto $resultado,
        bool $excluido
    ): void {
        $payload = $resultado->toArray();
        $ajustes = $item->ajustes_manuales;
        if (is_array($ajustes) && $ajustes !== []) {
            $payload = $this->aplicarAjustesAlResultado($payload, $ajustes, $item);
        }

        $estado = $this->clasificar($item, $payload, $excluido);
        $item->resultado_calculado = $resultado->toArray();
        $item->resultado_final = $payload;
        $item->errores = $payload['errores'] ?? [];
        $item->validaciones = [
            'publicable' => (bool) ($payload['publicable'] ?? false),
            'margen_estimado' => $payload['margen_estimado'] ?? null,
            'diferencia_absoluta' => $payload['diferencia_absoluta'] ?? null,
            'variacion_porcentual' => $payload['variacion_porcentual'] ?? null,
        ];
        $item->estado_fila = $estado;
        $item->excluido = $excluido;
        $item->save();
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $ajustes
     * @return array<string, mixed>
     */
    public function aplicarAjustesAlResultado(array $resultado, array $ajustes, TiendanubePrecioLoteItem $item): array
    {
        $camposDto = $this->camposDesdeArray($resultado['campos'] ?? []);
        foreach ($ajustes as $destino => $ajuste) {
            if (! is_array($ajuste)) {
                continue;
            }
            $enum = TiendanubePrecioDestino::tryFrom((string) $destino);
            if (! $enum) {
                continue;
            }
            $intencion = TiendanubePrecioIntencion::tryFrom((string) ($ajuste['intencion'] ?? TiendanubePrecioIntencion::Establecer->value))
                ?? TiendanubePrecioIntencion::Establecer;
            if ($intencion === TiendanubePrecioIntencion::Eliminar) {
                $camposDto[$enum->value] = new TiendanubePrecioMotorResultadoCampoDto(
                    destino: $enum,
                    intencion: TiendanubePrecioIntencion::Eliminar,
                    valorBruto: null,
                    valorFinal: null,
                    reglaId: $camposDto[$enum->value]->reglaId ?? null,
                    explicacion: 'Ajuste manual: '.((string) ($ajuste['motivo'] ?? 'eliminar')),
                );

                continue;
            }
            if (! array_key_exists('valor', $ajuste) || $ajuste['valor'] === null) {
                continue;
            }
            $camposDto[$enum->value] = new TiendanubePrecioMotorResultadoCampoDto(
                destino: $enum,
                intencion: TiendanubePrecioIntencion::Establecer,
                valorBruto: (string) $ajuste['valor'],
                valorFinal: (string) $ajuste['valor'],
                reglaId: $camposDto[$enum->value]->reglaId ?? null,
                explicacion: 'Ajuste manual: '.((string) ($ajuste['motivo'] ?? '')),
            );
        }

        $politica = TiendanubePrecioMotorPoliticaDto::fromArray([]);
        $metricas = $this->metricas->calcular($camposDto, $this->snapshotDesdeItem($item));
        $final = $this->validacionFinal->validar($camposDto, $politica, $metricas['costo_usado']);
        $metricas = $this->metricas->calcular($final['campos'], $this->snapshotDesdeItem($item));

        $campos = [];
        foreach ($final['campos'] as $clave => $campo) {
            $campos[$clave] = $campo->toArray();
        }

        $resultado['campos'] = $campos;
        $resultado['errores'] = $final['errores'];
        $resultado['publicable'] = $final['errores'] === [];
        $resultado['margen_estimado'] = $metricas['margen_estimado'];
        $resultado['costo_usado'] = $metricas['costo_usado'];
        $resultado['diferencia_absoluta'] = $metricas['diferencia_absoluta'];
        $resultado['variacion_porcentual'] = $metricas['variacion_porcentual'];
        $resultado['margen_calculable'] = $metricas['margen_calculable'];
        $resultado['variacion_porcentual_calculable'] = $metricas['variacion_porcentual_calculable'];

        return $resultado;
    }

    public function actualizarChecksumYResumen(TiendanubePrecioLoteRevision $revision): void
    {
        $items = $revision->items()->orderBy('variante_id')->get();
        $huella = [];
        $aprobada = [
            'definicion' => $revision->definicion,
            'items' => [],
        ];
        $resumen = [
            'seleccionadas' => $items->count(),
            'productos' => $items->pluck('producto_id')->unique()->count(),
            'con_cambio' => 0,
            'sin_cambio' => 0,
            'bloqueadas' => 0,
            'excluidas' => 0,
            'con_errores' => 0,
            'variantes_normal' => 0,
            'variantes_promo' => 0,
            'variantes_costo' => 0,
            'moneda' => $revision->lote?->moneda ?? 'MXN',
            'motivo_sin_aprobacion' => null,
        ];

        foreach ($items as $item) {
            $anteriores = $item->valores_anteriores ?? [];
            $huella[] = [
                'variante_id' => (int) $item->variante_id,
                'normal' => $anteriores['normal'] ?? null,
                'promocional' => $anteriores['promocional'] ?? null,
                'costo_remoto' => $anteriores['costo_remoto'] ?? null,
                'sin_normal' => ($anteriores['normal'] ?? null) === null,
                'sin_promocional' => ($anteriores['promocional'] ?? null) === null,
                'sin_costo_remoto' => ($anteriores['costo_remoto'] ?? null) === null,
            ];
            $aprobada['items'][] = [
                'variante_id' => (int) $item->variante_id,
                'excluido' => (bool) $item->excluido,
                'ajustes' => $item->ajustes_manuales,
                'resultado' => $item->resultado_final,
            ];

            match ($item->estado_fila) {
                TiendanubePrecioLoteItem::ESTADO_CON_CAMBIO => $resumen['con_cambio']++,
                TiendanubePrecioLoteItem::ESTADO_SIN_CAMBIO => $resumen['sin_cambio']++,
                TiendanubePrecioLoteItem::ESTADO_BLOQUEADA => $resumen['bloqueadas']++,
                TiendanubePrecioLoteItem::ESTADO_EXCLUIDA => $resumen['excluidas']++,
                TiendanubePrecioLoteItem::ESTADO_ERROR => $resumen['con_errores']++,
                default => null,
            };
            if (($item->errores ?? []) !== [] && $item->estado_fila !== TiendanubePrecioLoteItem::ESTADO_ERROR) {
                $resumen['con_errores']++;
            }

            if ($item->excluido || $item->estado_fila !== TiendanubePrecioLoteItem::ESTADO_CON_CAMBIO) {
                continue;
            }
            $campos = $item->resultado_final['campos'] ?? [];
            foreach ([
                TiendanubePrecioDestino::Normal->value => 'variantes_normal',
                TiendanubePrecioDestino::Promocional->value => 'variantes_promo',
                TiendanubePrecioDestino::CostoRemoto->value => 'variantes_costo',
            ] as $destino => $clave) {
                $intencion = $campos[$destino]['intencion'] ?? TiendanubePrecioIntencion::Conservar->value;
                if ($intencion !== TiendanubePrecioIntencion::Conservar->value) {
                    $resumen[$clave]++;
                }
            }
        }

        $validas = $resumen['variantes_normal'] + $resumen['variantes_promo'] + $resumen['variantes_costo'];
        $erroresEnSeleccionadas = $items->filter(function (TiendanubePrecioLoteItem $item) {
            return ! $item->excluido && ($item->estado_fila === TiendanubePrecioLoteItem::ESTADO_ERROR
                || $item->estado_fila === TiendanubePrecioLoteItem::ESTADO_BLOQUEADA
                || (($item->errores ?? []) !== []));
        })->count();

        if ($validas === 0) {
            $resumen['motivo_sin_aprobacion'] = 'No hay cambios válidos para aprobar.';
        } elseif ($erroresEnSeleccionadas > 0) {
            $resumen['motivo_sin_aprobacion'] = 'Hay filas con error o bloqueadas. Exclúyalas o corríjalas antes de aprobar.';
        }

        $resumen['puede_aprobar'] = $resumen['motivo_sin_aprobacion'] === null;
        $resumen['excluidas_invalidas'] = $items->filter(fn (TiendanubePrecioLoteItem $i) => $i->excluido && ($i->errores ?? []) !== [])->count();

        $revision->update([
            'checksum' => hash('sha256', json_encode($aprobada, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'huella_conflicto_remoto' => hash('sha256', json_encode($huella, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'resumen' => $resumen,
        ]);
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function clasificar(TiendanubePrecioLoteItem $item, array $resultado, bool $excluido): string
    {
        if ($excluido) {
            return TiendanubePrecioLoteItem::ESTADO_EXCLUIDA;
        }
        if (! $item->espejo_existe) {
            return TiendanubePrecioLoteItem::ESTADO_ERROR;
        }
        $errores = $resultado['errores'] ?? [];
        $hayCambio = $this->hayCambio($item, $resultado);
        if ($errores !== []) {
            return $hayCambio ? TiendanubePrecioLoteItem::ESTADO_ERROR : TiendanubePrecioLoteItem::ESTADO_BLOQUEADA;
        }
        if (! $hayCambio) {
            return TiendanubePrecioLoteItem::ESTADO_SIN_CAMBIO;
        }
        if (empty($resultado['publicable'])) {
            return TiendanubePrecioLoteItem::ESTADO_BLOQUEADA;
        }

        return TiendanubePrecioLoteItem::ESTADO_CON_CAMBIO;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function hayCambio(TiendanubePrecioLoteItem $item, array $resultado): bool
    {
        $anteriores = $item->valores_anteriores ?? [];
        $mapa = [
            TiendanubePrecioDestino::Normal->value => 'normal',
            TiendanubePrecioDestino::Promocional->value => 'promocional',
            TiendanubePrecioDestino::CostoRemoto->value => 'costo_remoto',
        ];
        foreach ($resultado['campos'] ?? [] as $clave => $campo) {
            $intencion = $campo['intencion'] ?? TiendanubePrecioIntencion::Conservar->value;
            if ($intencion === TiendanubePrecioIntencion::Conservar->value) {
                continue;
            }
            $antes = $anteriores[$mapa[$clave] ?? ''] ?? null;
            $despues = $intencion === TiendanubePrecioIntencion::Eliminar->value ? null : ($campo['valor_final'] ?? null);
            if ((string) $antes !== (string) $despues) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $campos
     * @return array<string, TiendanubePrecioMotorResultadoCampoDto>
     */
    private function camposDesdeArray(array $campos): array
    {
        $salida = [];
        foreach ($campos as $clave => $campo) {
            $destino = TiendanubePrecioDestino::tryFrom((string) ($campo['destino'] ?? $clave));
            $intencion = TiendanubePrecioIntencion::tryFrom((string) ($campo['intencion'] ?? 'conservar'));
            if (! $destino || ! $intencion) {
                continue;
            }
            $salida[$destino->value] = new TiendanubePrecioMotorResultadoCampoDto(
                destino: $destino,
                intencion: $intencion,
                valorBruto: $campo['valor_bruto'] ?? null,
                valorFinal: $campo['valor_final'] ?? null,
                reglaId: $campo['regla_id'] ?? null,
                explicacion: (string) ($campo['explicacion'] ?? ''),
                alertas: $campo['alertas'] ?? [],
                errores: $campo['errores'] ?? [],
            );
        }

        return $salida;
    }

    private function snapshotDesdeItem(TiendanubePrecioLoteItem $item): TiendanubePrecioMotorSnapshotDto
    {
        return TiendanubePrecioMotorSnapshotDto::fromArray([
            'tienda_id' => (int) ($item->revision?->lote?->store_id ?? 0),
            'producto_id' => (int) $item->producto_id,
            'variante_id' => (int) $item->variante_id,
            'fuentes' => $item->fuentes_snapshot ?? [],
        ]);
    }
}
