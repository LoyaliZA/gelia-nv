<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Propuestas validadas en servidor y pendientes de confirmación.
 * Confirmar ejecuta exactamente el payload guardado, no lo que reenvíe el cliente.
 *
 * Consumo único: reclamar() lee y marca la propuesta como «procesando» dentro de Cache::lock(),
 * que con los stores database/redis/file es un bloqueo compartido entre procesos. Solo quien la
 * reclama la ejecuta. Si la ejecución falla, expira o el proceso muere, la propuesta queda en
 * «procesando» o «fallida» y nunca vuelve a «pendiente»: no hay segunda ejecución automática,
 * hay que preparar una nueva. Garantía: como máximo una ejecución por propuesta, no exactamente una.
 */
class PropuestasAccionGeliaAi
{
    public const PENDIENTE = 'pendiente';

    public const PROCESANDO = 'procesando';

    public const EJECUTADA = 'ejecutada';

    public const FALLIDA = 'fallida';

    public const CANCELADA = 'cancelada';

    public const VENCIDA = 'vencida';

    /** No existe, ya se depuró o pertenece a otra persona (no se distingue a propósito). */
    public const INEXISTENTE = 'inexistente';

    /** Otro proceso tiene el bloqueo o no se pudo guardar el cambio de estado: no se ejecutó nada. */
    public const OCUPADA = 'ocupada';

    /** Tiempo que se conserva el estado tras vencer o consumirse, para explicar qué pasó. */
    private const RETENCION_MINUTOS = 1440;

    private const LOCK_SEGUNDOS = 15;

    private const ESPERA_LOCK_SEGUNDOS = 5;

    public function __construct(private AccionRegistry $acciones) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $advertencias
     * @param  array<string, mixed>  $detalle  datos para la tarjeta de confirmación
     * @return array<string, mixed>
     */
    public function registrar(User $user, string $accion, array $payload, string $resumen, array $advertencias = [], array $detalle = []): array
    {
        $propuesta = [
            'id' => (string) Str::uuid(),
            'user_id' => (int) $user->id,
            'accion' => $accion,
            'payload' => $payload,
            'resumen' => $resumen,
            'advertencias' => $advertencias,
            'detalle' => $detalle,
            'estado' => self::PENDIENTE,
            'creada_en' => now()->toIso8601String(),
            'expira_en' => now()->addMinutes($this->ttlMinutos())->toIso8601String(),
        ];

        $this->guardar($user, $propuesta);

        return $propuesta;
    }

    /**
     * Reserva la propuesta para ejecutarla. Solo devuelve la propuesta a una de varias solicitudes simultáneas.
     *
     * @return array{estado: string, propuesta?: array<string, mixed>}
     */
    public function reclamar(User $user, string $id): array
    {
        return $this->conBloqueo($id, function () use ($user, $id) {
            $propuesta = $this->leer($user, $id);
            if (! $propuesta) {
                return ['estado' => self::INEXISTENTE];
            }

            $estado = $this->estadoDe($propuesta);
            if ($estado !== self::PENDIENTE) {
                return ['estado' => $estado];
            }

            $propuesta['estado'] = self::PROCESANDO;
            $propuesta['procesando_desde'] = now()->toIso8601String();
            if (! $this->guardar($user, $propuesta)) {
                return ['estado' => self::OCUPADA];
            }

            return ['estado' => self::PROCESANDO, 'propuesta' => $propuesta];
        });
    }

    /** Registra el resultado de una propuesta reclamada. No la vuelve a dejar pendiente. */
    public function finalizar(User $user, string $id, bool $exito): void
    {
        $this->conBloqueo($id, function () use ($user, $id, $exito) {
            $propuesta = $this->leer($user, $id);
            if ($propuesta && ($propuesta['estado'] ?? null) === self::PROCESANDO) {
                $propuesta['estado'] = $exito ? self::EJECUTADA : self::FALLIDA;
                $propuesta['finalizada_en'] = now()->toIso8601String();
                $this->guardar($user, $propuesta);
            }

            return ['estado' => $propuesta['estado'] ?? self::INEXISTENTE];
        });
    }

    /**
     * Invalida una propuesta pendiente en el servidor.
     *
     * @return array{estado: string, propuesta?: array<string, mixed>}
     */
    public function cancelar(User $user, string $id): array
    {
        return $this->conBloqueo($id, function () use ($user, $id) {
            $propuesta = $this->leer($user, $id);
            if (! $propuesta) {
                return ['estado' => self::INEXISTENTE];
            }

            $estado = $this->estadoDe($propuesta);
            if ($estado !== self::PENDIENTE) {
                return ['estado' => $estado];
            }

            $propuesta['estado'] = self::CANCELADA;
            $propuesta['finalizada_en'] = now()->toIso8601String();
            if (! $this->guardar($user, $propuesta)) {
                return ['estado' => self::OCUPADA];
            }

            return ['estado' => self::CANCELADA, 'propuesta' => $propuesta];
        });
    }

