<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\ProductoCosto;
use App\Services\Almacenes\AlcanceAlmacenesService;
use Inertia\Inertia;
use Inertia\Response;

class AlmacenesResumenController extends Controller
{
    public function index(AlcanceAlmacenesService $alcance): Response
    {
        $user = request()->user();
        if (! $user->can('almacenes.inventarios.ver')
            && ! $user->can('almacenes.costos.ver')
            && ! $user->can('catalogos.gestionar')
            && ! $user->can('gestion_interna.productos.ver')
            && ! $user->can('almacenes.productos.ver')) {
            abort(403);
        }

        $idsSucursal = $alcance->idsSucursalesOperables($user);
        $idsAlmacen = $alcance->almacenesOperables($user)->pluck('id');

        $ultimasImportaciones = ImportacionAlmacenLog::query()
            ->when(! $user->can('catalogos.gestionar'), fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->limit(5)
            ->get(['id', 'estado', 'tipo', 'created_at', 'total_filas', 'procesados']);

        return Inertia::render('Almacenes/Resumen/Index', [
            'contadores' => [
                'productos' => Producto::count(),
                'almacenes' => $idsAlmacen->isEmpty()
                    ? 0
                    : Almacen::whereIn('id', $idsAlmacen)->where('activo', true)->count(),
                'asignaciones' => $idsAlmacen->isEmpty()
                    ? 0
                    : ProductoAlmacen::whereIn('almacen_id', $idsAlmacen)->count(),
                'inventarios' => $idsAlmacen->isEmpty()
                    ? 0
                    : Inventario::whereIn('almacen_id', $idsAlmacen)->count(),
                'costos' => $idsAlmacen->isEmpty()
                    ? 0
                    : ProductoCosto::whereIn('almacen_id', $idsAlmacen)->count(),
            ],
            'ultimasImportaciones' => $ultimasImportaciones,
            'sin_sucursales_operables' => $idsSucursal->isEmpty(),
        ]);
    }
}
