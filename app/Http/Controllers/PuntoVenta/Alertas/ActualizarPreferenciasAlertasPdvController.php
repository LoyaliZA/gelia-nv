<?php

namespace App\Http\Controllers\PuntoVenta\Alertas;

use App\Http\Controllers\Controller;
use App\Http\Requests\PuntoVenta\Alertas\ActualizarPreferenciasAlertasPdvRequest;
use App\Models\ConfiguracionUsuario;
use App\Models\User;
use App\Services\PuntoVenta\Alertas\NormalizarPreferenciasAlertasPdvService;
use Illuminate\Http\JsonResponse;

class ActualizarPreferenciasAlertasPdvController extends Controller
{
    public function __invoke(
        ActualizarPreferenciasAlertasPdvRequest $request,
        NormalizarPreferenciasAlertasPdvService $normalizar,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $prefs = $normalizar->ejecutar($request->validated());

        $configuracion = ConfiguracionUsuario::firstOrNew(['user_id' => $user->id]);
        $temaVisual = is_array($configuracion->tema_visual)
            ? $configuracion->tema_visual
            : [];

        $temaVisual['pdv_alertas_prefs'] = $prefs;
        $configuracion->tema_visual = $temaVisual;
        $configuracion->save();

        return response()->json([
            'pdv_alertas_prefs' => $prefs,
        ]);
    }
}
