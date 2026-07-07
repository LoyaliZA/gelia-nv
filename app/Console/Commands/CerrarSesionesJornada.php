<?php

namespace App\Console\Commands;

use App\Services\Auditoria\RegistrarAuditoriaAccesoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CerrarSesionesJornada extends Command
{
    protected $signature = 'sesiones:cerrar-jornada';

    protected $description = 'Cierra todas las sesiones activas al terminar la jornada laboral configurada';

    public function handle(RegistrarAuditoriaAccesoService $auditoriaAcceso): int
    {
        if (!filter_var(config('sesiones.jornada_cierre_activo', true), FILTER_VALIDATE_BOOLEAN)) {
            return self::SUCCESS;
        }

        $zona = config('sesiones.jornada_zona_horaria', config('app.timezone', 'UTC'));
        $horaFin = config('sesiones.jornada_hora_fin', '18:00');

        try {
            $ahora = Carbon::now($zona);
            [$hora, $minuto] = array_pad(explode(':', $horaFin), 2, '0');
            $finJornada = $ahora->copy()->setTime((int) $hora, (int) $minuto, 0);
        } catch (\Throwable) {
            $this->error('Configuración de jornada inválida.');

            return self::FAILURE;
        }

        if ($ahora->lt($finJornada)) {
            return self::SUCCESS;
        }

        $cacheKey = 'sesiones_jornada_cerrada:' . $ahora->toDateString();

        if (Cache::has($cacheKey)) {
            return self::SUCCESS;
        }

        $table = config('session.table', 'sessions');
        $sessionIds = DB::table($table)->pluck('id')->all();

        if ($sessionIds === []) {
            Cache::put($cacheKey, true, now()->endOfDay());

            return self::SUCCESS;
        }

        $auditoriaAcceso->registrarCierrePorIds($sessionIds, 'jornada');
        $eliminadas = DB::table($table)->delete();

        Cache::put($cacheKey, true, now()->endOfDay());

        $this->info("Jornada cerrada: {$eliminadas} sesión(es) finalizada(s).");

        return self::SUCCESS;
    }
}
