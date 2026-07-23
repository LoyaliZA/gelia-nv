<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_traspaso_detalle_danos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitud_traspaso_id');
            $table->unsignedBigInteger('solicitud_traspaso_producto_id');
            $table->text('motivo');
            $table->json('paths')->nullable();
            $table->foreignId('reportado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reportado_at')->nullable();
            $table->timestamps();

            // Nombres cortos: MySQL limita identificadores a 64 chars.
            $table->foreign('solicitud_traspaso_id', 'st_det_danos_sol_fk')
                ->references('id')->on('solicitudes_traspasos')->cascadeOnDelete();
            $table->foreign('solicitud_traspaso_producto_id', 'st_det_danos_prod_fk')
                ->references('id')->on('solicitud_traspaso_productos')->cascadeOnDelete();
            $table->unique('solicitud_traspaso_producto_id', 'st_det_danos_prod_uq');
        });

        if (Schema::hasColumn('solicitudes_traspasos', 'detalle_dano_motivo')) {
            $legacy = DB::table('solicitudes_traspasos')
                ->where(function ($q) {
                    $q->whereNotNull('detalle_dano_at')
                        ->orWhereNotNull('detalle_dano_motivo')
                        ->orWhereNotNull('detalle_dano_paths');
                })
                ->get(['id', 'detalle_dano_motivo', 'detalle_dano_paths', 'detalle_dano_por_id', 'detalle_dano_at']);

            foreach ($legacy as $sol) {
                $paths = $sol->detalle_dano_paths;
                if (is_string($paths)) {
                    $paths = json_decode($paths, true) ?: [];
                }
                if (! is_array($paths)) {
                    $paths = [];
                }

                $lineas = DB::table('solicitud_traspaso_productos')
                    ->where('solicitud_traspaso_id', $sol->id)
                    ->pluck('id');

                foreach ($lineas as $lineaId) {
                    DB::table('solicitud_traspaso_detalle_danos')->insert([
                        'solicitud_traspaso_id' => $sol->id,
                        'solicitud_traspaso_producto_id' => $lineaId,
                        'motivo' => $sol->detalle_dano_motivo ?: 'Detalle/daño reportado por CEDIS (migración).',
                        'paths' => json_encode($paths),
                        'reportado_por_id' => $sol->detalle_dano_por_id,
                        'reportado_at' => $sol->detalle_dano_at ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            Schema::table('solicitudes_traspasos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('detalle_dano_por_id');
                $table->dropColumn([
                    'detalle_dano_motivo',
                    'detalle_dano_paths',
                    'detalle_dano_at',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('solicitudes_traspasos', function (Blueprint $table) {
            if (! Schema::hasColumn('solicitudes_traspasos', 'detalle_dano_motivo')) {
                $table->text('detalle_dano_motivo')->nullable()->after('respondida_at');
                $table->json('detalle_dano_paths')->nullable()->after('detalle_dano_motivo');
                $table->foreignId('detalle_dano_por_id')->nullable()->after('detalle_dano_paths')
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('detalle_dano_at')->nullable()->after('detalle_dano_por_id');
            }
        });

        Schema::dropIfExists('solicitud_traspaso_detalle_danos');
    }
};
