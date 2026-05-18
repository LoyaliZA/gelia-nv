<?php

namespace App\Observers;

use App\Models\CatalogoListaDescuento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CatalogoListaDescuentoObserver
{
    /**
     * Intercepta el evento justo después de que el registro ha sido actualizado.
     */
    public function updated(CatalogoListaDescuento $lista): void
    {
        // Solo registramos si el monto requerido fue modificado
        if ($lista->wasChanged('monto_requerido')) {
            $this->registrarAuditoria($lista);
        }
    }

    /**
     * Extrae y persiste la información del cambio.
     */
    private function registrarAuditoria(CatalogoListaDescuento $lista): void
    {
        $origen = app()->runningInConsole() ? 'CONSOLA / SCRIPT BASH' : 'INTERFAZ WEB';

        DB::table('auditorias_listas_descuento')->insert([
            'lista_id'       => $lista->id,
            'usuario_id'     => Auth::check() ? Auth::id() : null,
            'monto_anterior' => $lista->getOriginal('monto_requerido'),
            'monto_nuevo'    => $lista->monto_requerido,
            'origen_cambio'  => $origen,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}