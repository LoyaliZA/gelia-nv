<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente_direcciones', function (Blueprint $table) {
            if (! Schema::hasColumn('cliente_direcciones', 'nombres_destinatario')) {
                $table->string('nombres_destinatario')->nullable()->after('nombre_destinatario');
            }
            if (! Schema::hasColumn('cliente_direcciones', 'apellidos_destinatario')) {
                $table->string('apellidos_destinatario')->nullable()->after('nombres_destinatario');
            }
            if (! Schema::hasColumn('cliente_direcciones', 'anexa_remision')) {
                $table->boolean('anexa_remision')->default(false)->after('indicaciones_entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cliente_direcciones', function (Blueprint $table) {
            foreach (['nombres_destinatario', 'apellidos_destinatario', 'anexa_remision'] as $col) {
                if (Schema::hasColumn('cliente_direcciones', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
