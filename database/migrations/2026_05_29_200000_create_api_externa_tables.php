<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('client_id', 64)->unique();
            $table->string('client_secret');
            $table->boolean('activa')->default(true);
            $table->json('ips_permitidas')->nullable();
            $table->unsignedInteger('limite_por_minuto')->default(60);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('api_recursos', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('lectura_habilitada')->default(true);
            $table->boolean('escritura_habilitada')->default(false);
            $table->timestamps();
        });

        Schema::create('api_campos_recurso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_recurso_id')->constrained('api_recursos')->cascadeOnDelete();
            $table->string('slug');
            $table->string('etiqueta');
            $table->boolean('es_sensible')->default(false);
            $table->boolean('habilitado_global')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['api_recurso_id', 'slug']);
        });

        Schema::create('api_aplicacion_permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_aplicacion_id')->constrained('api_aplicaciones')->cascadeOnDelete();
            $table->foreignId('api_recurso_id')->constrained('api_recursos')->cascadeOnDelete();
            $table->boolean('puede_leer')->default(true);
            $table->boolean('puede_escribir')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['api_aplicacion_id', 'api_recurso_id']);
        });

        Schema::create('api_aplicacion_campos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_aplicacion_id')->constrained('api_aplicaciones')->cascadeOnDelete();
            $table->foreignId('api_campo_recurso_id')->constrained('api_campos_recurso')->cascadeOnDelete();
            $table->boolean('habilitado')->default(true);
            $table->timestamps();

            $table->unique(['api_aplicacion_id', 'api_campo_recurso_id'], 'api_app_campo_unique');
        });

        Schema::create('api_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_aplicacion_id')->nullable()->constrained('api_aplicaciones')->nullOnDelete();
            $table->string('metodo', 10);
            $table->string('ruta');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('query_params')->nullable();
            $table->uuid('request_id')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duracion_ms')->nullable();
            $table->string('error_resumen')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['api_aplicacion_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_auditoria');
        Schema::dropIfExists('api_aplicacion_campos');
        Schema::dropIfExists('api_aplicacion_permisos');
        Schema::dropIfExists('api_campos_recurso');
        Schema::dropIfExists('api_recursos');
        Schema::dropIfExists('api_aplicaciones');
    }
};
