<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CatalogoProceso;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class CatalogoController extends Controller
{
    // --- 1. CATÁLOGO DE PROCESOS ---
    public function storeProceso(Request $request) {
        CatalogoProceso::create($request->validate(['nombre' => 'required', 'descripcion' => 'nullable', 'activo' => 'boolean']));
        return back()->with('success', 'Proceso creado correctamente.');
    }

    public function updateProceso(Request $request, $id) {
        CatalogoProceso::findOrFail($id)->update($request->validate(['nombre' => 'required', 'descripcion' => 'nullable', 'activo' => 'boolean']));
        return back()->with('success', 'Proceso actualizado.');
    }

    public function destroyProceso($id) {
        CatalogoProceso::findOrFail($id)->delete();
        return back()->with('success', 'Proceso eliminado.');
    }

    // --- 2. CATÁLOGO DE ESTADOS ---
    public function storeEstado(Request $request) {
        CatalogoEstadoSolicitud::create($request->validate(['nombre' => 'required', 'descripcion' => 'nullable', 'activo' => 'boolean']));
        return back()->with('success', 'Estado creado correctamente.');
    }

    public function updateEstado(Request $request, $id) {
        CatalogoEstadoSolicitud::findOrFail($id)->update($request->validate(['nombre' => 'required', 'descripcion' => 'nullable', 'activo' => 'boolean']));
        return back()->with('success', 'Estado actualizado.');
    }

    public function destroyEstado($id) {
        CatalogoEstadoSolicitud::findOrFail($id)->delete();
        return back()->with('success', 'Estado eliminado.');
    }

    // --- 3. CATÁLOGO DE LISTAS DE DESCUENTO (Con Revinculación) ---
    public function storeLista(Request $request) {
        CatalogoListaDescuento::create($request->validate(['nombre' => 'required', 'monto_requerido' => 'required|numeric', 'activo' => 'boolean']));
        return back()->with('success', 'Lista creada correctamente.');
    }

    public function updateLista(Request $request, $id) {
        CatalogoListaDescuento::findOrFail($id)->update($request->validate(['nombre' => 'required', 'monto_requerido' => 'required|numeric', 'activo' => 'boolean']));
        return back()->with('success', 'Lista actualizada.');
    }

    public function destroyLista(Request $request, $id) {
        $lista = CatalogoListaDescuento::findOrFail($id);
        
        // Si el usuario especificó a qué lista revincular a los clientes, los movemos primero.
        if ($request->has('reubicar_en_id') && $request->reubicar_en_id) {
            Cliente::where('lista_actual_id', $id)->update(['lista_actual_id' => $request->reubicar_en_id]);
        }

        $lista->delete();
        return back()->with('success', 'Lista eliminada y clientes revinculados exitosamente.');
    }
}