<?php

namespace App\Services\GeliaAi;

use App\Models\Producto;
use App\Models\User;
use App\Services\GeliaAi\Acciones\AccionRegistry;
use App\Services\GeliaAi\Knowledge\ModulosKnowledge;
use App\Services\GeliaAi\Tools\BuscarProductoInventarioTool;
use App\Services\GeliaAi\Tools\ConsultarAyudaModuloTool;
use RuntimeException;

class GeliaAiChatService
{
    public function __construct(
        private DeepSeekClient $client,
        private ConsultarAyudaModuloTool $ayudaTool,
        private BuscarProductoInventarioTool $inventarioTool,
        private SanitizarContextoAi $sanitizer,
        private ModulosKnowledge $knowledge,
        private GeliaAiArchivoService $archivos,
        private AccionRegistry $acciones,
    ) {}


    /**
     * @param  list<array{role: string, content: string}>  $historial
     * @param  list<string>  $fileIds
     * @return array{reply: string, usage: array<string, mixed>|null, propuesta_accion: array<string, mixed>|null}
     */
    public function chat(User $user, string $mensaje, array $historial = [], array $fileIds = []): array
    {
        if (! $this->client->estaConfigurado()) {
            throw new RuntimeException('DeepSeek no está configurado. Configura deepseek.api_token y deepseek.base_url en Configuración del sistema.');
        }

        $mensaje = trim($mensaje);
        if ($mensaje === '') {
            throw new RuntimeException('Mensaje vacío.');
        }

        $maxTurns = (int) config('gelia_ai.max_history_turns', 4);
        $historial = $this->filtrarHistorial($historial, $maxTurns);

        $resumenes = $this->archivos->resúmenesParaLlm($user, $fileIds);
        $tieneArchivos = $resumenes !== [];
        $intencionOperativa = $tieneArchivos || $this->detectarIntencionOperativa($mensaje);

        $system = $this->systemPrompt($intencionOperativa);
        if ($tieneArchivos) {
            // Solo metadatos compactos — nunca filas/celdas.
            $system .= "\nAdjuntos (solo metadatos): ".json_encode($resumenes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $system .= "\nOBLIGATORIO: llama YA la tool proponer_accion_operativa con accion+payload usando file_id UUID de Adjuntos (nunca nombres de archivo). No expliques el módulo; no inventes tools.";
        }

        $tools = null;
        $toolChoice = null;
        $mode = 'tools';
        $propuesta = null;
        $facts = null;

        $codigo = $tieneArchivos ? null : $this->extraerCodigoProducto($mensaje);
        if ($codigo !== null) {
            $facts = $this->inventarioTool->ejecutar($user, ['q' => $codigo, 'limit' => 1, 'con_precios' => true]);
            $facts = $this->sanitizer->limpiar($facts);
            $system .= "\nDatos stock (JSON): ".json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $mode = 'prefetch_inventario';
        } elseif (! $intencionOperativa && ($modulo = $this->detectarModuloAyuda($mensaje))) {
            $system .= "\nHechos módulo:\n".$this->knowledge->fragmento($modulo);
            $mode = 'prefetch_ayuda';
        } elseif (! $tieneArchivos && (
            $this->pareceConsultaStock($mensaje, $historial)
            || Producto::tokensBusqueda($mensaje) !== []
        )) {
            // Prefetch: evita tools+2 rondas en nombre de producto / stock (logs: 8 chars → 1111 tokens).
            $facts = $this->inventarioTool->ejecutar($user, ['q' => $mensaje, 'limit' => 3, 'con_precios' => true]);
            $facts = $this->sanitizer->limpiar($facts);
            $system .= "\nDatos stock (JSON): ".json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $mode = 'prefetch_inventario';
        } elseif ($tieneArchivos) {
            // Evidencia: con ayuda+inventario el modelo distrae y en ronda 2 ya no puede proponer.
            $tools = $this->acciones->schemasParaTools();
            $toolChoice = [
                'type' => 'function',
                'function' => ['name' => 'proponer_accion_operativa'],
            ];
            $mode = 'tools_archivos';
        } elseif ($intencionOperativa) {
            $tools = [
                $this->ayudaTool->schema(),
                $this->inventarioTool->schema(),
                ...$this->acciones->schemasParaTools(),
            ];
            $mode = 'tools_operativo';
        } else {
            // Saludos / sin tokens buscables: 1 ronda, sin schemas de tools.
            $mode = 'chat_corto';
        }

        $messages = [
            ['role' => 'system', 'content' => $system],
        ];

        foreach ($historial as $turn) {
            $messages[] = [
                'role' => ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $turn['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $mensaje];

        $maxTokens = (int) config('gelia_ai.max_tokens', 400);
        $maxRounds = (int) config('gelia_ai.max_tool_rounds', 2);
        $usage = null;
        $usageAcc = ['prompt' => 0, 'completion' => 0, 'total' => 0, 'rounds' => 0];
        $activeTools = $tools;
        $activeToolChoice = $toolChoice;

        for ($round = 0; $round <= $maxRounds; $round++) {
            $response = $this->client->chat($messages, $activeTools, $maxTokens, $activeTools ? $activeToolChoice : null);
            $usage = $response['usage'] ?? $usage;
            if (is_array($usage)) {
                $usageAcc['prompt'] += (int) ($usage['prompt_tokens'] ?? 0);
                $usageAcc['completion'] += (int) ($usage['completion_tokens'] ?? 0);
                $usageAcc['total'] += (int) ($usage['total_tokens'] ?? 0);
                $usageAcc['rounds']++;
            }

            $choice = $response['choices'][0] ?? null;
            $msg = is_array($choice) ? ($choice['message'] ?? null) : null;
            if (! is_array($msg)) {
                throw new RuntimeException('Respuesta inválida de DeepSeek.');
            }

            $toolCalls = $msg['tool_calls'] ?? null;

            if (is_array($toolCalls) && $toolCalls !== [] && $round < $maxRounds && $activeTools) {
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $msg['content'] ?? null,
                    'tool_calls' => $toolCalls,
                ];

                $yaBuscoInventario = false;
                foreach ($toolCalls as $call) {
                    $toolName = (string) ($call['function']['name'] ?? '');
                    // Una sola búsqueda de inventario por ronda (el modelo a veces dispara 3 en paralelo).
                    if ($toolName === $this->inventarioTool->name()) {
                        if ($yaBuscoInventario) {
                            continue;
                        }
                        $yaBuscoInventario = true;
                    }
                    $toolResult = $this->ejecutarToolCall($user, $call, $resumenes);
                    if (($toolResult['_propuesta'] ?? null) !== null) {
                        $propuesta = $toolResult['_propuesta'];
                    }
                    unset($toolResult['_propuesta']);
                    $messages[] = $toolResult;
                }

                // Con propuesta ya lista no hace falta 2ª ronda (evita narración / DSML).
                if ($propuesta !== null) {
                    $reply = (string) ($propuesta['resumen_corto'] ?? 'Propongo una acción. Confirma para ejecutar.');

                    return [
                        'reply' => $reply,
                        'usage' => [
                            ...(is_array($usage) ? $usage : []),
                            'gelia_acc' => $usageAcc,
                            'gelia_mode' => $mode,
                        ],
                        'propuesta_accion' => $propuesta,
                    ];
                }

                $activeTools = null;
                $activeToolChoice = null;

                continue;
            }

            $reply = trim((string) ($msg['content'] ?? ''));
            if ($reply === '') {
                $reply = $propuesta
                    ? (string) ($propuesta['resumen_corto'] ?? 'Propongo una acción. Confirma para ejecutar.')
                    : 'No pude generar una respuesta. Intenta reformular tu pregunta.';
            }

            return [
                'reply' => $reply,
                'usage' => [
                    ...(is_array($usage) ? $usage : []),
                    'gelia_acc' => $usageAcc,
                    'gelia_mode' => $mode,
                ],
                'propuesta_accion' => $propuesta,
            ];
        }

        return [
            'reply' => 'Se alcanzó el límite de consultas internas. Reformula con más detalle (p. ej. un SKU concreto).',
            'usage' => is_array($usage) ? $usage : null,
            'propuesta_accion' => $propuesta,
        ];
    }

    private function systemPrompt(bool $operativo = false): string
    {
        $base = 'Eres GELIA (Gelia). Español, breve. Explica listados/solicitudes; reporta stock/precio/ficha solo con datos dados. No inventes existencias, costos, datos fiscales, atributos, notas olfativas, productos relacionados ni rankings de ventas.';
        $stock = ' En stock: si n>0, SIEMPRE lista los items (nombre+SKU+s+p) aunque exacto=false; nunca digas n=0 si items no está vacío. Si sugerir=true, lista coincidencias y pregunta cuál. Si n=0, pide otro nombre o SKU. Claves JSON: s=stock (e/d, tipo almacén), p=precios (co/pv), attrs=atributos, ext=extensiones (solo claves presentes; p.ej. ext.perfumeria.notas), rel=relacionados, ven=ventas, cont=contenido. Extensiones son opcionales por categoría: si ext no trae perfumeria, no menciones notas olfativas. Si falta attrs/cont dilo; no inventes. Prioriza almacén con contexto=true; si no hay en PDV pero sí en otro almacén, dilo. Si rel tiene hermanos, puedes mencionarlos sin inventar su stock.';
        if ($operativo) {
            return $base.$stock.' Puedes proponer importar costos/inventarios o generar listado; nunca ejecutes sin confirmación del usuario. Otras FO: solo guía al módulo.';
        }

        return $base.$stock.' No modifiques registros.';
    }

    private function detectarIntencionOperativa(string $mensaje): bool
    {
        $m = mb_strtolower($mensaje);

        // "costo/precio" solos = consulta de producto, no importación.
        return (bool) preg_match(
            '/\b(importar|importe|generar?\s+lista|listado|resurtido|sub[ií]|archivo|excel|csv)\b/u',
            $m
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $historial
     */
    private function pareceConsultaStock(string $mensaje, array $historial = []): bool
    {
        if ($this->mensajePareceStock($mensaje)) {
            return true;
        }

        // Continuación corta tras una pregunta de stock ("y el de 50ml?", "mandarin sky").
        if (mb_strlen(trim($mensaje)) < 3) {
            return false;
        }
        foreach (array_reverse($historial) as $turn) {
            if (($turn['role'] ?? '') === 'user' && $this->mensajePareceStock((string) ($turn['content'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private function mensajePareceStock(string $mensaje): bool
    {
        $m = mb_strtolower($mensaje);

        return (bool) preg_match(
            '/\b(cu[aá]nt[oa]s?|quedan?|stock|existencia|existencias|inventario|disponibles?)\b/u',
            $m
        );
    }

    /**
     * @param  list<array{role?: string, content?: string}>  $historial
     * @return list<array{role: string, content: string}>
     */
    private function filtrarHistorial(array $historial, int $maxTurns): array
    {
        $out = [];
        foreach ($historial as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($role === 'assistant' && (
                str_contains($content, 'Hola, soy GELIA')
                || str_contains($content, 'Hola, soy Gel-IA')
                || str_contains($content, 'Hola, soy GEL-IA')
            )) {
                continue;
            }
            if ($role === 'assistant' && mb_strlen($content) > 400) {
                $content = mb_substr($content, 0, 400).'…';
            }
            $out[] = ['role' => $role, 'content' => $content];
        }

        return array_slice($out, -$maxTurns);
    }

    private function extraerCodigoProducto(string $mensaje): ?string
    {
        if (preg_match('/\b(\d{8,14})\b/', $mensaje, $m)) {
            return $m[1];
        }
        if (preg_match('/\b([A-Z0-9][-A-Z0-9]{2,31})\b/i', $mensaje, $m)) {
            $cand = strtoupper($m[1]);
            if (preg_match('/^(STOCK|PRODUCTO|LISTADO|SOLICITUD|COMO|FUNCIONAN|REVISA|ESTE|PARA)$/i', $cand)) {
                return null;
            }
            if (preg_match('/[0-9]/', $cand)) {
                return $cand;
            }
        }

        return null;
    }

    private function detectarModuloAyuda(string $mensaje): ?string
    {
        $m = mb_strtolower($mensaje);
        $pideAyuda = (bool) preg_match('/\b(c[oó]mo|qu[eé] es|para qu[eé]|explica|funcionan|flujo|pasos)\b/u', $m);
        if (! $pideAyuda) {
            return null;
        }
        if (str_contains($m, 'listado')) {
            return 'listados';
        }
        if (str_contains($m, 'solicitud')) {
            return 'solicitudes';
        }
        if (str_contains($m, 'inventario') || str_contains($m, 'almacen') || str_contains($m, 'almacén')) {
            return 'inventario';
        }
        if (str_contains($m, 'producto')) {
            return 'productos';
        }
        if (str_contains($m, 'venta') || str_contains($m, 'reporte')) {
            return 'ventas';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $call
     * @param  list<array{file_id: string, original_name: string, kind: string, headers: list<string>, rows: int, guess_mapping: array<string, string>}>  $resumenes
     * @return array<string, mixed>
     */
    private function ejecutarToolCall(User $user, array $call, array $resumenes = []): array
    {
        $id = (string) ($call['id'] ?? 'tool');
        $fn = $call['function'] ?? [];
        $name = (string) ($fn['name'] ?? '');
        $rawArgs = (string) ($fn['arguments'] ?? '{}');
        $args = json_decode($rawArgs, true);
        if (! is_array($args)) {
            $args = [];
        }

        $propuesta = null;

        if ($name === 'proponer_accion_operativa') {
            $accion = (string) ($args['accion'] ?? '');
            $payload = is_array($args['payload'] ?? null) ? $args['payload'] : [];
            $payload = $this->normalizarPayloadArchivos($payload, $resumenes);
            $resumen = (string) ($args['resumen_corto'] ?? '');

            if (! $this->acciones->soporta($accion)) {
                $result = ['ok' => false, 'error' => "Acción no soportada: {$accion}"];
            } else {
                $accionObj = $this->acciones->obtener($accion);
                $puede = $user->can($accionObj->permiso());
                $propuesta = [
                    'accion' => $accion,
                    'payload' => $payload,
                    'resumen_corto' => $resumen !== '' ? $resumen : "Ejecutar {$accion}",
                    'permiso' => $accionObj->permiso(),
                    'puede' => $puede,
                ];
                $result = [
                    'ok' => true,
                    'propuesta_registrada' => true,
                    'puede_ejecutar' => $puede,
                    'aviso' => $puede
                        ? 'Propuesta lista. El usuario debe confirmar en UI; no ejecutes tú.'
                        : 'Usuario sin permiso; informa que no puede ejecutar.',
                ];
            }
        } else {
            if ($name === $this->inventarioTool->name()) {
                $args['con_precios'] = true;
            }
            $result = match ($name) {
                $this->ayudaTool->name() => $this->ayudaTool->ejecutar($user, $args),
                $this->inventarioTool->name() => $this->inventarioTool->ejecutar($user, $args),
                default => ['ok' => false, 'error' => "Herramienta desconocida: {$name}"],
            };
        }

        $clean = $this->sanitizer->limpiar($result);

        return [
            'role' => 'tool',
            'tool_call_id' => $id,
            'content' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '_propuesta' => $propuesta,
        ];
    }

    /**
     * Sustituye nombres de archivo por file_id UUID usando metadatos locales.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<array{file_id: string, original_name: string, kind: string}>  $resumenes
     * @return array<string, mixed>
     */
    private function normalizarPayloadArchivos(array $payload, array $resumenes): array
    {
        if ($resumenes === []) {
            return $payload;
        }

        $byId = [];
        $byName = [];
        $byKind = [];
        foreach ($resumenes as $r) {
            $id = (string) ($r['file_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $byId[$id] = $id;
            $name = mb_strtolower((string) ($r['original_name'] ?? ''));
            if ($name !== '') {
                $byName[$name] = $id;
            }
            $kind = (string) ($r['kind'] ?? '');
            if ($kind !== '' && ! isset($byKind[$kind])) {
                $byKind[$kind] = $id;
            }
        }

        $resolve = function (mixed $val) use ($byId, $byName): ?string {
            if (! is_string($val) || $val === '') {
                return null;
            }
            if (isset($byId[$val])) {
                return $val;
            }
            $key = mb_strtolower($val);

            return $byName[$key] ?? null;
        };

        if (isset($payload['file_id'])) {
            $resolved = $resolve($payload['file_id']);
            if ($resolved) {
                $payload['file_id'] = $resolved;
            }
        }

        if (is_array($payload['file_ids'] ?? null)) {
            foreach ($payload['file_ids'] as $rol => $val) {
                $resolved = $resolve($val);
                if ($resolved) {
                    $payload['file_ids'][$rol] = $resolved;
                } elseif (is_string($rol) && isset($byKind[$rol])) {
                    $payload['file_ids'][$rol] = $byKind[$rol];
                }
            }
            foreach (['existencias', 'precios', 'costos'] as $rol) {
                if (empty($payload['file_ids'][$rol]) && isset($byKind[$rol])) {
                    $payload['file_ids'][$rol] = $byKind[$rol];
                }
            }
        }

        return $payload;
    }
}
