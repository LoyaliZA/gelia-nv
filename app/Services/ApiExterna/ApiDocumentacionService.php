<?php

namespace App\Services\ApiExterna;

use App\Models\ApiRecurso;
use Illuminate\Support\Collection;

class ApiDocumentacionService
{
    public function __construct(
        protected ApiPermisoService $permisoService
    ) {}

    public function construirDatos(): array
    {
        $baseUrl = rtrim(config('app.url'), '/') . '/api/v1';

        $recursos = ApiRecurso::with('campos')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return [
            'titulo' => 'GELIANV API Externa v1',
            'fecha' => now()->format('d/m/Y H:i'),
            'base_url' => $baseUrl,
            'recursos' => $recursos->map(function (ApiRecurso $recurso) {
                return [
                    'slug' => $recurso->slug,
                    'nombre' => $recurso->nombre,
                    'lectura_habilitada' => $recurso->lectura_habilitada,
                    'escritura_habilitada' => $recurso->escritura_habilitada,
                    'campos' => $recurso->campos
                        ->where('habilitado_global', true)
                        ->map(fn ($campo) => [
                            'slug' => $campo->slug,
                            'etiqueta' => $campo->etiqueta,
                            'es_sensible' => $campo->es_sensible,
                        ])
                        ->values()
                        ->all(),
                    'endpoints' => $this->endpointsParaRecurso($recurso),
                ];
            })->all(),
            'codigos_error' => [
                ['codigo' => 401, 'descripcion' => 'Credenciales o token inválidos.'],
                ['codigo' => 403, 'descripcion' => 'Aplicación desactivada, IP no permitida o permiso insuficiente.'],
                ['codigo' => 404, 'descripcion' => 'Recurso o registro no encontrado.'],
                ['codigo' => 406, 'descripcion' => 'Falta el encabezado Accept: application/json.'],
                ['codigo' => 422, 'descripcion' => 'Datos de entrada inválidos.'],
                ['codigo' => 429, 'descripcion' => 'Límite de peticiones excedido.'],
                ['codigo' => 500, 'descripcion' => 'Error interno del servidor.'],
            ],
        ];
    }

    private function endpointsParaRecurso(ApiRecurso $recurso): array
    {
        if ($recurso->slug !== 'clientes') {
            return [];
        }

        $endpoints = [];

        if ($recurso->lectura_habilitada) {
            $endpoints[] = ['metodo' => 'GET', 'ruta' => '/clientes', 'descripcion' => 'Listado paginado. Query: q, page, per_page (max 100).'];
            $endpoints[] = ['metodo' => 'GET', 'ruta' => '/clientes/{numero_cliente}', 'descripcion' => 'Detalle por número de cliente.'];
        }

        if ($recurso->escritura_habilitada) {
            $endpoints[] = ['metodo' => 'POST', 'ruta' => '/clientes', 'descripcion' => 'Crear cliente.'];
            $endpoints[] = ['metodo' => 'PUT', 'ruta' => '/clientes/{numero_cliente}', 'descripcion' => 'Actualizar cliente.'];
        }

        return $endpoints;
    }
}
