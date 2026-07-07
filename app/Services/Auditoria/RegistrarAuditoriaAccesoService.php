<?php

namespace App\Services\Auditoria;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use App\Services\Geo\ResolverUbicacionIpService;
use App\Services\UserSessionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RegistrarAuditoriaAccesoService
{
    public function __construct(
        private readonly ResolverUbicacionIpService $ubicacionIp
    ) {}

    public function registrarLogin(User $user, Request $request, string $sessionId): AuditoriaAcceso
    {
        $ip = $request->ip();
        $agent = UserSessionService::parseUserAgent($request->userAgent());
        $ubicacion = $this->ubicacionIp->resolver($ip);
        $ahora = now();

        return AuditoriaAcceso::create([
            'user_id'             => $user->id,
            'session_id'          => $sessionId,
            'ip_address'          => $ip,
            'user_agent'          => $request->userAgent(),
            'dispositivo'         => $agent['dispositivo'],
            'plataforma'          => $agent['plataforma'],
            'navegador'           => $agent['navegador'],
            'ubicacion_ciudad'    => $ubicacion['ciudad'],
            'ubicacion_region'    => $ubicacion['region'],
            'ubicacion_pais'      => $ubicacion['pais'],
            'inicio_sesion_at'    => $ahora,
            'ultima_actividad_at' => $ahora,
        ]);
    }

    public function registrarCierre(string $sessionId, string $motivo): void
    {
        $acceso = AuditoriaAcceso::where('session_id', $sessionId)
            ->whereNull('cierre_sesion_at')
            ->latest('inicio_sesion_at')
            ->first();

        if (!$acceso) {
            return;
        }

        $this->cerrarAcceso($acceso, $motivo);
    }

    public function registrarCierrePorIds(array $sessionIds, string $motivo): int
    {
        $cerradas = 0;

        foreach ($sessionIds as $sessionId) {
            $acceso = AuditoriaAcceso::where('session_id', $sessionId)
                ->whereNull('cierre_sesion_at')
                ->latest('inicio_sesion_at')
                ->first();

            if ($acceso) {
                $this->cerrarAcceso($acceso, $motivo);
                $cerradas++;
            }
        }

        return $cerradas;
    }

    public function actualizarActividad(string $sessionId): void
    {
        $cacheKey = "sesion_actividad:{$sessionId}";

        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, 60);

        AuditoriaAcceso::where('session_id', $sessionId)
            ->whereNull('cierre_sesion_at')
            ->update(['ultima_actividad_at' => now()]);
    }

    public function sincronizarExpiradas(): int
    {
        $sessionIdsActivos = DB::table(config('session.table', 'sessions'))
            ->pluck('id')
            ->all();

        $query = AuditoriaAcceso::whereNull('cierre_sesion_at');

        if ($sessionIdsActivos !== []) {
            $query->whereNotIn('session_id', $sessionIdsActivos);
        }

        $accesos = $query->get();
        $cerradas = 0;

        foreach ($accesos as $acceso) {
            $this->cerrarAcceso($acceso, 'expiracion');
            $cerradas++;
        }

        return $cerradas;
    }

    private function cerrarAcceso(AuditoriaAcceso $acceso, string $motivo): void
    {
        $cierre = now();
        $inicio = Carbon::parse($acceso->inicio_sesion_at);
        $ultima = Carbon::parse($acceso->ultima_actividad_at);

        $duracionTotal = max(0, $inicio->diffInSeconds($cierre));
        $duracionInactiva = max(0, $ultima->diffInSeconds($cierre));
        $duracionActiva = max(0, $duracionTotal - $duracionInactiva);

        $acceso->update([
            'cierre_sesion_at'           => $cierre,
            'motivo_cierre'              => $motivo,
            'duracion_activa_segundos'   => $duracionActiva,
            'duracion_inactiva_segundos' => $duracionInactiva,
        ]);
    }
}
