<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;

final class TiendanubePrecioMotorResultadoDto
{
    /**
     * @param  array<string, TiendanubePrecioMotorResultadoCampoDto>  $campos
     * @param  list<string>  $alertas
     * @param  list<string>  $errores
     */
    public function __construct(
        public readonly int $tiendaId,
        public readonly int $productoId,
        public readonly int $varianteId,
        public readonly string $motorVersion,
        public readonly array $campos,
        public readonly bool $publicable,
        public readonly ?string $margenEstimado = null,
        public readonly ?string $costoUsado = null,
        public readonly ?string $diferenciaAbsoluta = null,
        public readonly ?string $variacionPorcentual = null,
        public readonly bool $margenCalculable = false,
        public readonly bool $variacionPorcentualCalculable = false,
        public readonly array $alertas = [],
        public readonly array $errores = [],
    ) {}

    public function campo(TiendanubePrecioDestino $destino): ?TiendanubePrecioMotorResultadoCampoDto
    {
        return $this->campos[$destino->value] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $campos = [];
        foreach ($this->campos as $clave => $campo) {
            $campos[$clave] = $campo->toArray();
        }

        return [
            'tienda_id' => $this->tiendaId,
            'producto_id' => $this->productoId,
            'variante_id' => $this->varianteId,
            'motor_version' => $this->motorVersion,
            'campos' => $campos,
            'publicable' => $this->publicable,
            'margen_estimado' => $this->margenEstimado,
            'costo_usado' => $this->costoUsado,
            'diferencia_absoluta' => $this->diferenciaAbsoluta,
            'variacion_porcentual' => $this->variacionPorcentual,
            'margen_calculable' => $this->margenCalculable,
            'variacion_porcentual_calculable' => $this->variacionPorcentualCalculable,
            'alertas' => $this->alertas,
            'errores' => $this->errores,
        ];
    }
}
