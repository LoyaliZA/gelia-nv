<?php

namespace App\Services\Clientes;

use Illuminate\Validation\ValidationException;

/** Decimal de Wizerp, sin redondeo ni conversiones monetarias a float. */
class MontoWizerp
{
    public function interpretar(?string $valor, string $campo = 'monto_venta_actual'): ?string
    {
        $valor = trim($valor ?? '');
        if ($valor === '') {
            return null;
        }
        if (! preg_match('/^\$?(?:[0-9]+|[1-9][0-9]{0,2}(?:,[0-9]{3})+)(?:\.[0-9]{1,2})?$/D', $valor)) {
            throw ValidationException::withMessages([$campo => 'Monto inválido: use un decimal no negativo con hasta dos decimales.']);
        }
        $decimal = bcadd(str_replace(['$', ','], '', $valor), '0', 2);
        $limite = $campo === 'monto_credito_autorizado' ? '9999999999999.99' : '9999999999.99';
        if (bccomp($decimal, $limite, 2) > 0) {
            throw ValidationException::withMessages([$campo => 'El monto excede el límite permitido.']);
        }

        return $decimal;
    }
}
