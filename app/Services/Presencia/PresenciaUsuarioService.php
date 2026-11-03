<?php

namespace App\Services\Presencia;

use App\Events\PresenciaActualizadaUsuario;
use App\Events\PresenciaContactoActualizada;
use App\Models\ConfiguracionUsuario;
use App\Models\ConversacionParticipante;
use App\Models\User;
use App\Support\PresenciaCatalogo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PresenciaUsuarioService
{
    public function __construct(
        private ResolverEstadoPresenciaService $resolver,
        private FormatearPresenciaService $formatear,
    ) {}

    public function catalogo(): array
    {
        return collect(PresenciaCatalogo::estados())
            ->map(fn ($meta, $slug) => array_merge(['slug' => $slug], $meta))
            ->values()
            ->all();
    }

    public function prefsPorDefecto(): array
    {
        return PresenciaCatalogo::defaults();
    }

    public function obtenerPrefs(User $user): array
    {
        $config = ConfiguracionUsuario::where('user_id', $user->id)->first();
        $guardado = $config?->presencia;

        if (! is_array($guardado)) {
            return $this->prefsPorDefecto();
        }

        return array_replace_recursive($this->prefsPorDefecto(), $guardado);
    }

    public function formatear(User $user): array
    {
        return $this->formatear->ejecutar($this->obtenerPrefs($user));
    }

    public function formatearPorUserId(?int $userId): ?array
    {
        if (! $userId) {
            return null;
        }

        $user = User::find($userId);

        return $user ? $this->formatear($user) : null;
    }

    /** @return array<int, array> */
    public function formatearVarios(Collection|array $userIds): array
    {
        $ids = collect($userIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $configs = ConfiguracionUsuario::whereIn('user_id', $ids)->get()->keyBy('user_id');
        $resultado = [];

        foreach ($ids as $id) {
            $guardado = $configs->get($id)?->presencia;
            $prefs = is_array($guardado)
                ? array_replace_recursive($this->prefsPorDefecto(), $guardado)
                : $this->prefsPorDefecto();
            $resultado[$id] = array_merge(
                $this->formatear->ejecutar($prefs),
                ['user_id' => (int) $id]
            );
        }

        return $resultado;
    }

    public function registrarActividad(User $user): array
    {
        $prefs = $this->obtenerPrefs($user);
        $notificada = (bool) ($prefs['en_linea_notificada'] ?? false);
        $estadoAntes = $this->resolver->estadoEfectivo($prefs);

        $prefs['ultima_actividad_at'] = now()->toIso8601String();

        $estadoDespues = $this->resolver->estadoEfectivo($prefs);
        $enLinea = PresenciaCatalogo::enLinea($prefs);
        $prefs['en_linea_notificada'] = $enLinea;

        return $this->persistir(
            $user,
            $prefs,
            $estadoAntes !== $estadoDespues || $notificada !== $enLinea
        );
    }

    public function registrarSenal(User $user, string $tabId, bool $visible): array
    {
        $tabId = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $tabId) ?? '', 0, 64);
        if ($tabId === '') {
            return $this->registrarActividad($user);
        }

        return DB::transaction(function () use ($user, $tabId, $visible) {
            $config = ConfiguracionUsuario::where('user_id', $user->id)->lockForUpdate()->first();
            $prefs = $this->mezclarPrefs(is_array($config?->presencia) ? $config->presencia : []);
            $notificada = (bool) ($prefs['en_linea_notificada'] ?? false);
            $estadoAntes = $this->resolver->estadoEfectivo($prefs);

            $pestanas = $this->pestanasVivas(is_array($prefs['pestanas'] ?? null) ? $prefs['pestanas'] : []);
            if ($visible) {
                $pestanas[$tabId] = now()->addSeconds(PresenciaCatalogo::EN_LINEA_SEGUNDOS)->toIso8601String();
                $prefs['ultima_actividad_at'] = now()->toIso8601String();
            } else {
                unset($pestanas[$tabId]);
                if ($pestanas === []) {
                    $prefs['ultima_actividad_at'] = now()->toIso8601String();
                }
            }
            $prefs['pestanas'] = $pestanas;

            $estadoDespues = $this->resolver->estadoEfectivo($prefs);
            $enLinea = PresenciaCatalogo::enLinea($prefs);
            $prefs['en_linea_notificada'] = $enLinea;

            return $this->persistir(
                $user,
                $prefs,
                $estadoAntes !== $estadoDespues || $notificada !== $enLinea
            );
        });
    }

    public function debeMarcarFueraDeLinea(array $prefs): bool
    {
        return (bool) ($prefs['en_linea_notificada'] ?? false)
            && ! PresenciaCatalogo::enLinea($prefs);
    }

    public function marcarFueraDeLineaSiAplica(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $config = ConfiguracionUsuario::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $config || ! is_array($config->presencia)) {
                return false;
            }

            $prefs = $this->mezclarPrefs($config->presencia);
            $prefs['pestanas'] = $this->pestanasVivas(is_array($prefs['pestanas'] ?? null) ? $prefs['pestanas'] : []);

            if (! $this->debeMarcarFueraDeLinea($prefs)) {
                return false;
            }

            $prefs['en_linea_notificada'] = false;
            $this->persistir($user, $prefs, true);

            return true;
        });
    }

    public function actualizar(User $user, array $datos): array
    {
        $prefs = $this->obtenerPrefs($user);
        if (isset($datos['estado']) && PresenciaCatalogo::esSlugValido($datos['estado'])) {
            $prefs['estado'] = $datos['estado'];
        }

        if (array_key_exists('mensaje', $datos)) {
            $prefs['mensaje'] = $datos['mensaje'] ? mb_substr((string) $datos['mensaje'], 0, 120) : null;
        }

        if (isset($datos['modo']) && in_array($datos['modo'], ['manual', 'automatico'], true)) {
            $prefs['modo'] = $datos['modo'];
        }

        if (array_key_exists('automatizar', $datos)) {
            $prefs['automatizar'] = (bool) $datos['automatizar'];
        }

        if (array_key_exists('duracion_minutos', $datos)) {
            $mins = (int) $datos['duracion_minutos'];
            $prefs['expira_at'] = $mins > 0 ? now()->addMinutes($mins)->toIso8601String() : null;
            if ($mins > 0) {
                $prefs['modo'] = 'manual';
            }
        }

        if (isset($datos['horarios']) && is_array($datos['horarios'])) {
            $prefs['horarios'] = $this->normalizarHorarios($datos['horarios']);
        }

        if (isset($datos['inactividad_minutos'])) {
            $prefs['inactividad_minutos'] = max(0, min(480, (int) $datos['inactividad_minutos']));
        }

        if (isset($datos['inactividad_estado']) && PresenciaCatalogo::esSlugValido($datos['inactividad_estado'])) {
            $prefs['inactividad_estado'] = $datos['inactividad_estado'];
        }

        if (($datos['modo'] ?? $prefs['modo']) === 'automatico') {
            $prefs['expira_at'] = null;
        }

        $prefs['ultima_actividad_at'] = now()->toIso8601String();

        return $this->persistir($user, $prefs, true);
    }

    public function sincronizarAutomatico(User $user): array
    {
        $prefs = $this->obtenerPrefs($user);
        if (($prefs['modo'] ?? 'automatico') === 'manual' && ! empty($prefs['expira_at'])) {
            if (now()->lt($prefs['expira_at'])) {
                return $this->formatear->ejecutar($prefs);
            }
            $prefs['modo'] = 'automatico';
            $prefs['expira_at'] = null;
        }

        $estadoAntes = $this->resolver->estadoEfectivo($prefs);
        $estadoDespues = $this->resolver->estadoEfectivo($prefs);

        if ($estadoAntes === $estadoDespues) {
            return $this->formatear->ejecutar($prefs);
        }

        return $this->persistir($user, $prefs, true);
    }

    private function mezclarPrefs(array $guardado): array
    {
        return array_replace_recursive($this->prefsPorDefecto(), $guardado);
    }

    private function pestanasVivas(array $pestanas): array
    {
        $vivas = [];

        foreach ($pestanas as $id => $expira) {
            if (! is_string($id) || ! is_string($expira)) {
                continue;
            }
            try {
                if (Carbon::parse($expira)->isFuture()) {
                    $vivas[$id] = $expira;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (count($vivas) > 8) {
            $vivas = array_slice($vivas, -8, null, true);
        }

        return $vivas;
    }

    private function persistir(User $user, array $prefs, bool $emitirBroadcast): array
    {
        ConfiguracionUsuario::updateOrCreate(
            ['user_id' => $user->id],
            ['presencia' => $prefs]
        );

        $formateado = array_merge($this->formatear->ejecutar($prefs), ['user_id' => $user->id]);

        if ($emitirBroadcast) {
            broadcast(new PresenciaActualizadaUsuario($user->id, $formateado));
            $this->notificarContactos($user->id, $formateado);
        }

        return $formateado;
    }

    private function notificarContactos(int $userId, array $formateado): void
    {
        $conversacionIds = ConversacionParticipante::where('user_id', $userId)->pluck('conversacion_id');

        if ($conversacionIds->isEmpty()) {
            return;
        }

        $contactoIds = ConversacionParticipante::query()
            ->whereIn('conversacion_id', $conversacionIds)
            ->where('user_id', '!=', $userId)
            ->distinct()
            ->pluck('user_id');

        foreach ($contactoIds as $contactoId) {
            broadcast(new PresenciaContactoActualizada((int) $contactoId, $formateado));
        }
    }

    private function normalizarHorarios(array $horarios): array
    {
        $validos = [];

        foreach ($horarios as $regla) {
            if (! is_array($regla)) {
                continue;
            }
            $estado = $regla['estado'] ?? null;
            if (! $estado || ! PresenciaCatalogo::esSlugValido($estado)) {
                continue;
            }
            $dias = array_values(array_unique(array_map('intval', $regla['dias'] ?? [])));
            $dias = array_filter($dias, fn ($d) => $d >= 1 && $d <= 7);
            $inicio = $this->normalizarHora($regla['inicio'] ?? '');
            $fin = $this->normalizarHora($regla['fin'] ?? '');
            if (! $inicio || ! $fin) {
                continue;
            }
            $validos[] = [
                'estado' => $estado,
                'dias' => $dias ?: [1, 2, 3, 4, 5],
                'inicio' => $inicio,
                'fin' => $fin,
            ];
        }

        return $validos;
    }

    private function normalizarHora(string $hora): ?string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', trim($hora), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h < 0 || $h > 23 || $min < 0 || $min > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $min);
    }
}
