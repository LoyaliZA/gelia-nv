<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class UserSessionService
{
    public static function driverSoportaListado(): bool
    {
        return Config::get('session.driver') === 'database';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarParaUsuario(int $userId, string $currentSessionId): array
    {
        if (!self::driverSoportaListado()) {
            return [];
        }

        $table = Config::get('session.table', 'sessions');

        return DB::table($table)
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => $this->formatearSesion($session, $currentSessionId))
            ->values()
            ->all();
    }

    public function cerrarOtrasSesiones(int $userId, string $currentSessionId): int
    {
        if (!self::driverSoportaListado()) {
            return 0;
        }

        $table = Config::get('session.table', 'sessions');

        return DB::table($table)
            ->where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatearSesion(object $session, string $currentSessionId): array
    {
        $agent = self::parseUserAgent($session->user_agent ?? '');
        $ultimaActividad = Carbon::createFromTimestamp((int) $session->last_activity)
            ->timezone(config('app.timezone'));

        return [
            'id'                    => $session->id,
            'ip_address'            => $session->ip_address,
            'dispositivo'           => $agent['dispositivo'],
            'plataforma'            => $agent['plataforma'],
            'navegador'             => $agent['navegador'],
            'resumen'               => $agent['resumen'],
            'es_actual'             => $session->id === $currentSessionId,
            'last_activity'         => (int) $session->last_activity,
            'last_activity_humano'  => $ultimaActividad->locale('es')->diffForHumans(),
            'last_activity_fecha'   => $ultimaActividad->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array{dispositivo: string, plataforma: string, navegador: string, resumen: string}
     */
    public static function parseUserAgent(?string $userAgent): array
    {
        $ua = $userAgent ?? '';
        $dispositivo = 'Escritorio';
        $plataforma = 'Desconocido';
        $navegador = 'Navegador';

        if ($ua === '') {
            return [
                'dispositivo' => $dispositivo,
                'plataforma'  => $plataforma,
                'navegador'   => $navegador,
                'resumen'     => 'Sesión sin detalles',
            ];
        }

        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $dispositivo = preg_match('/iPad/i', $ua) ? 'Tablet' : 'Móvil';
            $plataforma = 'iOS';
        } elseif (preg_match('/Android/i', $ua)) {
            $dispositivo = preg_match('/Mobile/i', $ua) ? 'Móvil' : 'Tablet';
            $plataforma = 'Android';
        } elseif (preg_match('/Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
            $dispositivo = 'Móvil';
        }

        if (preg_match('/Windows NT/i', $ua)) {
            $plataforma = 'Windows';
        } elseif (preg_match('/Mac OS X|Macintosh/i', $ua)) {
            $plataforma = 'macOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $plataforma = 'Linux';
        } elseif (preg_match('/CrOS/i', $ua)) {
            $plataforma = 'Chrome OS';
        }

        if (preg_match('/Edg\//i', $ua)) {
            $navegador = 'Microsoft Edge';
        } elseif (preg_match('/Chrome\//i', $ua) && !preg_match('/Edg\//i', $ua)) {
            $navegador = 'Google Chrome';
        } elseif (preg_match('/Firefox\//i', $ua)) {
            $navegador = 'Mozilla Firefox';
        } elseif (preg_match('/Safari\//i', $ua) && !preg_match('/Chrome\//i', $ua)) {
            $navegador = 'Safari';
        } elseif (preg_match('/OPR\//i', $ua) || preg_match('/Opera/i', $ua)) {
            $navegador = 'Opera';
        }

        return [
            'dispositivo' => $dispositivo,
            'plataforma'  => $plataforma,
            'navegador'   => $navegador,
            'resumen'     => "{$navegador} · {$plataforma} · {$dispositivo}",
        ];
    }
}
