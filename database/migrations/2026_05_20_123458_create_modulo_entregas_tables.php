<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Arreglo de permisos atómicos requeridos para la operación del módulo.
     */
    private array $permisos = [
        'entregas.cotizar',
        'entregas.configurar_zonas',
    ];

    /**
     * Ejecuta las migraciones y siembra configuraciones iniciales.
     */
    public function up(): void
    {
        // 1. Catálogo de Zonas de Entrega
        // Define los polígonos GeoJSON y el costo base de cada área.
        Schema::create('catalogo_zonas_entrega', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); 
            $table->json('coordenadas_poligono');
            $table->string('color_hex', 7)->default('#000000');
            $table->decimal('costo_base', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Catálogo Relacional de Horarios
        // Establece ventanas de tiempo dependientes de cada zona.
        Schema::create('catalogo_horarios_entrega', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_id')->constrained('catalogo_zonas_entrega')->cascadeOnDelete();
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 3. Configuración Global del Motor Híbrido
        // Almacena el Punto Central, tolerancias y banderas de control logístico.
        Schema::create('configuracion_entregas', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitud_origen', 10, 8); 
            $table->decimal('longitud_origen', 11, 8);
            $table->decimal('radio_tolerancia_km', 8, 2)->default(12.00);
            $table->decimal('tarifa_envio_extra', 10, 2)->default(60.00);
            $table->boolean('cobro_extra_por_km')->default(false); // Flag para alternar lógica comercial
            $table->boolean('usar_api_distancia')->default(false);
            $table->timestamps();
        });

        // 4. Sembrado inicial del Punto Central (Toyota Ruiz Cortines)
        DB::table('configuracion_entregas')->insert([
            'latitud_origen' => 17.99300568,
            'longitud_origen' => -92.94544775,
            'radio_tolerancia_km' => 12.00,
            'tarifa_envio_extra' => 60.00,
            'cobro_extra_por_km' => false,
            'usar_api_distancia' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Automatización e inyección de Permisos de Spatie
        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'Administrador')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($this->permisos);
        }
    }

    /**
     * Revierte la migración y limpia la tabla de permisos.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_entregas');
        Schema::dropIfExists('catalogo_horarios_entrega');
        Schema::dropIfExists('catalogo_zonas_entrega');

        foreach ($this->permisos as $permiso) {
            Permission::where('name', $permiso)->delete();
        }
    }
};