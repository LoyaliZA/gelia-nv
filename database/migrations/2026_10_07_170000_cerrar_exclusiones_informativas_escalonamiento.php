<?php

use App\Models\Escalonamiento\EscalonamientoIncidencia;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $resolucion = 'Registro informativo: el documento se conserva sin sumar al acumulado (lista no participa en escalonamiento).';

        EscalonamientoIncidencia::query()
            ->whereIn('codigo', EscalonamientoIncidencia::codigosInformativos())
            ->where('estado', 'abierta')
            ->update([
                'estado' => 'resuelta',
                'resolucion' => $resolucion,
                'resuelto_en' => now(),
            ]);
    }

    public function down(): void
    {
        // ponytail: no se revierte; las exclusiones informativas no deben volver a estado abierto.
    }
};
