<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'clientes.expediente.ver',
        'clientes.expediente.editar',
        'clientes.expediente.documentos',
        'clientes.expediente.ver_identificacion',
        'clientes.expediente.eliminar',
        'clientes.expediente.ver_historico',
        'control_pedidos.municipal.responder_consulta',
    ];

    public function up(): void
    {
        Schema::create('cliente_consignatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cliente_consignatario_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignatario_id')->constrained('cliente_consignatarios')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('telefono', 40);
            $table->boolean('vigente')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('anterior_id')->nullable()->constrained('cliente_consignatario_versiones')->nullOnDelete();
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['consignatario_id', 'vigente']);
        });

        Schema::create('cliente_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignatario_id')->constrained('cliente_consignatarios')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('mime_type', 120);
            $table->unsignedInteger('tamano_bytes');
            $table->string('hash_sha256', 64);
            $table->boolean('vigente')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('anterior_id')->nullable()->constrained('cliente_documentos')->nullOnDelete();
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['consignatario_id', 'vigente']);
        });

        Schema::create('cliente_expediente_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('consignatario_id')->nullable()->constrained('cliente_consignatarios')->nullOnDelete();
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->string('accion', 40);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('detalle')->nullable();
            $table->timestamps();
        });

        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        $super = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();
        if ($super) {
            $super->givePermissionTo(self::PERMISOS);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_expediente_eventos');
        Schema::dropIfExists('cliente_documentos');
        Schema::dropIfExists('cliente_consignatario_versiones');
        Schema::dropIfExists('cliente_consignatarios');
        Permission::query()->whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();
    }
};
