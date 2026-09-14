<?php

namespace App\Services\Tiendanube\Precios\Aplicacion;

use App\Exceptions\Tiendanube\TiendanubePrecioEjecucionException;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubeProducto;
use App\Models\Tiendanube\TiendanubeProductoVariante;
use App\Services\Tiendanube\Precios\TiendanubePrecioDecimal;
use App\Services\Tiendanube\TiendanubeApiClient;
use App\Services\Tiendanube\TiendanubeCatalogoSyncService;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class TiendanubePrecioVarianteWriteService
{
    public const CAMPOS_API = [
        TiendanubePrecioDestino::Normal->value => 'price',
        TiendanubePrecioDestino::Promocional->value => 'promotional_price',
        TiendanubePrecioDestino::CostoRemoto->value => 'cost',
    ];

    public function __construct(
        private readonly TiendanubeApiClient $api,
        private readonly TiendanubeCatalogoSyncService $sync,
    ) {}

    /**
     * @param  array<string, array{intencion: string, valor_final?: mixed}>  $campos
     * @return array<string, mixed>
     */
    public function construirPayload(array $campos): array
    {
        $payload = [];
        foreach (self::CAMPOS_API as $destino => $apiCampo) {
            $campo = $campos[$destino] ?? null;
            if (! is_array($campo)) {
                continue;
            }
            $intencion = TiendanubePrecioIntencion::tryFrom((string) ($campo['intencion'] ?? ''));
            if ($intencion === null || $intencion === TiendanubePrecioIntencion::Conservar) {
                continue;
            }

            if ($destino === TiendanubePrecioDestino::Promocional->value
                && $intencion === TiendanubePrecioIntencion::Eliminar) {
                $payload[$apiCampo] = null;

                continue;
            }

            if ($intencion !== TiendanubePrecioIntencion::Establecer) {
                throw new TiendanubePrecioEjecucionException(
                    'La intención no es válida para el campo '.$destino.'.',
                    'intencion_invalida'
                );
            }

            $serializado = $this->serializarImporte($campo['valor_final'] ?? null, $destino);
            if ($apiCampo === 'cost') {
                if ($serializado === null) {
                    continue;
                }
                if (TiendanubePrecioDecimal::esCero($serializado)) {
                    throw new TiendanubePrecioEjecucionException(
                        'El costo remoto cero no se puede publicar.',
                        'costo_cero'
                    );
                }
            }

            $payload[$apiCampo] = $serializado;
        }

        $this->assertAllowlist($payload);

        if ($payload === []) {
            throw new TiendanubePrecioEjecucionException(
                'No hay campos de precio para enviar.',
                'sin_cambios'
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function escribir(int $storeId, int $productoId, int $varianteId, array $payload): TiendanubePrecioVarianteWriteResult
    {
        $this->assertAllowlist($payload);
        $this->assertPertenencia($storeId, $productoId, $varianteId);

        $put = null;
        $putHecho = false;
        try {
            $put = $this->api->sinReintentoEscrituraAmbigua()->updateVariant($productoId, $varianteId, $payload);
            $putHecho = true;
        } catch (Throwable $e) {
            if ($this->esEscrituraAmbigua($e)) {
                return new TiendanubePrecioVarianteWriteResult(
                    payloadEnviado: $payload,
                    respuestaPut: null,
                    productoCompleto: null,
                    espejoActualizado: false,
                    escrituraIncierta: true,
                    detalleRefresco: $e->getMessage(),
                );
            }

            throw $e;
        }

        if ($this->esProductoCompleto($put, $varianteId)) {
            $this->sync->upsertProducto($put);

            return new TiendanubePrecioVarianteWriteResult($payload, $put, $put, true, false);
        }

        try {
            $producto = $this->api->getProduct($productoId);
        } catch (Throwable $e) {
            return new TiendanubePrecioVarianteWriteResult(
                payloadEnviado: $payload,
                respuestaPut: $put,
                productoCompleto: null,
                espejoActualizado: false,
                escrituraIncierta: true,
                detalleRefresco: $e->getMessage(),
            );
        }

        if (! $this->esProductoCompleto($producto, $varianteId)) {
            return new TiendanubePrecioVarianteWriteResult(
                payloadEnviado: $payload,
                respuestaPut: $put,
                productoCompleto: $producto,
                espejoActualizado: false,
                escrituraIncierta: true,
                detalleRefresco: 'La relectura no devolvió el producto completo.',
            );
        }

        $this->sync->upsertProducto($producto);

        return new TiendanubePrecioVarianteWriteResult($payload, $putHecho ? $put : null, $producto, true, false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertAllowlist(array $payload): void
    {
        $prohibidos = [
            'stock', 'stock_management', 'inventory_levels', 'location_id',
            'sku', 'categories', 'images', 'name', 'values', 'barcode', 'weight',
        ];
        foreach (array_keys($payload) as $clave) {
            if (! in_array($clave, ['price', 'promotional_price', 'cost'], true)) {
                throw new TiendanubePrecioEjecucionException(
                    'El payload de precios solo admite price, promotional_price y cost.',
                    'payload_invalido',
                    422,
                    ['campo' => (string) $clave]
                );
            }
            if (in_array($clave, $prohibidos, true)) {
                throw new TiendanubePrecioEjecucionException(
                    'El payload incluye un campo no permitido.',
                    'payload_invalido'
                );
            }
        }
    }

    public function assertPertenencia(int $storeId, int $productoId, int $varianteId): void
    {
        $config = TiendanubeConfiguracion::obtener();
        if (! $config->store_id || (int) $config->store_id !== $storeId) {
            throw new TiendanubePrecioEjecucionException(
                'La variante no pertenece a la tienda configurada.',
                'tienda_invalida',
                409
            );
        }

        $variante = TiendanubeProductoVariante::query()->find($varianteId);
        if (! $variante || (int) $variante->producto_id !== $productoId) {
            throw new TiendanubePrecioEjecucionException(
                'La variante no pertenece al producto de esta tienda.',
                'variante_ajena',
                409
            );
        }

        if (! TiendanubeProducto::query()->whereKey($productoId)->exists()) {
            throw new TiendanubePrecioEjecucionException(
                'El producto no existe en el catálogo local.',
                'producto_ausente',
                409
            );
        }
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    public function esProductoCompleto(array $documento, int $varianteId): bool
    {
        if (! isset($documento['id']) || ! is_numeric($documento['id'])) {
            return false;
        }
        if (! isset($documento['variants']) || ! is_array($documento['variants'])) {
            return false;
        }
        foreach ($documento['variants'] as $variante) {
            if (is_array($variante) && (int) ($variante['id'] ?? 0) === $varianteId) {
                return true;
            }
        }

        return false;
    }

    private function serializarImporte(mixed $valor, string $destino): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $parsed = TiendanubePrecioDecimal::parseImporte(is_string($valor) ? $valor : (string) $valor);
        if ($parsed === null) {
            throw new TiendanubePrecioEjecucionException(
                'El importe de '.$destino.' no es válido.',
                'importe_invalido'
            );
        }

        return TiendanubePrecioDecimal::serializar($parsed);
    }

    private function esEscrituraAmbigua(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        $mensaje = $e->getMessage();

        return str_contains($mensaje, 'timeout')
            || str_contains($mensaje, 'conexión fallida')
            || str_contains($mensaje, 'connection_failed')
            || str_contains($mensaje, 'HTTP 502')
            || str_contains($mensaje, 'HTTP 503')
            || str_contains($mensaje, 'HTTP 504');
    }
}
