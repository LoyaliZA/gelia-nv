<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias_despliegues', function (Blueprint $table) {
            $table->id();
            $table->string('accion', 40);
            $table->string('superficie', 40)->nullable();
            $table->string('origen', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('detalles')->nullable();
            $table->timestamps();

            $table->index(['accion', 'created_at']);
            $table->index(['superficie', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_despliegues');
    }
};
