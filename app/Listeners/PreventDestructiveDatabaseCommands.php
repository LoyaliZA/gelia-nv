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
        if (! in_array($event->command, self::COMANDOS_DESTRUCTIVOS, true)) {
            return;
        }

        if (config('app.env') === 'production') {
            throw new RuntimeException(
                "Comando «{$event->command}» bloqueado en producción."
            );
        }

        $dbConnection = config('database.default');
        $dbName = (string) config("database.connections.{$dbConnection}.database");

        // Solo bases claramente de prueba pueden destruirse (nunca laravel/gelia_nv/etc.).
        if ($this->esBaseDePrueba($dbName)) {
            return;
        }

        // ALLOW_DESTRUCTIVE_DB ya no bypasea bases con datos reales.
        $users = Schema::hasTable('users') ? User::count() : 0;
        $clientes = Schema::hasTable('clientes') ? Cliente::count() : 0;

        if ($users <= 1 && $clientes === 0) {
            return;
        }

        throw new RuntimeException(
            "Comando «{$event->command}» bloqueado: BD «{$dbName}» tiene datos ({$users} usuarios, {$clientes} clientes). "
            . 'Usa sqlite :memory: o una BD cuyo nombre contenga «test». '
            . 'ALLOW_DESTRUCTIVE_DB ya no permite borrar bases con datos.'
        );
    }

    private function esBaseDePrueba(string $dbName): bool
    {
        $n = strtolower($dbName);

        return $n === ':memory:'
            || str_contains($n, 'test')
            || str_contains($n, 'phpunit')
            || str_ends_with($n, '_testing');
    }
}
