<?php

namespace App\Services\Soporte;

use App\Models\SoporteConfiguracion;
use Carbon\Carbon;

class SLAService
{
    public function calculateDueDate(int $hours, ?Carbon $startDate = null): Carbon
    {
        $config = SoporteConfiguracion::first();
        
        if (!$config) {
            // Fallback if no config exists (absolute hours)
            return ($startDate ?? now())->addHours($hours);
        }

        $start = $startDate ?? now();
        $due = $start->copy();
        $remainingHours = $hours;

        $workStart = Carbon::parse($config->horario_inicio);
        $workEnd = Carbon::parse($config->horario_fin);
        $workHoursPerDay = $workStart->diffInHours($workEnd);

        if ($workHoursPerDay <= 0) {
            // Invalid config, fallback to absolute
            return $due->addHours($hours);
        }

        while ($remainingHours > 0) {
            // Skip weekends
            if ($due->isWeekend()) {
                $due->addDay()->setTime($workStart->hour, $workStart->minute, $workStart->second);
                continue;
            }

            // If currently before work hours, jump to work start
            if ($due->format('H:i:s') < $config->horario_inicio) {
                $due->setTime($workStart->hour, $workStart->minute, $workStart->second);
            }

            // If currently after or at work end, jump to next day work start
            if ($due->format('H:i:s') >= $config->horario_fin) {
                $due->addDay()->setTime($workStart->hour, $workStart->minute, $workStart->second);
                continue;
            }

            // Calculate remaining time today
            $endOfToday = $due->copy()->setTime($workEnd->hour, $workEnd->minute, $workEnd->second);
            $hoursLeftToday = $due->diffInMinutes($endOfToday) / 60;

            if ($remainingHours <= $hoursLeftToday) {
                // We can finish within today
                $due->addMinutes($remainingHours * 60);
                $remainingHours = 0;
            } else {
                // Exhaust today's hours, jump to next day work start
                $remainingHours -= $hoursLeftToday;
                $due->addDay()->setTime($workStart->hour, $workStart->minute, $workStart->second);
            }
        }

        return $due;
    }

    public function isWithinBusinessHours(?Carbon $time = null): bool
    {
        $config = SoporteConfiguracion::first();
        if (!$config) return true;

        $checkTime = $time ?? now();
        if ($checkTime->isWeekend()) return false;

        $timeStr = $checkTime->format('H:i:s');
        return $timeStr >= $config->horario_inicio && $timeStr <= $config->horario_fin;
    }
}
