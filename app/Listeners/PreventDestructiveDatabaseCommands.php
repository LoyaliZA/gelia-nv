<?php

namespace App\Listeners;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PreventDestructiveDatabaseCommands
{
    private const COMANDOS_DESTRUCTIVOS = [
        'migrate:fresh',
        'migrate:refresh',
        'db:wipe',
    ];

    public function handle(CommandStarting $event): void
    {
        if (!in_array($event->command, self::COMANDOS_DESTRUCTIVOS, true)) {
            return;
        }

        if (config('app.env') === 'production') {
            throw new RuntimeException(
                "Comando «{$event->command}» bloqueado en producción."
            );
        }

        if (filter_var(env('ALLOW_DESTRUCTIVE_DB', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $users = Schema::hasTable('users') ? User::count() : 0;
        $clientes = Schema::hasTable('clientes') ? Cliente::count() : 0;

        if ($users <= 1 && $clientes === 0) {
            return;
        }

        throw new RuntimeException(
            "Comando «{$event->command}» bloqueado: hay datos en la BD ({$users} usuarios, {$clientes} clientes). "
            . 'Ejecuta «php artisan db:backup» primero. '
            . 'Para forzar: ALLOW_DESTRUCTIVE_DB=1 php artisan ' . $event->command
        );
    }
}
