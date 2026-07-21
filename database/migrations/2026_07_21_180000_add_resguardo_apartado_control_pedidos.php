<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->timestamp('resguardo_apartado_at')->nullable()->after('es_resguardo');
            $table->foreignId('resguardo_apartado_por_id')->nullable()->after('resguardo_apartado_at')
                ->constrained('users')->nullOnDelete();
            $table->text('detalle_resguardo_apartado')->nullable()->after('resguardo_apartado_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resguardo_apartado_por_id');
            $table->dropColumn(['resguardo_apartado_at', 'detalle_resguardo_apartado']);
        });
    }
};
