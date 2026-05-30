<?php

namespace App\Services\ApiExterna;

use App\Models\ApiAplicacion;
use App\Models\ApiRecurso;
use App\Models\Cliente;
use Illuminate\Support\Collection;

class ApiFieldFilterService
{
    public function __construct(
        protected ApiPermisoService $permisoService
    ) {}

    public function slugsHabilitados(ApiAplicacion $aplicacion, ApiRecurso $recurso): array
    {
        return $this->permisoService
            ->camposHabilitados($aplicacion, $recurso)
            ->pluck('slug')
            ->all();
    }

    public function filtrarCliente(Cliente $cliente, array $slugs): array
    {
        $cliente->loadMissing(['listaDescuento', 'vendedor', 'tipo']);

        $mapa = [
            'numero_cliente' => $cliente->numero_cliente,
            'nombre' => $cliente->nombre,
            'nombre_razon_social' => $cliente->nombre_razon_social,
            'rfc' => $cliente->rfc,
            'codigo_postal' => $cliente->codigo_postal,
            'regimen_fiscal' => $cliente->regimen_fiscal,
            'correo_electronico' => $cliente->correo_electronico,
            'uso_factura' => $cliente->uso_factura,
            'lista_descuento' => $cliente->listaDescuento?->nombre,
            'vendedor' => $cliente->vendedor?->name,
            'monto_venta_actual' => (float) $cliente->monto_venta_actual,
            'es_heredado' => (bool) $cliente->es_heredado,
            'tipo_cliente' => $cliente->tipo?->nombre,
            'lista_bloqueada' => (bool) $cliente->lista_bloqueada,
        ];

        $resultado = [];

        foreach ($slugs as $slug) {
            if (array_key_exists($slug, $mapa)) {
                $resultado[$slug] = $mapa[$slug];
            }
        }

        return $resultado;
    }

    public function filtrarColeccion(Collection $clientes, ApiAplicacion $aplicacion, ApiRecurso $recurso): array
    {
        $slugs = $this->slugsHabilitados($aplicacion, $recurso);

        return $clientes->map(fn (Cliente $cliente) => $this->filtrarCliente($cliente, $slugs))->all();
    }
}
