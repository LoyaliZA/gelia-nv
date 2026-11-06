<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajeria_stickers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('mime', 100);
            $table->unsignedBigInteger('tamano')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::table('mensajes', function (Blueprint $table) {
            $table->enum('tipo', ['texto', 'imagen', 'video', 'audio', 'archivo', 'sticker'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->enum('tipo', ['texto', 'imagen', 'video', 'audio', 'archivo'])->change();
        });

        Schema::dropIfExists('mensajeria_stickers');
    }
};
