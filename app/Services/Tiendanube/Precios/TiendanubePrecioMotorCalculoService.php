<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;
use App\Support\Tiendanube\Precios\TiendanubePrecioOperacion;
use ValueError;

final class TiendanubePrecioMotorCalculoService
{
    public const MOTOR_VERSION = '1';

    public function __construct(
        private readonly TiendanubePrecioMotorValidador $validador = new TiendanubePrecioMotorValidador,
        private readonly TiendanubePrecioMotorCondicionEvaluador $condiciones = new TiendanubePrecioMotorCondicionEvaluador,
        private readonly TiendanubePrecioMotorOperacionCalculador $operaciones = new TiendanubePrecioMotorOperacionCalculador,
        private readonly TiendanubePrecioMotorRedondeoService $redondeo = new TiendanubePrecioMotorRedondeoService,
        private readonly TiendanubePrecioMotorValidacionFinalService $validacionFinal = new TiendanubePrecioMotorValidacionFinalService,
        private readonly TiendanubePrecioMotorMetricasService $metricas = new TiendanubePrecioMotorMetricasService,
    ) {}

    /**
     * Motor puro: no accede a API, base de datos, archivos ni cola.
     * No muta snapshot ni reglas de entrada.
     *
     * @param  list<TiendanubePrecioMotorReglaDto|array<string, mixed>>  $reglas
     * @param  array<string, mixed>|TiendanubePrecioMotorPoliticaDto|null  $politica
     */
    public function calcular(
        TiendanubePrecioMotorSnapshotDto|array $snapshot,
        array $reglas,
        TiendanubePrecioMotorPoliticaDto|array|null $politica = null
    ): TiendanubePrecioMotorResultadoDto {
        $snapshotDto = $snapshot instanceof TiendanubePrecioMotorSnapshotDto
            ? $snapshot
            : TiendanubePrecioMotorSnapshotDto::fromArray($snapshot);

        $politicaDto = $politica instanceof TiendanubePrecioMotorPoliticaDto
            ? $politica
            : TiendanubePrecioMotorPoliticaDto::fromArray($politica ?? []);

        try {
            $reglasDto = $this->normalizarReglas($reglas);
        } catch (ValueError $e) {
            return $this->resultadoInvalido($snapshotDto, ['esquema_invalido']);
        }

        $erroresEsquema = $this->validador->validar($reglasDto, $politicaDto);
        if ($erroresEsquema !== []) {
            return $this->resultadoInvalido($snapshotDto, $erroresEsquema);
        }

        $campos = $this->conservarSnapshot($snapshotDto);
        $escrituras = [];
        $erroresFila = [];
        $alertas = [];

        foreach ($reglasDto as $regla) {
            if (! $this->condiciones->cumple($regla->condiciones, $snapshotDto)) {
                continue;
            }

            $destinoClave = $regla->destino->value;
            $base = $snapshotDto->valor($regla->base, $regla->baseListaId);
            $calculo = $this->operaciones->calcular($regla, $base);

            if (! $calculo['ok']) {
                $erroresFila[] = $calculo['error'] ?? 'operacion_invalida';
                $campos[$destinoClave] = $this->campoError($regla, $calculo['error'] ?? 'operacion_invalida');

                continue;
            }

            if (isset($escrituras[$destinoClave])) {
                $erroresFila[] = 'conflicto';
                $campos[$destinoClave] = $this->campoConflicto(
                    $regla,
                    $escrituras[$destinoClave],
                    $campos[$destinoClave]
                );

                continue;
            }

            $escrituras[$destinoClave] = $regla->id;

            if ($regla->operacion === TiendanubePrecioOperacion::Eliminar) {
                $campos[$destinoClave] = new TiendanubePrecioMotorResultadoCampoDto(
                    destino: $regla->destino,
                    intencion: TiendanubePrecioIntencion::Eliminar,
                    valorBruto: null,
                    valorFinal: null,
                    reglaId: $regla->id,
                    explicacion: 'Eliminar precio promocional.',
                );

                continue;
            }

            $bruto = (string) $calculo['bruto'];
            $final = $this->redondeo->aplicar($bruto, $regla->redondeo, $regla->redondeoDireccion);
            $campos[$destinoClave] = new TiendanubePrecioMotorResultadoCampoDto(
                destino: $regla->destino,
                intencion: TiendanubePrecioIntencion::Establecer,
                valorBruto: TiendanubePrecioDecimal::format($bruto, 4),
                valorFinal: $final,
                reglaId: $regla->id,
                explicacion: $this->explicar($regla, $base, $bruto, $final),
            );
        }

        $metricas = $this->metricas->calcular($campos, $snapshotDto);
        $final = $this->validacionFinal->validar($campos, $politicaDto, $metricas['costo_usado']);
        $campos = $final['campos'];
        $erroresFila = array_values(array_unique(array_merge($erroresFila, $final['errores'])));
        $metricas = $this->metricas->calcular($campos, $snapshotDto);

        return new TiendanubePrecioMotorResultadoDto(
            tiendaId: $snapshotDto->tiendaId,
            productoId: $snapshotDto->productoId,
            varianteId: $snapshotDto->varianteId,
            motorVersion: self::MOTOR_VERSION,
            campos: $campos,
            publicable: $erroresFila === [],
            margenEstimado: $metricas['margen_estimado'],
            costoUsado: $metricas['costo_usado'],
            diferenciaAbsoluta: $metricas['diferencia_absoluta'],
            variacionPorcentual: $metricas['variacion_porcentual'],
            margenCalculable: $metricas['margen_calculable'],
            variacionPorcentualCalculable: $metricas['variacion_porcentual_calculable'],
            alertas: $alertas,
            errores: $erroresFila,
        );
    }

