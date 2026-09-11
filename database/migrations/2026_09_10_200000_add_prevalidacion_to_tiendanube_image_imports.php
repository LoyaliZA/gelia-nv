<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_image_imports', function (Blueprint $table) {
            $table->timestamp('confirmado_at')->nullable()->after('modo_1280');
        });

        Schema::table('tiendanube_image_import_items', function (Blueprint $table) {
            $table->string('resolucion_estado')->nullable()->after('sku');
            $table->json('candidatos_json')->nullable()->after('resolucion_estado');
            $table->boolean('excluido')->default(false)->after('producto_id');
            $table->string('claim_token', 64)->nullable()->after('operacion_id');
            $table->timestamp('claim_expires_at')->nullable()->after('claim_token');
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_image_import_items', function (Blueprint $table) {
            $table->dropColumn(['resolucion_estado', 'candidatos_json', 'excluido', 'claim_token', 'claim_expires_at']);
        });

        Schema::table('tiendanube_image_imports', function (Blueprint $table) {
            $table->dropColumn('confirmado_at');
        });
    }
};
