<?php

namespace App\Support\Facturas;

use App\Models\EnlaceDatosFiscales;

final class CamposIncorrectosFactura
{
    public const RAZON_SOCIAL = 'razon_social';

    public const OBSERVACIONES_VENDEDOR = 'observaciones_vendedor';

    public const ARCHIVO_FISCAL = 'archivo_fiscal';

    public const VOUCHERS = 'vouchers';

    public const ADJUNTOS = [
        self::RAZON_SOCIAL,
        self::OBSERVACIONES_VENDEDOR,
        self::ARCHIVO_FISCAL,
        self::VOUCHERS,
    ];

    public const ETIQUETAS = [
        'rfc' => 'RFC',
        'codigo_postal' => 'Código postal',
        'regimen_fiscal' => 'Régimen fiscal',
        'correo_electronico' => 'Correo electrónico',
        'uso_factura' => 'Uso de CFDI',
        'nombre_razon_social' => 'Nombre / razón social',
        'telefono' => 'Teléfono',
        self::RAZON_SOCIAL => 'Razón social',
        self::OBSERVACIONES_VENDEDOR => 'Observaciones',
        self::ARCHIVO_FISCAL => 'Excel fiscal',
        self::VOUCHERS => 'Comprobantes (voucher)',
    ];

    /**
     * @return list<string>
     */
    public static function todos(): array
    {
        return array_values(array_unique(array_merge(EnlaceDatosFiscales::CAMPOS, self::ADJUNTOS)));
    }

    /**
     * @param  list<string>|null  $campos
     * @return list<string>
     */
    public static function filtrar(?array $campos): array
    {
        if ($campos === null || $campos === []) {
            return [];
        }

        $permitidos = self::todos();
        $out = [];
        foreach ($campos as $campo) {
            $campo = (string) $campo;
            if (in_array($campo, $permitidos, true) && ! in_array($campo, $out, true)) {
                $out[] = $campo;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $campos
     * @return list<string>
     */
    public static function soloFiscales(array $campos): array
    {
        return array_values(array_filter(
            self::filtrar($campos),
            fn (string $c) => in_array($c, EnlaceDatosFiscales::CAMPOS, true)
        ));
    }

    public static function etiqueta(string $campo): string
    {
        return self::ETIQUETAS[$campo] ?? $campo;
    }

    /**
     * @param  list<string>|null  $antes
     * @param  list<string>  $corregidos
     * @return list<string>
     */
    public static function quitarResueltos(?array $antes, array $corregidos): array
    {
        $antes = self::filtrar($antes);
        $corregidos = self::filtrar($corregidos);

        return array_values(array_filter(
            $antes,
            fn (string $c) => ! in_array($c, $corregidos, true)
        ));
    }
}
