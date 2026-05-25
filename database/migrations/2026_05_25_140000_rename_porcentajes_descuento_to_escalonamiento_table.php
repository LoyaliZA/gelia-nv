<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalogo_porcentajes_descuento_lista')
            && !Schema::hasTable('catalogo_porcentajes_escalonamiento_lista')) {
            Schema::rename(
                'catalogo_porcentajes_descuento_lista',
                'catalogo_porcentajes_escalonamiento_lista'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('catalogo_porcentajes_escalonamiento_lista')
            && !Schema::hasTable('catalogo_porcentajes_descuento_lista')) {
            Schema::rename(
                'catalogo_porcentajes_escalonamiento_lista',
                'catalogo_porcentajes_descuento_lista'
            );
        }
    }
};
