<?php

namespace App\Support\ComisionesEmpaque;

/**
 * Reparte centavos con residuo mayor. El total devuelto coincide con el importe.
 * Empate de residuo: gana el participante_id menor.
 *
 * @phpstan-type Linea array{participante_id: int, puntos_base: int}
 */
class RepartirCentavosComisionEmpaque
{
    /**
     * @param  list<Linea>  $lineas
     * @return array<int, int> participante_id => centavos
     */
    public function repartir(int $centavos, array $lineas): array
    {
        if ($centavos < 0) {
            throw new \InvalidArgumentException('El importe del tramo no puede ser negativo.');
        }

        $resultado = [];
        foreach ($lineas as $linea) {
            $resultado[(int) $linea['participante_id']] = 0;
        }

        if ($centavos === 0 || $lineas === []) {
            return $resultado;
        }

        $bases = [];
        $suma = 0;
        foreach ($lineas as $indice => $linea) {
            $numerador = $centavos * (int) $linea['puntos_base'];
            $base = intdiv($numerador, 10000);
            $bases[$indice] = [
                'participante_id' => (int) $linea['participante_id'],
                'base' => $base,
                'residuo' => $numerador % 10000,
            ];
            $suma += $base;
        }

        $restante = $centavos - $suma;
        usort($bases, function (array $a, array $b) {
            if ($a['residuo'] === $b['residuo']) {
                return $a['participante_id'] <=> $b['participante_id'];
            }

            return $b['residuo'] <=> $a['residuo'];
        });

        foreach ($bases as $fila) {
            $extra = $restante > 0 ? 1 : 0;
            $resultado[$fila['participante_id']] = $fila['base'] + $extra;
            if ($restante > 0) {
                $restante--;
            }
        }

        return $resultado;
    }
}
