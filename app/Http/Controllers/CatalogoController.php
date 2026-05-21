<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CatalogoProceso;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoTipoCliente; // <-- NUEVO
use App\Models\Cliente;
use App\Models\Departamento; // <-- NUEVO
use App\Models\Area;         // <-- NUEVO
use App\Models\CatalogoZonaEntrega; // <-- NUEVO
use App\Models\CatalogoHorarioEntrega;
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

    // --- 4. CATÁLOGO DE DEPARTAMENTOS ---
    public function storeDepartamento(Request $request) {
        Departamento::create($request->validate([
            'nombre' => 'required|string|max:255', 
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Departamento creado correctamente.');
    }

    public function updateDepartamento(Request $request, $id) {
        Departamento::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255', 
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Departamento actualizado.');
    }

    public function destroyDepartamento($id) {
        // En BD el ON DELETE CASCADE se encargará de borrar las áreas vinculadas
        Departamento::findOrFail($id)->delete();
        return back()->with('success', 'Departamento eliminado exitosamente.');
    }

    // --- 5. CATÁLOGO DE ÁREAS ---
    public function storeArea(Request $request) {
        Area::create($request->validate([
            'nombre'          => 'required|string|max:255', 
            'departamento_id' => 'required|exists:departamentos,id'
        ]));
        return back()->with('success', 'Área creada correctamente.');
    }

    public function updateArea(Request $request, $id) {
        Area::findOrFail($id)->update($request->validate([
            'nombre'          => 'required|string|max:255', 
            'departamento_id' => 'required|exists:departamentos,id'
        ]));
        return back()->with('success', 'Área actualizada.');
    }

    public function destroyArea($id) {
        Area::findOrFail($id)->delete();
        return back()->with('success', 'Área eliminada exitosamente.');
    }

    // --- 6. CATÁLOGO DE TIPOS DE CLIENTE ---
    public function storeTipoCliente(Request $request) {
        CatalogoTipoCliente::create($request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Tipo de cliente registrado.');
    }

    public function updateTipoCliente(Request $request, $id) {
        CatalogoTipoCliente::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Tipo de cliente actualizado.');
    }

    public function destroyTipoCliente($id) {
        CatalogoTipoCliente::findOrFail($id)->delete();
        return back()->with('success', 'Tipo de cliente eliminado.');
    }

    // ----------------------------------------------------------------------
    // 7. CATÁLOGO DE ZONAS DE ENTREGA (Actualizado con Color)
    // ----------------------------------------------------------------------
    public function updateZonaEntrega(Request $request, $id) {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'color_hex' => 'required|string|size:7',
            'costo_base' => 'required|numeric|min:0',
            'activo' => 'boolean'
        ]);

        CatalogoZonaEntrega::findOrFail($id)->update([
            'nombre' => $request->nombre,
            'color_hex' => $request->color_hex,
            'costo_base' => $request->costo_base,
            'activo' => $request->activo ?? false
        ]);

        return back()->with('success', 'Costos y color de la zona logística actualizados.');
    }

    public function destroyZonaEntrega($id) {
        CatalogoZonaEntrega::findOrFail($id)->update(['activo' => false]);
        return back()->with('success', 'Zona de entrega desactivada del mapa.');
    }

    // ----------------------------------------------------------------------
    // 8. CATÁLOGO DE HORARIOS DE ENTREGA
    // ----------------------------------------------------------------------
    public function storeHorarioEntrega(Request $request) {
        CatalogoHorarioEntrega::create($request->validate([
            'zona_id' => 'required|exists:catalogo_zonas_entrega,id',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Horario de entrega registrado.');
    }

    public function updateHorarioEntrega(Request $request, $id) {
        CatalogoHorarioEntrega::findOrFail($id)->update($request->validate([
            'zona_id' => 'required|exists:catalogo_zonas_entrega,id',
            'hora_inicio' => 'required|date_format:H:i|date_format:H:i:s', // Soporta ambos formatos al editar
            'hora_fin' => 'required|date_format:H:i|date_format:H:i:s',
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Horario de entrega actualizado.');
    }

    public function destroyHorarioEntrega($id) {
        CatalogoHorarioEntrega::findOrFail($id)->delete();
        return back()->with('success', 'Horario de entrega eliminado.');
    }
}