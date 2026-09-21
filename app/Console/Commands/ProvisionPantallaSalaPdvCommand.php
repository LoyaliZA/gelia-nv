<?php

namespace App\Console\Commands;

use App\Models\PuntoVenta\PdvPantallaSalaToken;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Pantallas\GestionarEnlacePantallaSalaPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Console\Command;

class ProvisionPantallaSalaPdvCommand extends Command
{
    protected $signature = 'pdv:provision-pantalla-sala {--sucursal= : ID de sucursal específica}';

    protected $description = 'Provisiona enlaces permanentes de pantalla de sala por sucursal';

    public function handle(
        GestionarEnlacePantallaSalaPdvService $servicio,
        AlcancePdv $alcance,
    ): int {
        $actor = User::role('Super Admin')->first();
        if ($actor === null) {
            $this->error('No hay usuario Super Admin para provisionar enlaces.');

            return self::FAILURE;
        }

        if (! $actor->hasPermissionTo(PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR)) {
            $actor->givePermissionTo(PuntoVentaModulo::PERMISO_PANTALLA_SALA_ABRIR);
        }

        $sucursalId = $this->option('sucursal');
        $query = Sucursal::query()->where('activo', true);

        if ($sucursalId !== null) {
            $query->whereKey((int) $sucursalId);
        }

        $sucursales = $query->orderBy('id')->get();
        if ($sucursales->isEmpty()) {
            $this->warn('No hay sucursales activas para provisionar.');

            return self::SUCCESS;
        }

        foreach ($sucursales as $sucursal) {
            $actor->concederAccesoSucursal($sucursal, esPrincipal: true);
            $alcance->establecerSucursalActiva($actor, (int) $sucursal->id);

            $resultado = $servicio->obtenerEnlace($actor, (int) $sucursal->id, now());
            $estado = PdvPantallaSalaToken::query()
                ->where('sucursal_id', $sucursal->id)
                ->first();

            $this->line(sprintf(
                'Sucursal #%d (%s): %s',
                $sucursal->id,
                $sucursal->nombre,
                (string) ($resultado['url'] ?? 'sin URL'),
            ));
        }

        return self::SUCCESS;
    }
}
