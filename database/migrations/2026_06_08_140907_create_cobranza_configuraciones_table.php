<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranza_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('llave')->unique();
            $table->json('valor')->nullable();
            $table->timestamps();
        });

        // Configuración por defecto: 10:00 y 12:00
        DB::table('cobranza_configuraciones')->insert([
            'llave' => 'horarios_alertas',
            'valor' => json_encode(['10:00', '12:00']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear el permiso
        $permission = Permission::firstOrCreate(['name' => 'cobranza.configurar_alertas', 'guard_name' => 'web']);
        
        // Asignarlo a Super Admin si existe
        $role = Role::where('name', 'Super Admin')->first();
        if ($role) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranza_configuraciones');
        $permission = Permission::where('name', 'cobranza.configurar_alertas')->first();
        if ($permission) {
            $permission->delete();
        }
    }
};
