<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historial_montos_clientes', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable()->after('cliente_id')->constrained('users')->nullOnDelete();
            $table->string('origen', 50)->nullable()->after('diferencia_aplicada');
            $table->foreignId('importacion_cliente_id')->nullable()->after('origen')
                ->constrained('importaciones_clientes')->nullOnDelete();
            $table->foreignId('solicitud_id')->nullable()->after('importacion_cliente_id')
                ->constrained('solicitudes_tags')->nullOnDelete();
            $table->decimal('monto_operacion', 12, 2)->nullable()->after('solicitud_id');
            $table->text('notas')->nullable()->after('monto_operacion');
        });
    }

    public function down(): void
    {
        Schema::table('historial_montos_clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitud_id');
            $table->dropConstrainedForeignId('importacion_cliente_id');
            $table->dropConstrainedForeignId('usuario_id');
            $table->dropColumn(['origen', 'monto_operacion', 'notas']);
        });
    }
};
