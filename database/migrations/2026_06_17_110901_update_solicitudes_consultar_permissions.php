<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permisoAntiguo = Permission::where('name', 'solicitudes.consultar')->first();
        $permisoReportar = Permission::where('name', 'solicitudes.reportar')->first();

        $permisoEmitir = Permission::findOrCreate('solicitudes.emitir_consulta');
        $permisoResponder = Permission::findOrCreate('solicitudes.responder_consulta');

        if ($permisoAntiguo) {
            $rolesConsultar = $permisoAntiguo->roles;
            foreach ($rolesConsultar as $rol) {
                $rol->givePermissionTo($permisoEmitir);
            }
            $permisoAntiguo->delete();
        }

        if ($permisoReportar) {
            $rolesReportar = $permisoReportar->roles;
            foreach ($rolesReportar as $rol) {
                $rol->givePermissionTo($permisoResponder);
            }
        }

        $roleSuperAdmin = Role::where('name', 'Super Admin')->first();
        if ($roleSuperAdmin) {
            $roleSuperAdmin->givePermissionTo([$permisoEmitir, $permisoResponder]);
        }
    }

    public function down(): void
    {
        $permisoAntiguo = Permission::findOrCreate('solicitudes.consultar');
        $permisoEmitir = Permission::where('name', 'solicitudes.emitir_consulta')->first();
        $permisoResponder = Permission::where('name', 'solicitudes.responder_consulta')->first();

        if ($permisoEmitir) {
            $rolesEmitir = $permisoEmitir->roles;
            foreach ($rolesEmitir as $rol) {
                $rol->givePermissionTo($permisoAntiguo);
            }
            $permisoEmitir->delete();
        }

        if ($permisoResponder) {
            $permisoResponder->delete();
        }
    }
};
