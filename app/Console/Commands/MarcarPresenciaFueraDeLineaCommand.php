<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionUsuario;
use App\Models\User;
use App\Services\Presencia\PresenciaUsuarioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MarcarPresenciaFueraDeLineaCommand extends Command
{
    protected $signature = 'presencia:marcar-fuera-de-linea';

    protected $description = 'Avisa a los contactos cuando una sesión dejó de latir y ya no está en línea';

    public function handle(PresenciaUsuarioService $presencia): int
    {
        if (! Schema::hasTable('configuraciones_usuarios')) {
            return self::SUCCESS;
        }

        $marcadas = 0;

        ConfiguracionUsuario::query()
            ->whereNotNull('presencia')
            ->orderBy('id')
            ->chunkById(200, function ($configs) use ($presencia, &$marcadas) {
                foreach ($configs as $config) {
                    $prefs = is_array($config->presencia) ? $config->presencia : [];
                    if (! $presencia->debeMarcarFueraDeLinea($prefs)) {
                        continue;
                    }

                    $user = User::find($config->user_id);
                    if (! $user) {
                        continue;
                    }

                    if ($presencia->marcarFueraDeLineaSiAplica($user)) {
                        $marcadas++;
                    }
                }
            });

        $this->info("Presencia fuera de línea: {$marcadas}");

        return self::SUCCESS;
    }
}
