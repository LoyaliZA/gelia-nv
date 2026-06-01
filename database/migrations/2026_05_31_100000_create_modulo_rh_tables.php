<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_puestos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('rh_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('folio_prefijo', 20)->default('COL');
            $table->string('folio_separador', 5)->default('-');
            $table->unsignedTinyInteger('folio_padding')->default(6);
            $table->boolean('folio_incluir_anio')->default(false);
            $table->unsignedSmallInteger('dias_periodo_pago')->default(30);
            $table->unsignedTinyInteger('decimales_salario_minuto')->default(8);
            $table->timestamps();
        });

        Schema::create('rh_colaboradores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('nombre');
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->foreignId('catalogo_puesto_id')->constrained('catalogo_puestos');
            $table->decimal('salario_base', 12, 2)->default(0);
            $table->decimal('bono_productividad', 12, 2)->default(0);
            $table->decimal('bono_puntualidad', 12, 2)->default(0);
            $table->decimal('horas_laboradas_oficiales', 4, 2)->default(8);
            $table->decimal('salario_diario', 12, 2)->default(0);
            $table->decimal('bono_productividad_diario', 12, 2)->default(0);
            $table->decimal('bono_puntualidad_diario', 12, 2)->default(0);
            $table->decimal('salario_por_hora', 12, 4)->default(0);
            $table->decimal('salario_por_minuto', 16, 8)->default(0);
            $table->boolean('activo')->default(true);
            $table->foreignId('registrado_por_id')->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['departamento_id', 'activo']);
            $table->index('catalogo_puesto_id');
        });

        $this->seedConfiguracion();
        $this->seedPermisos();
    }

    private function seedConfiguracion(): void
    {
        DB::table('rh_configuraciones')->insert([
            'folio_prefijo' => config('rh.folio_prefijo', 'COL'),
            'folio_separador' => config('rh.folio_separador', '-'),
            'folio_padding' => config('rh.folio_padding', 6),
            'folio_incluir_anio' => config('rh.folio_incluir_anio', false),
            'dias_periodo_pago' => config('rh.dias_periodo_pago', 30),
            'decimales_salario_minuto' => config('rh.decimales_salario_minuto', 8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPermisos(): void
    {
        $permisos = [
            'rh.ver',
            'rh.colaboradores.crear',
            'rh.colaboradores.editar',
            'rh.colaboradores.vincular_usuario',
            'rh.configurar',
            'rh.catalogos.puestos',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_colaboradores');
        Schema::dropIfExists('rh_configuraciones');
        Schema::dropIfExists('catalogo_puestos');
    }
};
