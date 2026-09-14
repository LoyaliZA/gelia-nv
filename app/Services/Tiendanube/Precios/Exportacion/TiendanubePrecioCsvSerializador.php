<?php

namespace App\Services\Tiendanube\Precios\Exportacion;

use App\Exceptions\Tiendanube\TiendanubePrecioCsvException;
use App\Support\Tiendanube\Precios\TiendanubePrecioCsvColumnaCatalogo;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;

class TiendanubePrecioCsvSerializador
{
    /**
     * @param  array<string, string>  $filaEspejo
     * @param  array<string, mixed>|null  $camposD
     * @return array<string, string>
     */
    public function aplicarIntenciones(array $filaEspejo, ?array $camposD): array
    {
        if ($camposD === null) {
            return $filaEspejo;
        }

        $filaEspejo[TiendanubePrecioCsvColumnaCatalogo::PRECIO] = $this->celda(
            $camposD['normal'] ?? [],
            $filaEspejo[TiendanubePrecioCsvColumnaCatalogo::PRECIO] ?? '',
            false
        );
        $filaEspejo[TiendanubePrecioCsvColumnaCatalogo::PRECIO_PROMOCIONAL] = $this->celda(
            $camposD['promocional'] ?? [],
            $filaEspejo[TiendanubePrecioCsvColumnaCatalogo::PRECIO_PROMOCIONAL] ?? '',
            true
        );
        $filaEspejo[TiendanubePrecioCsvColumnaCatalogo::COSTO] = $this->celda(
            $camposD['costo_remoto'] ?? [],
            $filaEspejo[TiendanubePrecioCsvColumnaCatalogo::COSTO] ?? '',
            false
        );

        return $filaEspejo;
    }

    /**
     * @param  array<string, mixed>  $campo
     */
    private function celda(array $campo, string $espejo, bool $esPromo): string
    {
        $intencion = (string) ($campo['intencion'] ?? TiendanubePrecioIntencion::Conservar->value);
        if ($intencion === TiendanubePrecioIntencion::Eliminar->value) {
            return '-';
        }
        if ($intencion === TiendanubePrecioIntencion::Establecer->value) {
            $valor = $campo['valor_final'] ?? null;

            return $valor === null ? '' : (string) $valor;
        }
        if ($esPromo) {
            return '';
        }

        return $espejo;
    }

    /**
     * @param  array<string, string>  $fila
     */
    public function validarIdentidad(array $fila): void
    {
        $handle = $fila[TiendanubePrecioCsvColumnaCatalogo::IDENTIFICADOR_URL] ?? '';
        $sku = $fila[TiendanubePrecioCsvColumnaCatalogo::SKU] ?? '';
        if (trim($handle) === '') {
            throw new TiendanubePrecioCsvException(
                'Falta el identificador de URL en una variante. Sincronice el catálogo antes de exportar.',
                'identidad_faltante'
            );
        }
        foreach ([
            TiendanubePrecioCsvColumnaCatalogo::IDENTIFICADOR_URL => $handle,
            TiendanubePrecioCsvColumnaCatalogo::SKU => $sku,
        ] as $columna => $valor) {
            if ($this->pareceFormula((string) $valor)) {
                throw new TiendanubePrecioCsvException(
                    'La identidad de una fila no es segura para CSV ('.$columna.').',
                    'identidad_insegura'
                );
            }
        }
    }

    private function pareceFormula(string $valor): bool
    {
        $trim = ltrim($valor);
        if ($trim === '') {
            return false;
        }

        return (bool) preg_match('/^[=+@\t]/', $trim);
    }
}
