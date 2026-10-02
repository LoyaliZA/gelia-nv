<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'control_pedidos.tienda.empacar_municipio',
        'control_pedidos.tienda.despachar_municipio',
    ];

    public function up(): void
    {
        Schema::table('pedido_bma_cumplimiento_fisico', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'bultos_salida')) {
                $table->unsignedSmallInteger('bultos_salida')->nullable()->after('receptor_nombre');
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'empacado_at')) {
                $table->timestamp('empacado_at')->nullable()->after('bultos_salida');
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'empacado_por_id')) {
                $table->foreignId('empacado_por_id')->nullable()->after('empacado_at')
                    ->constrained('users', indexName: 'pb_cump_emp_por_fk')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'despachada_at')) {
                $table->timestamp('despachada_at')->nullable()->after('empacado_por_id');
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'despachada_por_id')) {
                $table->foreignId('despachada_por_id')->nullable()->after('despachada_at')
                    ->constrained('users', indexName: 'pb_cump_des_por_fk')
                    ->nullOnDelete();
            }
        });

        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        Schema::table('pedido_bma_cumplimiento_fisico', function (Blueprint $table) {
            $table->dropConstrainedForeignId('despachada_por_id');
            $table->dropColumn('despachada_at');
            $table->dropConstrainedForeignId('empacado_por_id');
            $table->dropColumn(['empacado_at', 'bultos_salida']);
        });
    }
};
