<?php

use App\Models\User;
use App\Services\Permisos\PermisoCatalogoMigracion;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar([
            PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA,
            PuntoVentaModulo::PERMISO_TURNOS_INICIAR_ATENCION,
            PuntoVentaModulo::PERMISO_OPERACION_PLAZOS_TURNOS,
        ]);

        $this->copiar('pdv.resguardos.recibir_gerente', [
            PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA,
        ]);
        $this->copiar(PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION, [
            PuntoVentaModulo::PERMISO_TURNOS_INICIAR_ATENCION,
        ]);
        $this->copiar(PuntoVentaModulo::PERMISO_OPERACION_EQUIPO_GESTIONAR, [
            PuntoVentaModulo::PERMISO_OPERACION_PLAZOS_TURNOS,
        ]);

        Permission::query()->whereIn('name', [
            'pdv.resguardos.recibir',
            'pdv.resguardos.recibir_gerente',
        ])->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        PermisoCatalogoMigracion::registrar([
            'pdv.resguardos.recibir',
            'pdv.resguardos.recibir_gerente',
        ]);

        foreach ([
            PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA,
        ] as $origen) {
            $this->copiar($origen, ['pdv.resguardos.recibir_gerente']);
        }

        Permission::query()->whereIn('name', [
            PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_LLEGADA,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENVIAR_A_CUSTODIA,
            PuntoVentaModulo::PERMISO_TURNOS_INICIAR_ATENCION,
            PuntoVentaModulo::PERMISO_OPERACION_PLAZOS_TURNOS,
        ])->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $hijos
     */
    private function copiar(string $padre, array $hijos): void
    {
        if (! Permission::query()->where('name', $padre)->where('guard_name', 'web')->exists()) {
            return;
        }

        Role::query()
            ->where('guard_name', 'web')
            ->whereHas('permissions', fn ($query) => $query->where('name', $padre))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($hijos));

        User::withoutGlobalScopes()
            ->whereHas('permissions', fn ($query) => $query->where('name', $padre))
            ->get()
            ->each(fn (User $user) => $user->givePermissionTo($hijos));
    }
};
