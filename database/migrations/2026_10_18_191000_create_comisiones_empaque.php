<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'gestion_interna.comisiones_empaque.ver',
        'gestion_interna.comisiones_empaque.configurar',
        'gestion_interna.comisiones_empaque.clasificar',
        'gestion_interna.comisiones_empaque.confirmar_cortes',
        'gestion_interna.comisiones_empaque.registrar_pagos',
        'gestion_interna.comisiones_empaque.ajustar',
        'gestion_interna.comisiones_empaque.exportar',
    ];

    public function up(): void
    {
        Schema::create('comisiones_empaque_areas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('comisiones_empaque_configuracion', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_inicio')->nullable();
            $table->string('zona_horaria', 64)->default('America/Mexico_City');
            $table->timestamps();
        });

        Schema::create('comisiones_empaque_participantes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('referencia_interna')->nullable();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('comisiones_empaque_usuario_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('comisiones_empaque_areas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'area_id']);
        });

        Schema::create('comisiones_empaque_tarifas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('comisiones_empaque_areas')->restrictOnDelete();
            $table->unsignedBigInteger('importe_centavos');
            $table->dateTime('vigente_desde');
            $table->dateTime('vigente_hasta')->nullable();
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['area_id', 'vigente_desde']);
        });

        Schema::create('comisiones_empaque_repartos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('comisiones_empaque_areas')->restrictOnDelete();
            $table->dateTime('vigente_desde');
            $table->dateTime('vigente_hasta')->nullable();
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['area_id', 'vigente_desde']);
        });

        Schema::create('comisiones_empaque_reparto_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reparto_id')->constrained('comisiones_empaque_repartos')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('comisiones_empaque_participantes')->restrictOnDelete();
            $table->unsignedInteger('puntos_base');
            $table->timestamps();
            $table->unique(['reparto_id', 'participante_id'], 'ce_reparto_part_uq');
        });

        Schema::create('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->id();
            $table->string('clave_origen', 120)->unique();
            $table->foreignId('area_id')->constrained('comisiones_empaque_areas')->restrictOnDelete();
            $table->unsignedBigInteger('pedido_bma_id')->nullable()->index();
            $table->string('procedencia', 40);
            $table->string('origen_registro', 20)->default('sincronizado');
            $table->string('origen_tipo', 40)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->dateTime('empacado_at');
            $table->dateTime('incorporado_at');
            $table->foreignId('empacador_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('empacador_nombre')->nullable();
            $table->string('cliente_nombre')->nullable();
            $table->string('folio_pedido')->nullable();
            $table->string('folio_remision')->nullable();
            $table->json('logistica')->nullable();
            $table->unsignedInteger('cantidad_origen')->default(0);
            $table->unsignedInteger('cantidad_comisionable')->nullable();
            $table->bigInteger('importe_centavos')->nullable();
            $table->string('situacion', 40);
            $table->string('revision_estado', 20)->default('no_aplica');
            $table->text('revision_motivo')->nullable();
            $table->foreignId('revision_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revision_at')->nullable();
            $table->unsignedBigInteger('corte_id')->nullable()->index();
            $table->boolean('incorporacion_tardia')->default(false);
            $table->boolean('excepcion_resguardo')->default(false);
            $table->string('huella', 64)->nullable();
            $table->string('operacion_folio')->nullable();
            $table->boolean('es_complemento')->default(false);
            $table->text('diferencia')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->index(['area_id', 'situacion', 'empacado_at'], 'ce_unidades_area_sit_fecha_idx');
            $table->index(['pedido_bma_id', 'procedencia'], 'ce_unidades_pedido_proc_idx');
        });

        Schema::create('comisiones_empaque_unidad_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('comisiones_empaque_unidades')->cascadeOnDelete();
            $table->foreignId('catalogo_tipo_caja_id')->nullable()->constrained('catalogo_tipos_caja_pedido')->nullOnDelete();
            $table->unsignedInteger('cantidad');
            $table->boolean('es_bolsa')->default(false);
            $table->timestamps();
        });

        Schema::create('comisiones_empaque_incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('comisiones_empaque_unidades')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->string('estado', 20)->default('pendiente');
            $table->string('decision', 40)->nullable();
            $table->text('motivo')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resuelta_at')->nullable();
            $table->json('datos')->nullable();
            $table->timestamps();
            $table->index(['unidad_id', 'tipo', 'estado']);
        });

        Schema::create('comisiones_empaque_cortes', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->nullable()->unique();
            $table->foreignId('area_id')->constrained('comisiones_empaque_areas')->restrictOnDelete();
            $table->string('estado', 20);
            $table->dateTime('limite_desde');
            $table->dateTime('limite_hasta');
            $table->boolean('incluye_tardias')->default(false);
            $table->unsignedInteger('cajas')->default(0);
            $table->bigInteger('total_centavos')->default(0);
            $table->string('hash_calculo', 64)->nullable();
            $table->json('simulacion')->nullable();
            $table->dateTime('confirmado_at')->nullable();
            $table->foreignId('confirmado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['area_id', 'estado', 'limite_desde'], 'ce_cortes_area_estado_idx');
        });

        Schema::create('comisiones_empaque_corte_unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corte_id')->constrained('comisiones_empaque_cortes')->restrictOnDelete();
            $table->foreignId('unidad_id')->unique()->constrained('comisiones_empaque_unidades')->restrictOnDelete();
            $table->string('clave_origen', 120);
            $table->boolean('incorporacion_tardia')->default(false);
            $table->boolean('excepcion_resguardo')->default(false);
            $table->dateTime('empacado_at');
            $table->unsignedInteger('cantidad_comisionable');
            $table->bigInteger('importe_centavos');
            $table->json('desglose');
            $table->json('referencia');
            $table->timestamps();
        });

        Schema::table('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->foreign('corte_id')->references('id')->on('comisiones_empaque_cortes')->nullOnDelete();
        });

        Schema::create('comisiones_empaque_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corte_id')->constrained('comisiones_empaque_cortes')->restrictOnDelete();
            $table->foreignId('participante_id')->constrained('comisiones_empaque_participantes')->restrictOnDelete();
            $table->foreignId('reparto_id')->nullable()->constrained('comisiones_empaque_repartos')->nullOnDelete();
            $table->dateTime('tramo_desde')->nullable();
            $table->dateTime('tramo_hasta')->nullable();
            $table->unsignedInteger('puntos_base');
            $table->bigInteger('importe_centavos');
            $table->bigInteger('pagado_centavos')->default(0);
            $table->timestamps();
            $table->unique(['corte_id', 'participante_id', 'reparto_id'], 'ce_asig_corte_part_rep_uq');
        });

        Schema::create('comisiones_empaque_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participante_id')->constrained('comisiones_empaque_participantes')->restrictOnDelete();
            $table->date('fecha_pago');
            $table->bigInteger('importe_centavos');
            $table->string('referencia')->nullable();
            $table->string('medio')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('clave_idempotencia', 80)->nullable()->unique();
            $table->string('estado', 20)->default('aplicado');
            $table->text('motivo_reversion')->nullable();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revertido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('revertido_at')->nullable();
            $table->timestamps();
            $table->index(['participante_id', 'estado']);
        });

        Schema::create('comisiones_empaque_pago_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('comisiones_empaque_pagos')->restrictOnDelete();
            $table->foreignId('asignacion_id')->constrained('comisiones_empaque_asignaciones')->restrictOnDelete();
            $table->bigInteger('importe_centavos');
            $table->timestamps();
        });

        Schema::create('comisiones_empaque_ajustes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corte_id')->nullable()->constrained('comisiones_empaque_cortes')->nullOnDelete();
            $table->foreignId('asignacion_id')->nullable()->constrained('comisiones_empaque_asignaciones')->nullOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('comisiones_empaque_unidades')->nullOnDelete();
            $table->foreignId('participante_id')->nullable()->constrained('comisiones_empaque_participantes')->nullOnDelete();
            $table->bigInteger('importe_centavos');
            $table->boolean('aplicar_en_saldo')->default(false);
            $table->text('motivo');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('comisiones_empaque_sincronizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('estado', 20);
            $table->text('mensaje')->nullable();
            $table->unsignedInteger('diferencias')->default(0);
            $table->unsignedInteger('unidades_nuevas')->default(0);
            $table->unsignedInteger('unidades_actualizadas')->default(0);
            $table->json('detalle')->nullable();
            $table->foreignId('ejecutado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('iniciado_at');
            $table->dateTime('finalizado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('comisiones_empaque_auditorias', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 40);
            $table->unsignedBigInteger('entidad_id');
            $table->string('accion', 40);
            $table->text('motivo')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('datos')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entidad', 'entidad_id']);
        });

        $ahora = now();
        $cedis = DB::table('comisiones_empaque_areas')->insertGetId([
            'codigo' => 'cedis',
            'nombre' => 'CEDIS',
            'activo' => true,
            'sistema' => true,
            'observaciones' => 'Empaque registrado en la operación de CEDIS.',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $tienda = DB::table('comisiones_empaque_areas')->insertGetId([
            'codigo' => 'tienda',
            'nombre' => 'Tienda',
            'activo' => true,
            'sistema' => true,
            'observaciones' => 'Empaque registrado en preparación de tienda o en un registro manual revisado.',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        foreach ([$cedis => 1000, $tienda => 600] as $areaId => $centavos) {
            DB::table('comisiones_empaque_tarifas')->insert([
                'area_id' => $areaId,
                'importe_centavos' => $centavos,
                'vigente_desde' => '2026-01-01 00:00:00',
                'vigente_hasta' => null,
                'creado_por_id' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        DB::table('comisiones_empaque_configuracion')->insert([
            'fecha_inicio' => null,
            'zona_horaria' => 'America/Mexico_City',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_empaque_auditorias');
        Schema::dropIfExists('comisiones_empaque_sincronizaciones');
        Schema::dropIfExists('comisiones_empaque_ajustes');
        Schema::dropIfExists('comisiones_empaque_pago_aplicaciones');
        Schema::dropIfExists('comisiones_empaque_pagos');
        Schema::dropIfExists('comisiones_empaque_asignaciones');
        Schema::table('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->dropForeign(['corte_id']);
        });
        Schema::dropIfExists('comisiones_empaque_corte_unidades');
        Schema::dropIfExists('comisiones_empaque_cortes');
        Schema::dropIfExists('comisiones_empaque_incidencias');
        Schema::dropIfExists('comisiones_empaque_unidad_lineas');
        Schema::dropIfExists('comisiones_empaque_unidades');
        Schema::dropIfExists('comisiones_empaque_reparto_participantes');
        Schema::dropIfExists('comisiones_empaque_repartos');
        Schema::dropIfExists('comisiones_empaque_tarifas');
        Schema::dropIfExists('comisiones_empaque_usuario_areas');
        Schema::dropIfExists('comisiones_empaque_participantes');
        Schema::dropIfExists('comisiones_empaque_configuracion');
        Schema::dropIfExists('comisiones_empaque_areas');

        foreach (self::PERMISOS as $permiso) {
            Permission::query()->where('name', $permiso)->where('guard_name', 'web')->delete();
        }
    }
};
