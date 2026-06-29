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
            'endpoints_generales' => [
                [
                    'metodo' => 'GET',
                    'ruta' => '/health',
                    'auth' => false,
                    'descripcion' => 'Verificación de disponibilidad (sin autenticación).',
                    'curl' => "curl -s \"{$baseUrl}/health\"",
                ],
                [
                    'metodo' => 'POST',
                    'ruta' => '/auth/token',
                    'auth' => false,
                    'descripcion' => 'Obtener token Bearer con client_id y client_secret.',
                    'curl' => "curl -s -X POST \"{$baseUrl}/auth/token\" \\\n  -H \"Content-Type: application/json\" \\\n  -H \"Accept: application/json\" \\\n  -d '{\"client_id\":\"SU_CLIENT_ID\",\"client_secret\":\"SU_CLIENT_SECRET\"}'",
                ],
            ],
            'instrucciones' => [
                'Siempre use la URL base /api/v1 (no /api, que es la API interna del sistema con sesión web).',
                'En TODAS las peticiones envíe el encabezado Accept: application/json. Sin él, las rutas protegidas pueden responder con redirección al login en lugar de JSON.',
                'El client_secret se almacena cifrado (hash). Use el valor en texto plano mostrado al crear o regenerar la aplicación; no es recuperable después.',
                'Deje IPs permitidas vacías para permitir cualquier IP (API abierta por IP). Restrinja con una IP por línea si lo necesita.',
                'El token expira en 24 horas. Solicite uno nuevo con POST /auth/token.',
            ],
            'guias_cliente_http' => $this->guiasClienteHttp($baseUrl),
            'recursos' => $recursos->map(function (ApiRecurso $recurso) use ($baseUrl) {
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
                    'endpoints' => $this->endpointsParaRecurso($recurso, $baseUrl),
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

    private function endpointsParaRecurso(ApiRecurso $recurso, string $baseUrl): array
    {
        if ($recurso->slug !== 'clientes') {
            return [];
        }

        $authHeaders = "-H \"Authorization: Bearer {TOKEN}\" -H \"Accept: application/json\"";
        $endpoints = [];

        if ($recurso->lectura_habilitada) {
            $endpoints[] = [
                'metodo' => 'GET',
                'ruta' => '/clientes',
                'descripcion' => 'Listado paginado. Query: q, page, per_page (máx. 100).',
                'curl' => "curl -s \"{$baseUrl}/clientes?page=1&per_page=25\" {$authHeaders}",
            ];
            $endpoints[] = [
                'metodo' => 'GET',
                'ruta' => '/clientes/{numero_cliente}',
                'descripcion' => 'Detalle por número de cliente.',
                'curl' => "curl -s \"{$baseUrl}/clientes/C-001\" {$authHeaders}",
            ];
        }

        if ($recurso->escritura_habilitada) {
            $endpoints[] = [
                'metodo' => 'POST',
                'ruta' => '/clientes',
                'descripcion' => 'Crear cliente.',
                'curl' => "curl -s -X POST \"{$baseUrl}/clientes\" {$authHeaders} -H \"Content-Type: application/json\" -d '{\"numero_cliente\":\"C-999\",\"nombre\":\"Ejemplo\"}'",
            ];
            $endpoints[] = [
                'metodo' => 'PUT',
                'ruta' => '/clientes/{numero_cliente}',
                'descripcion' => 'Actualizar cliente.',
                'curl' => "curl -s -X PUT \"{$baseUrl}/clientes/C-001\" {$authHeaders} -H \"Content-Type: application/json\" -d '{\"nombre\":\"Nombre actualizado\"}'",
            ];
        }

        return $endpoints;
    }

    private function guiasClienteHttp(string $baseUrl): array
    {
        return [
            [
                'nombre' => 'Postman',
                'pasos' => [
                    'Cree una colección llamada «GELIANV API v1».',
                    'En la colección → pestaña Variables, agregue: base_url = ' . $baseUrl,
                    'En la colección → pestaña Authorization deje «No Auth» (el token se configura por petición o vía script).',
                    'En la colección → pestaña Headers agregue siempre: Accept = application/json',
                    'Petición 1 — Health: método GET, URL {{base_url}}/health (sin autenticación). Debe responder 200 con {"status":"ok",...}.',
                    'Petición 2 — Token: método POST, URL {{base_url}}/auth/token. Body → raw → JSON:',
                    '{"client_id":"SU_CLIENT_ID","client_secret":"SU_CLIENT_SECRET"}',
                    'Headers adicionales en Token: Content-Type = application/json',
                    'En la pestaña Tests del Token, pegue: pm.environment.set("access_token", pm.response.json().access_token);',
                    'Cree un Environment con variable access_token vacía y selecciónelo antes de enviar peticiones.',
                    'Peticiones protegidas: Authorization → Type «Bearer Token» → Token {{access_token}}',
                    'Ejemplo listar clientes: GET {{base_url}}/clientes?page=1&per_page=25',
                ],
            ],
            [
                'nombre' => 'Thunder Client (VS Code)',
                'pasos' => [
                    'Instale la extensión «Thunder Client» en VS Code.',
                    'Abra Thunder Client → New Request.',
                    'Petición 1 — Health: GET ' . $baseUrl . '/health. Sin headers extra. Send → debe ver status 200.',
                    'Petición 2 — Token: POST ' . $baseUrl . '/auth/token',
                    'En Headers agregue: Accept = application/json y Content-Type = application/json',
                    'En Body → JSON pegue: {"client_id":"SU_CLIENT_ID","client_secret":"SU_CLIENT_SECRET"}',
                    'Envíe y copie access_token de la respuesta.',
                    'Menú Env → agregue variable access_token con el valor copiado (o use {{access_token}} en peticiones).',
                    'Peticiones protegidas: pestaña Auth → Bearer → pegue el token o use {{access_token}}',
                    'Headers obligatorios en cada petición: Accept = application/json',
                    'Ejemplo listar clientes: GET ' . $baseUrl . '/clientes?page=1&per_page=25 con Auth Bearer.',
                    'Guarde las peticiones en una colección «GELIANV API v1» para reutilizarlas.',
                ],
            ],
            [
                'nombre' => 'Encabezados mínimos (todas las herramientas)',
                'pasos' => [
                    'Accept: application/json — obligatorio en todas las peticiones.',
                    'Content-Type: application/json — obligatorio en POST y PUT con cuerpo JSON.',
                    'Authorization: Bearer {access_token} — obligatorio en rutas protegidas (excepto /health y /auth/token).',
                ],
            ],
        ];
    }
}
