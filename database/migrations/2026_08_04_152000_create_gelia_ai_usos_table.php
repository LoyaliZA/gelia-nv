<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gelia_ai_usos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversacion_id')
                ->nullable()
                ->constrained('gelia_ai_conversaciones')
                ->nullOnDelete();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedTinyInteger('rounds')->default(0);
            $table->string('mode', 40)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->unsignedInteger('mensaje_chars')->default(0);
            $table->unsignedInteger('reply_chars')->default(0);
            $table->boolean('con_archivos')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
            $table->index('total_tokens');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gelia_ai_usos');
    }
};
