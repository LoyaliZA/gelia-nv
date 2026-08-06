<?php

namespace App\Services\Productos\Extensiones;

use App\Models\Producto;
use App\Models\ProductoNotaOlfativa;
use App\Services\Productos\GuardarNotasOlfativasProductoService;
use App\Services\Productos\ResolverExtensionesProductoService;
use InvalidArgumentException;

class PerfumeriaExtensionService
{
    public function __construct(
        private readonly ResolverExtensionesProductoService $resolver,
        private readonly GuardarNotasOlfativasProductoService $guardarNotas,
    ) {}

    public function codigo(): string
    {
        return 'perfumeria';
    }

    public function disponiblePara(Producto $producto): bool
    {
        return $this->resolver->tiene($producto, $this->codigo());
    }

    /**
     * @return array{version:string,notas:array{salida:list<string>,corazon:list<string>,fondo:list<string>}}|null
     */
    public function serializar(Producto $producto): ?array
    {
        if (! $this->disponiblePara($producto)) {
            return null;
        }

        return [
            'version' => '1',
            'notas' => $this->cargarNotas($producto),
        ];
    }

    /**
     * @return array{salida:list<string>,corazon:list<string>,fondo:list<string>}
     */
    public function cargarNotas(Producto $producto): array
    {
        $producto->loadMissing([
            'notasOlfativas.nota:id,nombre',
            'notasOlfativas.fase:id,codigo,orden',
        ]);

        $out = ['salida' => [], 'corazon' => [], 'fondo' => []];
        foreach ($producto->notasOlfativas->sortBy(fn (ProductoNotaOlfativa $n) => [($n->fase?->orden ?? 99), $n->orden]) as $n) {
            $codigo = $n->fase?->codigo;
            if (! $codigo || ! isset($out[$codigo])) {
                continue;
            }
            if ($n->nota?->nombre) {
                $out[$codigo][] = $n->nota->nombre;
            }
        }

        return $out;
    }

    /**
     * @param  array{salida?:list, corazon?:list, fondo?:list}  $data
     */
    public function guardar(Producto $producto, array $data): void
    {
        if (! $this->disponiblePara($producto)) {
            throw new InvalidArgumentException('La extensión Perfumería no está habilitada para la categoría de este producto.');
        }

        $this->guardarNotas->sincronizar($producto, [
            'salida' => $data['salida'] ?? [],
            'corazon' => $data['corazon'] ?? [],
            'fondo' => $data['fondo'] ?? [],
        ]);
    }

    public function tieneDatosHistoricos(Producto $producto): bool
    {
        return ProductoNotaOlfativa::query()->where('producto_id', $producto->id)->exists();
    }
}
