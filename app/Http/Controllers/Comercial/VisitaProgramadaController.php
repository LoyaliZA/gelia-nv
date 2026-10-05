<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comercial\BuscarClienteVisitaProgramadaRequest;
use App\Http\Requests\Comercial\RegistrarVisitaProgramadaRequest;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Comercial\VisitasProgramadas\ListarVisitasProgramadasService;
use App\Services\Comercial\VisitasProgramadas\RegistrarVisitaProgramadaService;
use App\Services\Comercial\VisitasProgramadas\SerializarVisitaProgramadaService;
use App\Services\Mobile\MobileClienteAlcanceService;
use App\Support\Comercial\VisitaProgramadaCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitaProgramadaController extends Controller
{
    public function index(
        Request $request,
        ListarVisitasProgramadasService $listar,
        SerializarVisitaProgramadaService $serializar,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $ahora = now();
        $vista = $request->string('vista')->toString() === 'historial' ? 'historial' : 'vigentes';

        $paginado = $listar->paginarParaRegistrador($user, $vista, $ahora);

        $visitas = $paginado->through(
            fn ($visita) => $serializar->item($visita, $ahora)
        );

        return Inertia::render('Comercial/VisitasProgramadas/Index', [
            'vista' => $vista,
            'visitas' => $visitas,
            'sucursales' => Sucursal::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'intenciones' => VisitaProgramadaCatalogo::intenciones(),
            'fecha_hoy' => $ahora->toDateString(),
        ]);
    }

    public function store(
        RegistrarVisitaProgramadaRequest $request,
        RegistrarVisitaProgramadaService $registrar,
    ): RedirectResponse {
        $registrar->handle($request->user(), $request->validated());

        return redirect()
            ->route('visitas_programadas.index')
            ->with('success', 'Visita programada registrada.');
    }

    public function buscarCliente(
        BuscarClienteVisitaProgramadaRequest $request,
        MobileClienteAlcanceService $alcance,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $modo = $request->string('modo')->toString();
        $termino = trim($request->string('q')->toString());

        $query = $alcance->queryPara($user)->limit(5);

        if ($modo === 'numero') {
            $query->where('numero_cliente', 'like', '%'.$termino.'%');
        } else {
            $query->where(function ($inner) use ($termino) {
                $inner->where('nombre', 'like', '%'.$termino.'%')
                    ->orWhere('nombre_razon_social', 'like', '%'.$termino.'%');
            });
        }

        $resultados = $query->get(['id', 'numero_cliente', 'nombre', 'nombre_razon_social'])
            ->map(static fn ($cliente) => [
                'id' => $cliente->id,
                'numero_cliente' => $cliente->numero_cliente,
                'nombre' => $cliente->nombre ?: $cliente->nombre_razon_social,
            ])
            ->values();

        return response()->json(['data' => $resultados]);
    }
}
