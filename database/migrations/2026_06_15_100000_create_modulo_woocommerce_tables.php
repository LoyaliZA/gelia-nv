<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permisos = [
        'woocommerce.ver',
        'woocommerce.sincronizar',
        'woocommerce.configurar',
        'woocommerce.auditoria',
        'woocommerce.emergencia',
    ];

    public function up(): void
    {
        Schema::create('woocommerce_configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('store_url')->nullable();
            $table->text('consumer_key')->nullable();
            $table->text('consumer_secret')->nullable();
            $table->decimal('iva', 8, 4)->default(1.16);
            $table->json('notified_users')->nullable();
            $table->timestamps();
        });

        DB::table('woocommerce_configuracion')->insert([
            'iva' => 1.16,
            'notified_users' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('woocommerce_products', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('sku')->unique();
            $table->string('nombre');
            $table->decimal('precio_normal', 10, 2)->nullable();
            $table->decimal('precio_rebajado', 10, 2)->nullable();
            $table->string('tipo')->default('simple');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('woocommerce_margins', function (Blueprint $table) {
            $table->id();
            $table->decimal('precio_min', 8, 2);
            $table->decimal('precio_max', 10, 2);
            $table->decimal('multiplicador_rebaja', 5, 2);
            $table->decimal('multiplicador_normal', 5, 2);
            $table->timestamps();
        });

        Schema::create('woocommerce_templates', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('ruta_fisica');
            $table->string('tamano_kb')->nullable();
            $table->boolean('subido_drive')->default(false);
            $table->string('drive_id')->nullable();
            $table->timestamps();
        });

        Schema::create('woocommerce_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('total_productos')->default(0);
            $table->integer('procesados')->default(0);
            $table->string('estado')->default('pendiente');
            $table->text('mensaje_error')->nullable();
            $table->timestamps();
        });

        Schema::create('woocommerce_sync_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->constrained('woocommerce_sync_logs')->cascadeOnDelete();
            $table->string('sku');
            $table->decimal('precio_anterior_normal', 10, 2)->nullable();
            $table->decimal('precio_nuevo_normal', 10, 2)->nullable();
            $table->decimal('precio_anterior_rebajado', 10, 2)->nullable();
            $table->decimal('precio_nuevo_rebajado', 10, 2)->nullable();
            $table->string('estado')->default('exito');
            $table->text('mensaje')->nullable();
            $table->timestamps();
        });

        DB::table('woocommerce_margins')->insert([
            ['precio_min' => 0, 'precio_max' => 100.00, 'multiplicador_rebaja' => 1.70, 'multiplicador_normal' => 1.80, 'created_at' => now(), 'updated_at' => now()],
            ['precio_min' => 100.01, 'precio_max' => 129.00, 'multiplicador_rebaja' => 1.60, 'multiplicador_normal' => 1.78, 'created_at' => now(), 'updated_at' => now()],
            ['precio_min' => 129.01, 'precio_max' => 190.00, 'multiplicador_rebaja' => 1.55, 'multiplicador_normal' => 1.73, 'created_at' => now(), 'updated_at' => now()],
            ['precio_min' => 190.01, 'precio_max' => 280.00, 'multiplicador_rebaja' => 1.50, 'multiplicador_normal' => 1.63, 'created_at' => now(), 'updated_at' => now()],
            ['precio_min' => 280.01, 'precio_max' => 359.00, 'multiplicador_rebaja' => 1.45, 'multiplicador_normal' => 1.55, 'created_at' => now(), 'updated_at' => now()],
            ['precio_min' => 359.01, 'precio_max' => 399.00, 'multiplicador_rebaja' => 1.35, 'multiplicador_normal' => 1.41, 'created_at' => now(), 'updated_at' => now()],
            ['precio_min' => 399.01, 'precio_max' => 699.00, 'multiplicador_rebaja' => 1.30, 'multiplicador_normal' => 1.35, 'created_at' => now(), 'updated_at' => now()],
            ['precio_min' => 699.01, 'precio_max' => 999.00, 'multiplicador_rebaja' => 1.25, 'multiplicador_normal' => 1.28, 'created_at' => now(), 'updated_at' => now()],
            ['precio_min' => 999.01, 'precio_max' => 99999.00, 'multiplicador_rebaja' => 1.20, 'multiplicador_normal' => 1.22, 'created_at' => now(), 'updated_at' => now()],
        ]);

        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'Administrador')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($this->permisos);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_sync_details');
        Schema::dropIfExists('woocommerce_sync_logs');
        Schema::dropIfExists('woocommerce_templates');
        Schema::dropIfExists('woocommerce_margins');
        Schema::dropIfExists('woocommerce_products');
        Schema::dropIfExists('woocommerce_configuracion');

        foreach ($this->permisos as $permiso) {
            Permission::where('name', $permiso)->delete();
        }
    }
};
