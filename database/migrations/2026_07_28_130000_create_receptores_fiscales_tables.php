<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receptores_fiscales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_interno', 16)->unique();
            $table->string('rfc', 13)->nullable()->index();
            $table->string('codigo_postal', 5)->nullable();
            $table->string('regimen_fiscal', 10)->nullable();
            $table->string('correo_electronico')->nullable();
            $table->string('uso_factura', 10)->nullable();
            $table->string('nombre_razon_social')->nullable()->index();
            $table->string('telefono', 10)->nullable();
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('cliente_receptor_fiscal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('receptor_fiscal_id')->constrained('receptores_fiscales')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cliente_id', 'receptor_fiscal_id']);
        });

        Schema::table('solicitudes_facturas', function (Blueprint $table) {
            $table->foreignId('receptor_fiscal_id')
                ->nullable()
                ->after('cliente_id')
                ->constrained('receptores_fiscales')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_facturas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receptor_fiscal_id');
        });
        Schema::dropIfExists('cliente_receptor_fiscal');
        Schema::dropIfExists('receptores_fiscales');
    }
};
