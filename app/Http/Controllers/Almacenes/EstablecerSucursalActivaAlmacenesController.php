<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Almacenes\EstablecerSucursalActivaAlmacenesRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Almacenes\AlcanceAlmacenesService;
use Illuminate\Http\JsonResponse;

class EstablecerSucursalActivaAlmacenesController extends Controller
{
    public function __invoke(
        EstablecerSucursalActivaAlmacenesRequest $request,
        AlcanceAlmacenesService $alcance,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $sucursalId = (int) $request->validated('sucursal_id');

        $alcance->establecerSucursalActiva($user, $sucursalId);

        $sucursal = Sucursal::query()->findOrFail($sucursalId, ['id', 'nombre']);

        return response()->json([
            'sucursal_activa' => [
                'id' => $sucursal->id,
                'nombre' => $sucursal->nombre,
            ],
        ]);
    }
}
