<?php

namespace App\Support\ComisionesEmpaque;

/**
 * Prevalencia de fuentes. No suma numero_cajas de varias fuentes ni el total de la operación.
 *
 * CEDIS: solo un pedido con empacado_at en la operación de CEDIS.
 * 1. Cajas activas de pedido_bma_cajas. Cada fila es la unidad. Las retiradas no comisionan.
 * 2. Si no hay cajas activas, cada bulto de pedido_bma_bultos_empaque queda pendiente de
 *    clasificación (puede ser bolsa). No se toma también numero_cajas, porque el empaque lo iguala al conteo de bultos.
 * 3. Si no hay cajas ni bultos y numero_cajas es mayor a cero, una unidad de cabecera pendiente de clasificación.
 * La operación de empaque no crea unidades: su numero_cajas es la suma de cabeceras.
 * Si algún miembro de la operación tiene cajas activas, se omite la cabecera de todos.
 * Un bulto de otro miembro, en ese caso, queda en revisión por posible solape.
 *
 * Tienda: pedido_bma_cumplimiento_fisico.empacado_at (empaque municipal / preparación de tienda).
 * Los bultos de salida no se interpretan como cajas: quedan pendientes de clasificación.
 * No se suma el numero_cajas del mismo pedido.
 *
 * El origen Call Center, Bellaroma o Aromas no elige el área. La elige el proceso que registró el empaque.
 * Un resguardo empacado en CEDIS y también empacado en tienda cuenta en ambas áreas, con marca de excepción temporal.
 * Recibir el resguardo no es empacarlo.
 *
 * La tarifa es plana por área y por la fecha del empaque. El tipo de caja se conserva como desglose;
 * la bolsa se excluye y no lleva tarifa cero silenciosa.
 */
class FuentesComisionEmpaque
{
    public const CEDIS_CAJA = 'cedis_caja';

    public const CEDIS_BULTO = 'cedis_bulto';

    public const CEDIS_CABECERA = 'cedis_cabecera';

    public const TIENDA_CUMPLIMIENTO = 'tienda_cumplimiento';

    public const MANUAL = 'manual';

    /** @return list<array{flujo: string, prevalece: string}> */
    public static function resumen(): array
    {
        return [
            [
                'flujo' => 'CEDIS',
                'prevalece' => 'Cajas activas del pedido empacado en CEDIS. Si no hay, bultos pendientes de clasificación. Si no hay bultos, el numero_cajas de esa cabecera. No se usa el total de la operación de empaque.',
            ],
            [
                'flujo' => 'Tienda',
                'prevalece' => 'Empaque registrado en preparación de tienda (cumplimiento con fecha de empaque). Los bultos quedan pendientes de separar cajas y bolsas. El registro manual entra solo después de la revisión.',
            ],
            [
                'flujo' => 'Resguardo',
                'prevalece' => 'Si se empaca en CEDIS y después se empaca en tienda, por ahora cuentan los dos. Recibir el resguardo no genera comisión. Esta excepción queda marcada para ajustarla más adelante.',
            ],
        ];
    }
}
