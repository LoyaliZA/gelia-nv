<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\GenerarEtiquetasResguardoPdvService;
use App\Services\PuntoVenta\Resguardos\ResolverEtiquetaResguardoPdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EtiquetasResguardoPdvController extends Controller
{
    public function descargar(
        Request $request,
        ResguardoPdv $resguardo,
        GenerarEtiquetasResguardoPdvService $generar,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        $bultoIds = $request->filled('bulto_ids')
            ? array_map('intval', (array) $request->input('bulto_ids'))
            : null;

        $pdf = $generar->ejecutar($resguardo, $user, $bultoIds);
        $folio = $resguardo->snapshot_folio ?: 'resguardo-'.$resguardo->id;

        return $pdf->download('etiquetas-'.$folio.'-'.now()->format('Ymd-His').'.pdf');
    }

    public function resolver(
        Request $request,
        string $codigo,
        ResolverEtiquetaResguardoPdvService $resolver,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($resolver->resolver($user, $codigo));
    }
}