    /**
     * @param  list<TiendanubePrecioMotorReglaDto|array<string, mixed>>  $reglas
     * @return list<TiendanubePrecioMotorReglaDto>
     */
    private function normalizarReglas(array $reglas): array
    {
        $salida = [];
        foreach ($reglas as $regla) {
            $salida[] = $regla instanceof TiendanubePrecioMotorReglaDto
                ? $regla
                : TiendanubePrecioMotorReglaDto::fromArray($regla);
        }

        return $salida;
    }

    /**
     * @return array<string, TiendanubePrecioMotorResultadoCampoDto>
     */
    private function conservarSnapshot(TiendanubePrecioMotorSnapshotDto $snapshot): array
    {
        $campos = [];
        foreach (TiendanubePrecioDestino::cases() as $destino) {
            $valor = $snapshot->valor($destino->campoSnapshot());
            $campos[$destino->value] = new TiendanubePrecioMotorResultadoCampoDto(
                destino: $destino,
                intencion: TiendanubePrecioIntencion::Conservar,
                valorBruto: $valor,
                valorFinal: $valor,
                reglaId: null,
                explicacion: 'Conservar valor del snapshot inicial.',
            );
        }

        return $campos;
    }

    /**
     * @param  list<string>  $errores
     */
    private function resultadoInvalido(TiendanubePrecioMotorSnapshotDto $snapshot, array $errores): TiendanubePrecioMotorResultadoDto
    {
        return new TiendanubePrecioMotorResultadoDto(
            tiendaId: $snapshot->tiendaId,
            productoId: $snapshot->productoId,
            varianteId: $snapshot->varianteId,
            motorVersion: self::MOTOR_VERSION,
            campos: $this->conservarSnapshot($snapshot),
            publicable: false,
            errores: $errores,
        );
    }

    private function campoError(TiendanubePrecioMotorReglaDto $regla, string $error): TiendanubePrecioMotorResultadoCampoDto
    {
        return new TiendanubePrecioMotorResultadoCampoDto(
            destino: $regla->destino,
            intencion: TiendanubePrecioIntencion::Conservar,
            valorBruto: null,
            valorFinal: null,
            reglaId: $regla->id,
            explicacion: 'La regla coincidió pero no produjo un importe publicable.',
            errores: [$error],
        );
    }

    private function campoConflicto(
        TiendanubePrecioMotorReglaDto $regla,
        string $reglaPrevia,
        TiendanubePrecioMotorResultadoCampoDto $previo
    ): TiendanubePrecioMotorResultadoCampoDto {
        return new TiendanubePrecioMotorResultadoCampoDto(
            destino: $regla->destino,
            intencion: $previo->intencion,
            valorBruto: $previo->valorBruto,
            valorFinal: null,
            reglaId: $previo->reglaId,
            explicacion: 'Conflicto: dos reglas escriben el mismo destino.',
            errores: ['conflicto'],
            alertas: [$reglaPrevia, $regla->id],
        );
    }

    private function explicar(TiendanubePrecioMotorReglaDto $regla, ?string $base, string $bruto, string $final): string
    {
        return sprintf(
            'Regla %s: %s sobre %s (%s) hacia %s. Bruto %s, final %s.',
            $regla->id,
            $regla->operacion->value,
            $regla->base->value,
            $base ?? 'ausente',
            $regla->destino->value,
            $bruto,
            $final
        );
    }
}
