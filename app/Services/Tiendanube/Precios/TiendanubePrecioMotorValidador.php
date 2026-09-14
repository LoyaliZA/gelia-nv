<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioOperacion;
use ValueError;

final class TiendanubePrecioMotorValidador
{
    /**
     * @param  list<TiendanubePrecioMotorReglaDto>  $reglas
     * @return list<string>
     */
    public function validar(array $reglas, TiendanubePrecioMotorPoliticaDto $politica): array
    {
        $errores = [];
        $ids = [];

        foreach ($reglas as $regla) {
            if ($regla->id === '') {
                $errores[] = 'regla_id_vacio';
            } elseif (isset($ids[$regla->id])) {
                $errores[] = 'regla_id_duplicado';
            }
            $ids[$regla->id] = true;

            foreach ($regla->condiciones as $condicion) {
                $errores = array_merge($errores, $this->validarCondicion($regla->id, $condicion));
            }

            $errores = array_merge($errores, $this->validarOperacion($regla));
        }

        if ($politica->margenMinimo !== null) {
            $parsed = TiendanubePrecioDecimal::parseParametro($politica->margenMinimo);
            if (! $parsed['ok']) {
                $errores[] = 'politica_margen_minimo_invalido';
            } elseif (TiendanubePrecioDecimal::cmp($parsed['valor'], '0') < 0
                || TiendanubePrecioDecimal::cmp($parsed['valor'], '100') >= 0) {
                $errores[] = 'politica_margen_minimo_invalido';
            }
        }

        return array_values(array_unique($errores));
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<string>
     */
    public function validarReglaArray(array $datos): array
    {
        try {
            $regla = TiendanubePrecioMotorReglaDto::fromArray($datos);
        } catch (ValueError $e) {
            return ['esquema_invalido'];
        }

        return $this->validar([$regla], new TiendanubePrecioMotorPoliticaDto);
    }

    /**
     * @return list<string>
     */
    private function validarCondicion(string $reglaId, TiendanubePrecioMotorCondicionDto $condicion): array
    {
        $prefijo = 'regla:'.$reglaId.':';
        $errores = [];

        if ($condicion->operador->requiereRango()) {
            if ($condicion->valor === null || $condicion->valorHasta === null) {
                $errores[] = $prefijo.'condicion_rango_incompleto';
            } else {
                $desde = TiendanubePrecioDecimal::parseParametro($condicion->valor);
                $hasta = TiendanubePrecioDecimal::parseParametro($condicion->valorHasta);
                if (! $desde['ok'] || ! $hasta['ok']) {
                    $errores[] = $prefijo.'condicion_valor_invalido';
                }
            }
        } elseif ($condicion->operador->requiereValor()) {
            if ($condicion->valor === null) {
                $errores[] = $prefijo.'condicion_valor_ausente';
            } else {
                $parsed = TiendanubePrecioDecimal::parseParametro($condicion->valor);
                if (! $parsed['ok']) {
                    $errores[] = $prefijo.'condicion_valor_invalido';
                }
            }
        }

        return $errores;
    }

    /**
     * @return list<string>
     */
    private function validarOperacion(TiendanubePrecioMotorReglaDto $regla): array
    {
        $prefijo = 'regla:'.$regla->id.':';
        $errores = [];

        if ($regla->operacion === TiendanubePrecioOperacion::Eliminar && ! $regla->destino->admiteEliminar()) {
            $errores[] = $prefijo.'eliminar_solo_promocional';
        }

        if ($regla->operacion === TiendanubePrecioOperacion::MargenObjetivo) {
            if (! $regla->base->esCosto()) {
                $errores[] = $prefijo.'margen_solo_desde_costo';
            }
            if (! $regla->destino->esVenta()) {
                $errores[] = $prefijo.'margen_solo_hacia_venta';
            }
        }

        if ($regla->operacion->requiereParametro()) {
            $parsed = TiendanubePrecioDecimal::parseParametro($regla->parametro);
            if (! $parsed['ok']) {
                $errores[] = $prefijo.'parametro_invalido';

                return $errores;
            }

            $valor = $parsed['valor'];
            if (in_array($regla->operacion, [
                TiendanubePrecioOperacion::ReducirPorcentaje,
                TiendanubePrecioOperacion::AumentarPorcentaje,
            ], true)) {
                if (TiendanubePrecioDecimal::cmp($valor, '0') < 0
                    || TiendanubePrecioDecimal::cmp($valor, '100') > 0) {
                    $errores[] = $prefijo.'porcentaje_fuera_de_rango';
                }
            }

            if ($regla->operacion === TiendanubePrecioOperacion::MargenObjetivo) {
                if (TiendanubePrecioDecimal::cmp($valor, '0') < 0
                    || TiendanubePrecioDecimal::cmp($valor, '100') >= 0) {
                    $errores[] = $prefijo.'margen_invalido';
                }
            }
        }

        return $errores;
    }
}
