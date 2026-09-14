<?php

namespace App\Services\Tiendanube\Precios;

use App\Models\Tiendanube\TiendanubePrecioLista;
use App\Support\Tiendanube\Precios\TiendanubePrecioCampoCondicion;
use App\Support\Tiendanube\Precios\TiendanubePrecioCondicionOperador;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioOperacion;
use App\Support\Tiendanube\Precios\TiendanubePrecioRedondeoDireccion;
use App\Support\Tiendanube\Precios\TiendanubePrecioRedondeoModo;

final class TiendanubePrecioReglaMetadatosService
{
    /**
     * @return array<string, mixed>
     */
    public function paraUi(int $storeId): array
    {
        $listas = TiendanubePrecioLista::query()
            ->where('store_id', $storeId)
            ->activas()
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (TiendanubePrecioLista $l) => ['id' => $l->id, 'nombre' => $l->nombre])
            ->values()
            ->all();

        return [
            'contract_version' => TiendanubePrecioMotorCalculoService::MOTOR_VERSION,
            'bases' => $this->enumOpciones(TiendanubePrecioCampoCondicion::cases(), [
                'costo_local' => 'Costo local',
                'lista_referencia' => 'Lista de referencia',
                'costo_remoto_actual' => 'Costo de TiendaNube',
                'precio_normal_actual' => 'Precio normal actual',
                'precio_promocional_actual' => 'Precio promocional actual',
            ]),
            'campos_condicion' => $this->enumOpciones(TiendanubePrecioCampoCondicion::cases(), [
                'costo_local' => 'Costo local',
                'lista_referencia' => 'Lista de referencia',
                'costo_remoto_actual' => 'Costo de TiendaNube',
                'precio_normal_actual' => 'Precio normal',
                'precio_promocional_actual' => 'Precio promocional',
            ]),
            'operadores' => $this->enumOpciones(TiendanubePrecioCondicionOperador::cases(), [
                '<' => 'Menor que',
                '<=' => 'Menor o igual que',
                '=' => 'Igual a',
                '>=' => 'Mayor o igual que',
                '>' => 'Mayor que',
                'entre' => 'Entre',
                'tiene_valor' => 'Tiene valor',
                'no_tiene_valor' => 'No tiene valor',
            ]),
            'operaciones' => $this->enumOpciones(TiendanubePrecioOperacion::cases(), [
                'copiar' => 'Copiar base',
                'fijar' => 'Fijar importe',
                'aumentar_importe' => 'Aumentar sobre base',
                'reducir_importe' => 'Reducir sobre base',
                'aumentar_porcentaje' => 'Aumentar porcentaje sobre base',
                'reducir_porcentaje' => 'Reducir porcentaje sobre base',
                'margen_objetivo' => 'Margen sobre venta',
                'eliminar' => 'Quitar promoción',
            ]),
            'destinos' => $this->enumOpciones(TiendanubePrecioDestino::cases(), [
                'normal' => 'Precio normal',
                'promocional' => 'Precio promocional',
                'costo_remoto' => 'Costo en TiendaNube',
            ]),
            'redondeos' => $this->enumOpciones(TiendanubePrecioRedondeoModo::cases(), [
                'dos_decimales_half_up' => 'Dos decimales',
                'entero_arriba' => 'Entero hacia arriba',
                'entero_abajo' => 'Entero hacia abajo',
                'entero_cercano' => 'Entero al más cercano',
                'terminacion_90' => 'Terminación .90',
                'terminacion_99' => 'Terminación .99',
            ]),
            'redondeo_direcciones' => $this->enumOpciones(TiendanubePrecioRedondeoDireccion::cases(), [
                'arriba' => 'Arriba',
                'abajo' => 'Abajo',
                'cercano' => 'Cercano',
            ]),
            'listas' => $listas,
            'base_inicial' => TiendanubePrecioCampoCondicion::CostoRemotoActual->value,
            'ayudas' => [
                'margen_objetivo' => 'Calcula el precio de venta para alcanzar un margen sobre el costo. Ej.: costo 100 y margen 30 % → precio 142.86.',
                'aumentar_porcentaje' => 'Multiplica la base por (1 + porcentaje). Ej.: base 100 y 12 % → 112.',
                'reducir_porcentaje' => 'Multiplica la base por (1 − porcentaje). Ej.: base 100 y 6 % → 94.',
                'eliminar' => 'Solo aplica al precio promocional. No es compatible con establecer un nuevo precio normal en la misma fila.',
            ],
        ];
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    private function enumOpciones(array $cases, array $labels): array
    {
        $out = [];
        foreach ($cases as $case) {
            $out[] = [
                'value' => $case->value,
                'label' => $labels[$case->value] ?? $case->value,
            ];
        }

        return $out;
    }
}
