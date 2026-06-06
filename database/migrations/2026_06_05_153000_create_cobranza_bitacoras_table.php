<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla cobranza_bitacoras
        Schema::create('cobranza_bitacoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo_evento'); // 'inicio_credito', 'ajuste_manual', 'alerta_aumento', 'pago', etc.
            $table->decimal('monto_anterior', 12, 2)->default(0);
            $table->decimal('monto_nuevo', 12, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->boolean('es_alerta')->default(false);
            $table->timestamps();
        });

        // 2. Nueva columna en clientes
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('alerta_aumento_credito')->default(false)->after('fecha_inicio_credito');
        });

        // 3. Permisos nuevos
        $permisos = [
            'cobranza.ver_bitacora',
            'cobranza.recibir_alertas',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permisos);
        }
    }

    public function down(): void
    {
        $permisos = [
            'cobranza.ver_bitacora',
            'cobranza.recibir_alertas',
        ];
        Permission::whereIn('name', $permisos)->where('guard_name', 'web')->delete();

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('alerta_aumento_credito');
        });

        Schema::dropIfExists('cobranza_bitacoras');
    }
};
