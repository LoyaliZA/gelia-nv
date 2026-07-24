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
        'tiendanube.ver',
        'tiendanube.configurar',
        'tiendanube.sincronizar',
    ];

    public function up(): void
    {
        Schema::create('tiendanube_configuracion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->text('access_token')->nullable();
            $table->string('app_id')->nullable();
            $table->string('scopes')->nullable();
            $table->string('store_name')->nullable();
            $table->string('store_url')->nullable();
            $table->timestamps();
        });

        DB::table('tiendanube_configuracion')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('tiendanube_categorias', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->json('name')->nullable();
            $table->json('handle')->nullable();
            $table->json('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedBigInteger('gelia_categoria_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('tiendanube_productos', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->json('handle')->nullable();
            $table->string('brand')->nullable();
            $table->boolean('published')->default(false);
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->text('tags')->nullable();
            $table->json('attributes')->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedBigInteger('gelia_producto_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('tiendanube_producto_imagenes', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('producto_id')->index();
            $table->string('src')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->string('alt')->nullable();
            $table->timestamps();

            $table->foreign('producto_id')
                ->references('id')
                ->on('tiendanube_productos')
                ->cascadeOnDelete();
        });

        Schema::create('tiendanube_producto_variantes', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('producto_id')->index();
            $table->string('sku')->nullable()->index();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('promotional_price', 12, 2)->nullable();
            $table->integer('stock')->nullable();
            $table->boolean('stock_management')->default(false);
            $table->json('values')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->timestamps();

            $table->foreign('producto_id')
                ->references('id')
                ->on('tiendanube_productos')
                ->cascadeOnDelete();
        });

        Schema::create('tiendanube_producto_categoria', function (Blueprint $table) {
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('categoria_id');
            $table->primary(['producto_id', 'categoria_id']);

            $table->foreign('producto_id')
                ->references('id')
                ->on('tiendanube_productos')
                ->cascadeOnDelete();
            $table->foreign('categoria_id')
                ->references('id')
                ->on('tiendanube_categorias')
                ->cascadeOnDelete();
        });

        Schema::create('tiendanube_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tipo')->default('completo');
            $table->string('estado')->default('pendiente');
            $table->unsignedInteger('total_categorias')->default(0);
            $table->unsignedInteger('total_productos')->default(0);
            $table->unsignedInteger('procesados_categorias')->default(0);
            $table->unsignedInteger('procesados_productos')->default(0);
            $table->text('mensaje_error')->nullable();
            $table->timestamps();
        });

        foreach ($this->permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'Administrador')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($this->permisos);
        }

        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($this->permisos);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_producto_categoria');
        Schema::dropIfExists('tiendanube_producto_variantes');
        Schema::dropIfExists('tiendanube_producto_imagenes');
        Schema::dropIfExists('tiendanube_productos');
        Schema::dropIfExists('tiendanube_categorias');
        Schema::dropIfExists('tiendanube_sync_logs');
        Schema::dropIfExists('tiendanube_configuracion');

        foreach ($this->permisos as $permiso) {
            Permission::where('name', $permiso)->delete();
        }
    }
};
