<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioCampoCondicion;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioOperacion;
use App\Support\Tiendanube\Precios\TiendanubePrecioRedondeoDireccion;
use App\Support\Tiendanube\Precios\TiendanubePrecioRedondeoModo;

final class TiendanubePrecioMotorReglaDto
{
    /**
     * @param  list<TiendanubePrecioMotorCondicionDto>  $condiciones
     */
    public function __construct(
        public readonly string $id,
        public readonly array $condiciones,
        public readonly TiendanubePrecioCampoCondicion $base,
        public readonly TiendanubePrecioOperacion $operacion,
        public readonly TiendanubePrecioDestino $destino,
        public readonly TiendanubePrecioRedondeoModo $redondeo,
        public readonly ?string $parametro = null,
        public readonly ?int $baseListaId = null,
        public readonly TiendanubePrecioRedondeoDireccion $redondeoDireccion = TiendanubePrecioRedondeoDireccion::Arriba,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function fromArray(array $datos): self
    {
        $condiciones = [];
        foreach ($datos['condiciones'] ?? [] as $condicion) {
            $condiciones[] = TiendanubePrecioMotorCondicionDto::fromArray($condicion);
        }

        $redondeo = isset($datos['redondeo'])
            ? TiendanubePrecioRedondeoModo::from((string) $datos['redondeo'])
            : TiendanubePrecioRedondeoModo::DosDecimalesHalfUp;

        $direccion = isset($datos['redondeo_direccion'])
            ? TiendanubePrecioRedondeoDireccion::from((string) $datos['redondeo_direccion'])
            : TiendanubePrecioRedondeoDireccion::Arriba;

        return new self(
            id: (string) ($datos['id'] ?? ''),
            condiciones: $condiciones,
            base: TiendanubePrecioCampoCondicion::from((string) ($datos['base'] ?? '')),
            operacion: TiendanubePrecioOperacion::from((string) ($datos['operacion'] ?? '')),
            destino: TiendanubePrecioDestino::from((string) ($datos['destino'] ?? '')),
            redondeo: $redondeo,
            parametro: isset($datos['parametro']) ? (string) $datos['parametro'] : null,
            baseListaId: isset($datos['base_lista_id']) ? (int) $datos['base_lista_id'] : null,
            redondeoDireccion: $direccion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'condiciones' => array_map(
                fn (TiendanubePrecioMotorCondicionDto $c) => $c->toArray(),
                $this->condiciones
            ),
            'base' => $this->base->value,
            'base_lista_id' => $this->baseListaId,
            'operacion' => $this->operacion->value,
            'parametro' => $this->parametro,
            'destino' => $this->destino->value,
            'redondeo' => $this->redondeo->value,
            'redondeo_direccion' => $this->redondeoDireccion->value,
        ];
    }
}