    /** @return array<string, mixed>|null propuesta propia aún confirmable */
    public function pendiente(User $user, string $id): ?array
    {
        $propuesta = $this->leer($user, $id);

        return $propuesta && $this->estadoDe($propuesta) === self::PENDIENTE ? $propuesta : null;
    }

    /**
     * Datos de la propuesta que puede ver el navegador (sin payload).
     *
     * @param  array<string, mixed>  $propuesta
     * @return array<string, mixed>
     */
    public function publica(User $user, array $propuesta): array
    {
        $permiso = $this->acciones->obtener($propuesta['accion'])->permiso();

        return [
            'id' => $propuesta['id'],
            'accion' => $propuesta['accion'],
            'resumen_corto' => $propuesta['resumen'],
            'advertencias' => $propuesta['advertencias'] ?? [],
            'detalle' => $propuesta['detalle'] ?? [],
            'expira_en' => $propuesta['expira_en'] ?? null,
            'permiso' => $permiso,
            'puede' => $user->can($permiso),
        ];
    }

    public function mensajeRechazo(string $estado): string
    {
        return match ($estado) {
            self::VENCIDA => 'La propuesta venció sin ejecutarse (cada propuesta es válida '.$this->ttlMinutos().' minutos). Pide a GELIA que la prepare de nuevo.',
            self::PROCESANDO, self::OCUPADA => 'Esta propuesta ya se está procesando. Espera el resultado; no se ejecutará dos veces.',
            self::EJECUTADA => 'Esta propuesta ya se ejecutó. Revisa el resultado en la conversación o en el módulo correspondiente.',
            self::FALLIDA => 'Esta propuesta ya se intentó y no se completó. Por seguridad no se reintenta; pide a GELIA que la prepare de nuevo.',
            self::CANCELADA => 'Esta propuesta se canceló y no se ejecutó nada. Pide a GELIA que la prepare de nuevo si la necesitas.',
            default => 'No encontramos esta propuesta en tu cuenta. Pide a GELIA que la prepare de nuevo.',
        };
    }

    public function codigoHttp(string $estado): int
    {
        return match ($estado) {
            self::INEXISTENTE => 404,
            self::VENCIDA => 410,
            default => 409,
        };
    }

    /** @param  array<string, mixed>  $propuesta */
    private function estadoDe(array $propuesta): string
    {
        $estado = (string) ($propuesta['estado'] ?? self::PENDIENTE);
        if ($estado !== self::PENDIENTE) {
            return $estado;
        }

        $expira = isset($propuesta['expira_en'])
            ? Carbon::parse($propuesta['expira_en'])
            : Carbon::parse($propuesta['creada_en'] ?? now())->addMinutes($this->ttlMinutos());

        return now()->greaterThanOrEqualTo($expira) ? self::VENCIDA : self::PENDIENTE;
    }

    /** @return array<string, mixed>|null */
    private function leer(User $user, string $id): ?array
    {
        $propuesta = Cache::get($this->key($user, $id));

        return is_array($propuesta) && (int) ($propuesta['user_id'] ?? 0) === (int) $user->id ? $propuesta : null;
    }

    /** @param  array<string, mixed>  $propuesta */
    private function guardar(User $user, array $propuesta): bool
    {
        $base = Carbon::parse($propuesta['expira_en'] ?? now());
        $hasta = $base->copy()->addMinutes(self::RETENCION_MINUTOS);

        return Cache::put($this->key($user, (string) $propuesta['id']), $propuesta, $hasta);
    }

    /**
     * @param  callable(): array{estado: string, propuesta?: array<string, mixed>}  $fn
     * @return array{estado: string, propuesta?: array<string, mixed>}
     */
    private function conBloqueo(string $id, callable $fn): array
    {
        $lock = Cache::lock("gelia_ai_propuesta_lock:{$id}", self::LOCK_SEGUNDOS);

        try {
            $lock->block(self::ESPERA_LOCK_SEGUNDOS);
        } catch (LockTimeoutException) {
            return ['estado' => self::OCUPADA];
        }

        try {
            return $fn();
        } finally {
            $lock->release();
        }
    }

    private function ttlMinutos(): int
    {
        return max(1, (int) config('gelia_ai.propuesta_ttl_minutes', 30));
    }

    private function key(User $user, string $id): string
    {
        return "gelia_ai_propuesta:{$user->id}:{$id}";
    }
}
