<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\EstablecerSucursalActivaPdvRequest;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class EstablecerSucursalActivaPdvController extends Controller
{
    public function __invoke(
        EstablecerSucursalActivaPdvRequest $request,
        ResuelveAlcancePdv $alcance,
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
