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

        $tokenExpiracionMovil = (int) config('mobile.token_expiration_days', 30);

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
                'Existen dos modelos de autenticación independientes: aplicaciones externas (client_id/client_secret → /auth/token) y apps móviles nativas (login de usuario → /mobile/login o /passkeys/login/verify). Los tokens no son intercambiables.',
                'El client_secret se almacena cifrado (hash). Use el valor en texto plano mostrado al crear o regenerar la aplicación; no es recuperable después.',
                'Deje IPs permitidas vacías para permitir cualquier IP (API abierta por IP). Restrinja con una IP por línea si lo necesita.',
                'El token de aplicación expira en 24 horas. Solicite uno nuevo con POST /auth/token.',
                'El token móvil expira en '.$tokenExpiracionMovil.' días. Un nuevo login en el mismo dispositivo revoca el token anterior.',
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
                ['codigo' => 401, 'descripcion' => 'Credenciales o token inválidos. En móvil: dispositivo revocado o token de aplicación usado en rutas /mobile.'],
                ['codigo' => 403, 'descripcion' => 'Aplicación desactivada, IP no permitida, permiso insuficiente o usuario sin acceso móvil (requiere clientes.ver o mis_clientes.gestionar).'],
                ['codigo' => 404, 'descripcion' => 'Recurso o registro no encontrado. En móvil: snapshot_id inexistente.'],
                ['codigo' => 406, 'descripcion' => 'Falta el encabezado Accept: application/json.'],
                ['codigo' => 409, 'descripcion' => 'Conflicto de sincronización móvil: scope_changed, cursor_expired o snapshot_not_ready.'],
                ['codigo' => 410, 'descripcion' => 'Snapshot de bootstrap móvil expirado (snapshot_expired).'],
                ['codigo' => 422, 'descripcion' => 'Datos de entrada inválidos. En móvil: bootstrap_mismatch al completar sincronización.'],
                ['codigo' => 429, 'descripcion' => 'Límite de peticiones excedido (aplicaciones: por app; móvil: 60/min por usuario).'],
                ['codigo' => 500, 'descripcion' => 'Error interno del servidor.'],
            ],
            'mobile' => $this->construirMobile($baseUrl, $tokenExpiracionMovil),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function construirMobile(string $baseUrl, int $tokenExpiracionDias): array
    {
        $authHeaders = "-H \"Authorization: Bearer {TOKEN}\" -H \"Accept: application/json\" -H \"X-Mobile-Scope-Version: {SCOPE_VERSION}\"";
        $campos = config('mobile.campos', []);
        $permisosCampos = config('mobile.permisos_campos', []);

        return [
            'introduccion' => 'API para apps móviles nativas. Autenticación por usuario del sistema (no por aplicación externa). Incluye sincronización incremental de clientes con bootstrap paginado y eventos de cambio.',
            'requisitos_acceso' => [
                'El usuario debe tener el permiso clientes.ver (alcance completo) o mis_clientes.gestionar (solo clientes asignados como vendedor).',
                'Cada dispositivo se identifica con device_uuid (UUID v4). Un login nuevo en el mismo dispositivo revoca el token anterior.',
                'Las rutas de sincronización requieren el encabezado X-Mobile-Scope-Version (o query scope_version) con el hash devuelto en login/me.',
            ],
            'instrucciones' => [
                'Autentíquese con POST /mobile/login o, si hay passkey registrada, POST /passkeys/login/options y POST /passkeys/login/verify (client=mobile).',
                'Guarde access_token, expires_at y scope_version de la respuesta.',
                'En rutas /mobile/sync/* envíe Authorization: Bearer, Accept: application/json y X-Mobile-Scope-Version.',
                'Primera sincronización o tras scope_changed: POST /mobile/sync/bootstrap, pagine con GET /mobile/sync/bootstrap, complete con POST /mobile/sync/bootstrap/complete.',
                'Actualizaciones posteriores: GET /mobile/sync/changes?cursor={cursor} usando el cursor devuelto al completar bootstrap.',
                'Opcional: suscríbase al canal privado Reverb App.Models.User.{user_id} y escuche el evento mobile.sync para detectar cambios.',
            ],
            'flujo_sincronizacion' => [
                'POST /mobile/sync/bootstrap → obtiene snapshot_id, total_items y publish_seq_at_start.',
                'GET /mobile/sync/bootstrap?snapshot_id=…&after_cliente_id=0&limit=100 → pagina clientes hasta has_more=false.',
                'POST /mobile/sync/bootstrap/complete con items_count y max_cliente_id → devuelve cursor inicial.',
                'GET /mobile/sync/changes?cursor={cursor} → consume eventos incrementales (granted, updated, deleted, revoked).',
                'Si recibe 409 scope_changed o cursor_expired, reinicie con un nuevo bootstrap.',
            ],
            'endpoints' => [
                [
                    'metodo' => 'POST',
                    'ruta' => '/mobile/login',
                    'auth' => false,
                    'descripcion' => 'Login de usuario. Body: login, password, device_uuid (requerido); device_name, platform, app_version (opcionales).',
                    'curl' => "curl -s -X POST \"{$baseUrl}/mobile/login\" \\\n  -H \"Content-Type: application/json\" \\\n  -H \"Accept: application/json\" \\\n  -d '{\"login\":\"usuario@ejemplo.com\",\"password\":\"***\",\"device_uuid\":\"11111111-1111-1111-1111-111111111111\",\"platform\":\"android\",\"app_version\":\"1.0.0\"}'",
                ],
                [
                    'metodo' => 'POST',
                    'ruta' => '/mobile/logout',
                    'auth' => true,
                    'descripcion' => 'Cierra sesión y revoca el token actual del dispositivo.',
                    'curl' => "curl -s -X POST \"{$baseUrl}/mobile/logout\" {$authHeaders}",
                ],
                [
                    'metodo' => 'GET',
                    'ruta' => '/mobile/me',
                    'auth' => true,
                    'descripcion' => 'Perfil del usuario, permisos, scope_version, tema_visual y datos del dispositivo.',
                    'curl' => "curl -s \"{$baseUrl}/mobile/me\" -H \"Authorization: Bearer {TOKEN}\" -H \"Accept: application/json\"",
                ],
                [
                    'metodo' => 'PATCH',
                    'ruta' => '/mobile/profile',
                    'auth' => true,
                    'descripcion' => 'Actualiza preferencias visuales (tema_visual) y foto de perfil. JSON para tema_visual/remove_foto; multipart POST para foto_perfil.',
                    'curl' => "curl -s -X PATCH \"{$baseUrl}/mobile/profile\" {$authHeaders} \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"tema_visual\":{\"modo\":\"dark\",\"color_nombre\":\"rosa\"}}'",
                ],
                [
                    'metodo' => 'POST',
                    'ruta' => '/passkeys/login/options',
                    'auth' => false,
                    'descripcion' => 'Challenge WebAuthn. Body: login, client (web|mobile). Si client=mobile, device_uuid. Respuesta genérica si el usuario no existe o no tiene passkeys (allowCredentials vacío). Requiere WEBAUTHN_ENABLED=true.',
                    'curl' => "curl -s -X POST \"{$baseUrl}/passkeys/login/options\" \\\n  -H \"Content-Type: application/json\" \\\n  -H \"Accept: application/json\" \\\n  -d '{\"login\":\"usuario@ejemplo.com\",\"client\":\"mobile\",\"device_uuid\":\"11111111-1111-1111-1111-111111111111\"}'",
                ],
                [
                    'metodo' => 'POST',
                    'ruta' => '/passkeys/login/verify',
                    'auth' => false,
                    'descripcion' => 'Verifica assertion. En móvil devuelve el mismo JSON que /mobile/login. En web: { redirect }. Body incluye credential WebAuthn y, en móvil, device_uuid.',
                    'curl' => "curl -s -X POST \"{$baseUrl}/passkeys/login/verify\" \\\n  -H \"Content-Type: application/json\" \\\n  -H \"Accept: application/json\" \\\n  -d '{\"login\":\"usuario@ejemplo.com\",\"client\":\"mobile\",\"device_uuid\":\"11111111-1111-1111-1111-111111111111\",\"credential\":{}}'",
                ],
                [
                    'metodo' => 'POST',
                    'ruta' => '/passkeys/register/options',
                    'auth' => true,
                    'descripcion' => 'Opciones de registro de passkey. Requiere sesión web (cookie) o Bearer móvil. Body: client, nickname opcional.',
                    'curl' => "curl -s -X POST \"{$baseUrl}/passkeys/register/options\" {$authHeaders} \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"client\":\"mobile\",\"nickname\":\"Pixel 8\",\"device_uuid\":\"11111111-1111-1111-1111-111111111111\"}'",
                ],
                [
                    'metodo' => 'GET',
                    'ruta' => '/passkeys',
                    'auth' => true,
                    'descripcion' => 'Lista passkeys del usuario (sin clave pública).',
                    'curl' => "curl -s \"{$baseUrl}/passkeys\" -H \"Authorization: Bearer {TOKEN}\" -H \"Accept: application/json\"",
                ],
                [
                    'metodo' => 'DELETE',
                    'ruta' => '/passkeys/{id}',
                    'auth' => true,
                    'descripcion' => 'Revoca una passkey (revocado_at). El id es el credential_id WebAuthn.',
                    'curl' => "curl -s -X DELETE \"{$baseUrl}/passkeys/{id}\" -H \"Authorization: Bearer {TOKEN}\" -H \"Accept: application/json\"",
                ],
                [
                    'metodo' => 'POST',
                    'ruta' => '/mobile/sync/bootstrap',
                    'auth' => true,
                    'descripcion' => 'Crea snapshot de sincronización completa. Respuesta 201.',
                    'curl' => "curl -s -X POST \"{$baseUrl}/mobile/sync/bootstrap\" {$authHeaders}",
                ],
                [
                    'metodo' => 'GET',
                    'ruta' => '/mobile/sync/bootstrap',
                    'auth' => true,
                    'descripcion' => 'Página de clientes del snapshot. Query: snapshot_id (UUID), after_cliente_id (default 0), limit (1-100, default '.config('mobile.bootstrap_page_size', 100).').',
                    'curl' => "curl -s \"{$baseUrl}/mobile/sync/bootstrap?snapshot_id={SNAPSHOT_ID}&after_cliente_id=0&limit=100\" {$authHeaders}",
                ],
                [
                    'metodo' => 'POST',
                    'ruta' => '/mobile/sync/bootstrap/complete',
                    'auth' => true,
                    'descripcion' => 'Confirma bootstrap. Body: snapshot_id, items_count, max_cliente_id (opcional). Devuelve cursor.',
                    'curl' => "curl -s -X POST \"{$baseUrl}/mobile/sync/bootstrap/complete\" {$authHeaders} \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"snapshot_id\":\"{SNAPSHOT_ID}\",\"items_count\":150,\"max_cliente_id\":999}'",
                ],
                [
                    'metodo' => 'GET',
                    'ruta' => '/mobile/sync/changes/head',
                    'auth' => true,
                    'descripcion' => 'Consulta max_seq y scope_version actuales sin consumir eventos.',
                    'curl' => "curl -s \"{$baseUrl}/mobile/sync/changes/head\" {$authHeaders}",
                ],
                [
                    'metodo' => 'GET',
                    'ruta' => '/mobile/sync/changes',
                    'auth' => true,
                    'descripcion' => 'Eventos incrementales. Query: cursor (default 0), limit (máx. '.config('mobile.changes_page_size', 200).').',
                    'curl' => "curl -s \"{$baseUrl}/mobile/sync/changes?cursor=0&limit=200\" {$authHeaders}",
                ],
            ],
            'respuesta_login' => [
                'access_token' => 'Token Bearer (ability mobile)',
                'token_type' => 'Bearer',
                'expires_at' => 'Fecha ISO-8601 (expira en '.$tokenExpiracionDias.' días)',
                'scope_version' => 'Hash SHA-256 del alcance y permisos del usuario',
                'user' => 'id, name, username, email',
                'permissions' => 'Lista de permisos del usuario',
                'tema_visual' => 'Preferencias visuales (modo, color, layout_sidebar_mobile, etc.)',
                'device' => 'id, device_uuid, nombre, plataforma, app_version, last_seen_at',
            ],
            'respuesta_cliente' => [
                'Siempre incluye: id, numero_cliente, nombre, alcance (full | vendedor | none).',
                'Grupo base: siempre visible.',
                'Grupo credito: requiere permiso cobranza.editar_credito.',
                'Grupo fiscal: requiere permiso clientes.ver.',
            ],
            'eventos_cambio' => [
                'operation: granted — cliente nuevo visible para el usuario.',
                'operation: updated — cliente modificado (incluye data con campos visibles).',
                'operation: deleted — cliente eliminado.',
                'operation: revoked — el cliente dejó de ser visible (cambio de vendedor o permisos).',
                'Cada evento incluye: seq, aggregate_type, aggregate_id, scope_before, scope_after.',
            ],
            'evento_realtime' => [
                'canal' => 'App.Models.User.{user_id} (privado, Reverb/Pusher)',
                'nombre' => 'mobile.sync',
                'payload' => 'type, requires_bootstrap (bool), max_seq (int)',
            ],
            'campos' => collect($campos)->map(function (array $slugs, string $grupo) use ($permisosCampos) {
                $permisos = $permisosCampos[$grupo] ?? [];

                return [
                    'grupo' => $grupo,
                    'permisos_requeridos' => $permisos === [] ? 'Siempre visible' : implode(', ', $permisos),
                    'campos' => $slugs,
                ];
            })->values()->all(),
            'limites' => [
                'Peticiones: 60 por minuto por usuario autenticado.',
                'Bootstrap: páginas de hasta '.config('mobile.bootstrap_page_size', 100).' clientes; snapshot válido '.config('mobile.bootstrap_ttl_hours', 24).' horas.',
                'Changes: hasta '.config('mobile.changes_page_size', 200).' eventos por petición.',
            ],
            'guias_cliente_http' => $this->guiasClienteHttpMobile($baseUrl),
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

    private function guiasClienteHttpMobile(string $baseUrl): array
    {
        return [
            [
                'nombre' => 'Postman (app móvil)',
                'pasos' => [
                    'En la colección «GELIANV API v1», agregue variables: base_url = '.$baseUrl.', mobile_token, scope_version, snapshot_id.',
                    'Petición — Login móvil: POST {{base_url}}/mobile/login. Body JSON:',
                    '{"login":"USUARIO","password":"CONTRASEÑA","device_uuid":"11111111-1111-1111-1111-111111111111","platform":"android"}',
                    'En Tests del login: pm.environment.set("mobile_token", pm.response.json().access_token); pm.environment.set("scope_version", pm.response.json().scope_version);',
                    'Peticiones protegidas móvil: Authorization Bearer {{mobile_token}}, Accept application/json, X-Mobile-Scope-Version {{scope_version}}.',
                    'Bootstrap: POST {{base_url}}/mobile/sync/bootstrap → guarde snapshot_id de la respuesta.',
                    'Página bootstrap: GET {{base_url}}/mobile/sync/bootstrap?snapshot_id={{snapshot_id}}&after_cliente_id=0&limit=100',
                    'Complete: POST {{base_url}}/mobile/sync/bootstrap/complete con body {"snapshot_id":"{{snapshot_id}}","items_count":N,"max_cliente_id":ID}',
                    'Cambios: GET {{base_url}}/mobile/sync/changes?cursor=0',
                ],
            ],
            [
                'nombre' => 'Encabezados móvil (sincronización)',
                'pasos' => [
                    'Accept: application/json — obligatorio.',
                    'Authorization: Bearer {mobile_token} — token obtenido en /mobile/login (no usar token de /auth/token).',
                    'X-Mobile-Scope-Version: {scope_version} — obligatorio en rutas /mobile/sync/*; valor de login o /mobile/me.',
                    'Content-Type: application/json — en POST con cuerpo JSON.',
                ],
            ],
        ];
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
