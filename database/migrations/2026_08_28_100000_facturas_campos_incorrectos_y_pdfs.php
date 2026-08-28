<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_facturas', function (Blueprint $table) {
            $table->json('campos_incorrectos')->nullable()->after('motivo_incorrecta');
        });

        Schema::create('solicitud_factura_pdfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_factura_id')->constrained('solicitudes_facturas')->cascadeOnDelete();
            $table->string('path');
            $table->string('nombre_original');
            $table->string('mime')->nullable();
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();
        });

        $filas = DB::table('solicitudes_facturas')
            ->whereNotNull('factura_pdf_path')
            ->where('factura_pdf_path', '!=', '')
            ->get(['id', 'factura_pdf_path', 'factura_pdf_nombre', 'created_at', 'updated_at']);

        foreach ($filas as $fila) {
            DB::table('solicitud_factura_pdfs')->insert([
                'solicitud_factura_id' => $fila->id,
                'path' => $fila->factura_pdf_path,
                'nombre_original' => $fila->factura_pdf_nombre ?: basename($fila->factura_pdf_path),
                'mime' => 'application/pdf',
                'orden' => 1,
                'created_at' => $fila->created_at ?? now(),
                'updated_at' => $fila->updated_at ?? now(),
            ]);
        }

        Schema::table('solicitudes_facturas', function (Blueprint $table) {
            $table->dropColumn(['factura_pdf_path', 'factura_pdf_nombre']);
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_facturas', function (Blueprint $table) {
            $table->string('factura_pdf_path')->nullable();
            $table->string('factura_pdf_nombre')->nullable();
        });

        $pdfs = DB::table('solicitud_factura_pdfs')
            ->orderBy('solicitud_factura_id')
            ->orderBy('orden')
            ->get();

        $restaurados = [];
        foreach ($pdfs as $pdf) {
            if (isset($restaurados[$pdf->solicitud_factura_id])) {
                continue;
            }
            DB::table('solicitudes_facturas')
                ->where('id', $pdf->solicitud_factura_id)
                ->update([
                    'factura_pdf_path' => $pdf->path,
                    'factura_pdf_nombre' => $pdf->nombre_original,
                ]);
            $restaurados[$pdf->solicitud_factura_id] = true;
        }

        Schema::dropIfExists('solicitud_factura_pdfs');

        Schema::table('solicitudes_facturas', function (Blueprint $table) {
            $table->dropColumn('campos_incorrectos');
        });
    }
};
