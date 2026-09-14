<?php

namespace App\Services\Tiendanube\Precios;

use App\Exceptions\Tiendanube\TiendanubePrecioFuenteException;
use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Models\Tiendanube\TiendanubePrecioLista;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TiendanubePrecioCostoCapturaService
{
    /**
     * @param  array{lista_id?: int|null, origen?: string, import_id?: int|null}  $opciones
     */
    public function capturar(
        int $storeId,
        int $varianteId,
        string $valor,
        string $moneda,
        ?string $motivo = null,
        ?User $user = null,
        string $tipo = TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
        array $opciones = [],
    ): TiendanubePrecioFuenteVersion {
        $moneda = strtoupper(trim($moneda));
        if (preg_match('/^[A-Z]{3}$/', $moneda) !== 1) {
            throw new TiendanubePrecioFuenteException('La moneda debe ser un código ISO de 3 letras.', 'moneda_invalida');
        }

        $parsed = TiendanubePrecioImporte::parse($valor, '.');
        if (! $parsed['ok'] || $parsed['valor'] === null) {
            throw new TiendanubePrecioFuenteException(
                $this->mensajeImporte($parsed['error'] ?? 'numero_invalido'),
                $parsed['error'] ?? 'numero_invalido'
            );
        }

        $variante = TiendanubeProductoVariante::query()->find($varianteId);
        if (! $variante) {
            throw new TiendanubePrecioFuenteException('La variante no existe en el catálogo local.', 'variante_inexistente');
        }

        $listaId = isset($opciones['lista_id']) ? (int) $opciones['lista_id'] : null;
        if ($tipo === TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA) {
            if (! $listaId) {
                throw new TiendanubePrecioFuenteException('La lista de referencia es obligatoria.', 'lista_requerida');
            }
            $lista = TiendanubePrecioLista::query()
                ->where('store_id', $storeId)
                ->find($listaId);
            if (! $lista) {
                throw new TiendanubePrecioFuenteException('La lista no existe.', 'lista_inexistente');
            }
        } else {
            $tipo = TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL;
            $listaId = null;
        }

        $origen = $opciones['origen'] ?? TiendanubePrecioFuenteVersion::ORIGEN_MANUAL;
        $fuenteClave = TiendanubePrecioFuenteVersion::claveFuente($tipo, $listaId);

        return DB::transaction(function () use (
            $storeId,
            $variante,
            $parsed,
            $moneda,
            $motivo,
            $user,
            $tipo,
            $listaId,
            $origen,
            $opciones,
            $fuenteClave,
        ) {
            $actual = TiendanubePrecioFuenteVersion::query()
                ->where('store_id', $storeId)
                ->where('fuente_clave', $fuenteClave)
                ->where('variante_id', $variante->id)
                ->lockForUpdate()
                ->orderByDesc('version')
                ->first();

            if ($actual && strtoupper((string) $actual->moneda) !== $moneda) {
                throw new TiendanubePrecioFuenteException(
                    'La moneda no coincide con la versión vigente ('.$actual->moneda.'). No se convierte automáticamente.',
                    'moneda_incompatible'
                );
            }

            $siguiente = $actual ? ((int) $actual->version) + 1 : 1;

            return TiendanubePrecioFuenteVersion::create([
                'store_id' => $storeId,
                'tipo' => $tipo,
                'lista_id' => $listaId,
                'fuente_clave' => $fuenteClave,
                'variante_id' => (int) $variante->id,
                'producto_id' => (int) $variante->producto_id,
                'version' => $siguiente,
                'moneda' => $moneda,
                'valor_decimal' => $parsed['valor'],
                'fecha' => now(),
                'user_id' => $user?->id,
                'origen' => $origen,
                'motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
                'import_id' => $opciones['import_id'] ?? null,
            ]);
        });
    }

    private function mensajeImporte(string $codigo): string
    {
        return match ($codigo) {
            'negativo' => 'No se aceptan importes negativos.',
            'escala' => 'El importe admite como máximo dos decimales.',
            'rango' => 'El importe excede el rango soportado.',
            'numero_ambiguo' => 'El formato numérico es ambiguo.',
            default => 'El importe no es válido.',
        };
    }
}
