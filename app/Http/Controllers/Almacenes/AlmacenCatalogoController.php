<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\CatalogoTipoAlmacen;
use App\Services\Almacenes\AlcanceAlmacenesService;
use App\Services\Almacenes\GuardarAlmacenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlmacenCatalogoController extends Controller
{
    public function index(Request $request, AlcanceAlmacenesService $alcance): Response
    {
        $this->autorizarVer();
        $user = $request->user();
        $sucursales = $alcance->sucursalesOperables($user);

        $sucursalId = $request->query('sucursal_id');
        if ($sucursalId !== null && $sucursalId !== '') {
            $alcance->asegurarSucursalOperable($user, (int) $sucursalId);
        } else {
            $sucursalId = $alcance->sucursalActivaId($user);
        }

        $almacenes = $alcance->almacenesOperables($user, $sucursalId ? (int) $sucursalId : null);

        return Inertia::render('Almacenes/Catalogo/Index', [
            'sucursales' => $sucursales,
            'almacenes' => $almacenes,
            'tipos_almacen' => CatalogoTipoAlmacen::orderBy('nombre')->get(),
            'filtros' => [
                'sucursal_id' => $sucursalId ? (string) $sucursalId : '',
            ],
            'sin_sucursales_operables' => $sucursales->isEmpty(),
            'puede_gestionar' => $user->can('almacenes.inventarios.gestionar') || $user->can('catalogos.gestionar'),
        ]);
    }

    public function store(Request $request, AlcanceAlmacenesService $alcance, GuardarAlmacenService $guardar): RedirectResponse
    {
        $this->autorizarGestionar();
        $user = $request->user();

        $data = $request->validate([
            'codigo' => 'required|string|max:50|unique:almacenes,codigo',
            'nombre' => 'required|string|max:255',
            'sucursal_id' => 'required|exists:sucursales,id',
            'tipo_almacen_id' => 'nullable|exists:catalogo_tipos_almacen,id',
            'activo' => 'boolean',
            'visible_en_pedidos' => 'boolean',
            'visible_en_traspasos' => 'boolean',
            'permite_busqueda_productos' => 'boolean',
        ]);

        $alcance->asegurarSucursalOperable($user, (int) $data['sucursal_id']);
        $guardar->crear($data);

        return back()->with('success', 'Almacén registrado.');
    }

    public function update(Request $request, Almacen $almacen, AlcanceAlmacenesService $alcance, GuardarAlmacenService $guardar): RedirectResponse
    {
        $this->autorizarGestionar();
        $user = $request->user();
        $alcance->asegurarAlmacenOperable($user, $almacen->id);

        $data = $request->validate([
            'codigo' => 'required|string|max:50|unique:almacenes,codigo,'.$almacen->id,
            'nombre' => 'required|string|max:255',
            'sucursal_id' => 'required|exists:sucursales,id',
            'tipo_almacen_id' => 'nullable|exists:catalogo_tipos_almacen,id',
            'activo' => 'boolean',
            'visible_en_pedidos' => 'boolean',
            'visible_en_traspasos' => 'boolean',
            'permite_busqueda_productos' => 'boolean',
        ]);

        $alcance->asegurarSucursalOperable($user, (int) $data['sucursal_id']);
        $guardar->actualizar($almacen, $data);

        return back()->with('success', 'Almacén actualizado.');
    }

    private function autorizarVer(): void
    {
        $user = request()->user();
        if ($user->can('almacenes.inventarios.ver')
            || $user->can('almacenes.costos.ver')
            || $user->can('catalogos.gestionar')) {
            return;
        }
        abort(403);
    }

    private function autorizarGestionar(): void
    {
        $user = request()->user();
        if ($user->can('almacenes.inventarios.gestionar') || $user->can('catalogos.gestionar')) {
            return;
        }
        abort(403);
    }
}
