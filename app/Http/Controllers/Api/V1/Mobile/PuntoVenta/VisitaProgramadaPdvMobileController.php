<?php

namespace App\Http\Controllers\Api\V1\Mobile\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PuntoVenta\VisitasProgramadas\BandejaVisitasProgramadasPdvController;
use App\Http\Requests\PuntoVenta\VisitasProgramadas\ConfirmarLlegadaVisitaProgramadaPdvRequest;
use App\Models\Comercial\VisitaClienteProgramada;
use App\Services\Comercial\VisitasProgramadas\ConfirmarLlegadaVisitaProgramadaService;
use App\Services\Comercial\VisitasProgramadas\ListarVisitasProgramadasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitaProgramadaPdvMobileController extends Controller
{
    public function index(
        Request $request,
        ResuelveAlcancePdv $alcance,
        ListarVisitasProgramadasService $listar,
        BandejaVisitasProgramadasPdvController $web,
    ): JsonResponse {
        return $web->datos($request, $alcance, $listar);
    }

    public function confirmarLlegada(
        ConfirmarLlegadaVisitaProgramadaPdvRequest $request,
        VisitaClienteProgramada $visitaClienteProgramada,
        ResuelveAlcancePdv $alcance,
        ConfirmarLlegadaVisitaProgramadaService $confirmar,
        BandejaVisitasProgramadasPdvController $web,
    ): JsonResponse {
        $response = $web->confirmarLlegada($request, $visitaClienteProgramada, $alcance, $confirmar);

        if ($response instanceof JsonResponse) {
            return $response;
        }

        return response()->json(['message' => 'Llegada registrada.']);
    }
}
