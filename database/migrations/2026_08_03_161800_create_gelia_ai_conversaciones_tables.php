<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gelia_ai_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titulo')->nullable();
            $table->boolean('temporal')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('gelia_ai_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('gelia_ai_conversaciones')->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversacion_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gelia_ai_mensajes');
        Schema::dropIfExists('gelia_ai_conversaciones');
    }
};
