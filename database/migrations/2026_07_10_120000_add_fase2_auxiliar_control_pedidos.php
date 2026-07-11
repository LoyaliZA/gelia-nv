<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->timestamp('pago_validado_at')->nullable()->after('motivo_rechazo');
            $table->foreignId('pago_validado_por_id')->nullable()->after('pago_validado_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('pedido_bma_documentos', function (Blueprint $table) {
            $table->string('tipo', 30)->default('comprobante')->after('pedido_bma_id');
        });

        DB::table('pedido_bma_documentos')->whereNull('tipo')->update(['tipo' => 'comprobante']);

        PermisoCatalogoMigracion::registrar('control_pedidos.auditar');
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pago_validado_por_id');
            $table->dropColumn('pago_validado_at');
        });

        Schema::table('pedido_bma_documentos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });

        \Spatie\Permission\Models\Permission::where('name', 'control_pedidos.auditar')->delete();
    }
};
