<?php

namespace App\Services\GeliaAi\Tools;

use App\Models\User;
use App\Services\GeliaAi\Acciones\AccionRegistry;
use App\Services\GeliaAi\Acciones\PropuestasAccionGeliaAi;
use App\Services\GeliaAi\SanitizarContextoAi;
use Throwable;

/**
 * Catálogo de tools ofrecidas al modelo y ejecución segura de cada llamada.
 * Toda llamada produce un resultado JSON (ok=false con error_code si se rechaza).
 */
class EjecutorHerramientasGeliaAi
{
    public function __construct(
        private ConsultarAyudaModuloTool $ayuda,
        private BuscarProductoInventarioTool $inventario,
        private ListarAlmacenesTool $almacenes,
        private AccionRegistry $acciones,
        private PropuestasAccionGeliaAi $propuestas,
        private ValidarArgumentosHerramienta $validador,
        private SanitizarContextoAi $sanitizer,
    ) {}

    /**
     * Schemas disponibles para la persona usuaria, opcionalmente limitados a ciertos nombres.
     *
     * @param  list<string>|null  $soloNombres
     * @return list<array<string, mixed>>
     */
    public function schemas(User $user, ?array $soloNombres = null): array
    {
        $out = [];
        foreach ($this->herramientas() as $tool) {
            if ($tool->disponiblePara($user)) {
                $out[] = $tool->schema();
            }
        }
        foreach ($this->acciones->todas() as $accion) {
            if ($user->can($accion->permiso())) {
                $out[] = $this->acciones->schemaTool($accion);
            }
        }

        if ($soloNombres !== null) {
            $out = array_values(array_filter($out, fn ($s) => in_array($s['function']['name'], $soloNombres, true)));
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $ofrecidas  schemas enviados en este turno
     * @param  list<array<string, mixed>>  $archivos
     * @return array{nombre: string, args: array<string, mixed>, resultado: array<string, mixed>, content: string, propuesta: array<string, mixed>|null}
     */
    public function ejecutar(User $user, string $nombre, string $rawArgs, array $ofrecidas, array $archivos = []): array
    {
        $propuesta = null;
        $args = [];
        $schema = null;
        foreach ($ofrecidas as $s) {
            if (($s['function']['name'] ?? null) === $nombre) {
                $schema = $s['function']['parameters'] ?? ['type' => 'object'];
            }
        }

        if ($schema === null) {
            $resultado = [
                'ok' => false,
                'error_code' => 'herramienta_desconocida',
                'error' => "La herramienta «{$nombre}» no existe o no está disponible en esta conversación.",
            ];
        } else {
            $decoded = json_decode($rawArgs === '' ? '{}' : $rawArgs, true);
            $jsonValido = json_last_error() === JSON_ERROR_NONE;
            $validacion = $this->validador->validar($schema, $decoded);
            $args = $validacion['args'];

            if (! $jsonValido) {
                $resultado = ['ok' => false, 'error_code' => 'argumentos_invalidos', 'error' => 'Los argumentos no son JSON válido.'];
            } elseif (! $validacion['ok']) {
                $resultado = ['ok' => false, 'error_code' => 'argumentos_invalidos', 'error' => implode(' ', $validacion['errores'])];
            } else {
                [$resultado, $propuesta] = $this->despachar($user, $nombre, $args, $archivos);
            }
        }

        $resultado = $this->sanitizer->limpiar($resultado);
        $content = json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{"ok":false,"error_code":"error_interno"}';

        $max = (int) config('gelia_ai.tool_result_max_chars', 12000);
        if (mb_strlen($content) > $max) {
            $resultado = [
                'ok' => false,
                'error_code' => 'resultado_demasiado_grande',
                'error' => 'El resultado excede el tamaño permitido. Repite con una consulta más específica o un limit menor.',
            ];
            $content = json_encode($resultado, JSON_UNESCAPED_UNICODE);
        }

        return ['nombre' => $nombre, 'args' => $args, 'resultado' => $resultado, 'content' => $content, 'propuesta' => $propuesta];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  list<array<string, mixed>>  $archivos
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function despachar(User $user, string $nombre, array $args, array $archivos): array
    {
        try {
            $accion = $this->acciones->accionDeTool($nombre);
            if ($accion) {
                if (! $user->can($accion->permiso())) {
                    return [['ok' => false, 'error_code' => 'sin_permiso', 'error' => "Sin permiso ({$accion->permiso()})."], null];
                }

                $prep = $accion->preparar($user, $args, $archivos);
                if (! $prep['ok']) {
                    return [$prep, null];
                }

                $propuesta = $this->propuestas->registrar($user, $accion->id(), $prep['payload'], $prep['resumen'], $prep['advertencias']);

                return [[
                    'ok' => true,
                    'propuesta_registrada' => true,
                    'propuesta_id' => $propuesta['id'],
                    'resumen' => $prep['resumen'],
                    'advertencias' => $prep['advertencias'],
                    'aviso' => 'Aún no se ejecutó nada. La persona usuaria debe confirmar en la interfaz.',
                ], $propuesta];
            }

            foreach ($this->herramientas() as $tool) {
                if ($tool->name() === $nombre) {
                    return [$tool->ejecutar($user, $args, $archivos), null];
                }
            }

            return [['ok' => false, 'error_code' => 'herramienta_desconocida', 'error' => "Herramienta desconocida: {$nombre}."], null];
        } catch (Throwable $e) {
            report($e);

            return [['ok' => false, 'error_code' => 'error_interno', 'error' => 'La herramienta falló en el servidor; no hay resultado. No inventes datos.'], null];
        }
    }

    /** @return list<HerramientaGeliaAi> */
    private function herramientas(): array
    {
        return [$this->ayuda, $this->inventario, $this->almacenes];
    }
}
