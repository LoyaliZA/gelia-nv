<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendanube_image_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado')->default('pendiente');
            $table->unsignedInteger('total_archivos')->default(0);
            $table->unsignedInteger('procesados')->default(0);
            $table->unsignedInteger('exitosos')->default(0);
            $table->unsignedInteger('fallidos')->default(0);
            $table->string('zip_path')->nullable();
            $table->string('extract_path')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamps();
        });

        Schema::create('tiendanube_image_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('tiendanube_image_imports')->cascadeOnDelete();
            $table->string('filename');
            $table->string('relative_path')->nullable();
            $table->string('sku')->nullable()->index();
            $table->unsignedInteger('position')->default(1);
            $table->unsignedBigInteger('producto_id')->nullable()->index();
            $table->string('estado')->default('pendiente');
            $table->text('mensaje')->nullable();
            $table->unsignedBigInteger('imagen_tn_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_image_import_items');
        Schema::dropIfExists('tiendanube_image_imports');
    }
};
