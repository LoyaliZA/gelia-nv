<?php

namespace App\Http\Controllers\Soporte;

use App\Http\Controllers\Controller;
use App\Services\Manuales\GenerarPdfManualService;
use App\Services\Manuales\ResolverManualesVisiblesService;
use App\Support\Manuales\Content\ControlPedidosManualContent;
use App\Support\Manuales\ManualesCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ManualesController extends Controller
{
    public function __construct(
        private ResolverManualesVisiblesService $resolver,
        private GenerarPdfManualService $pdfService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($this->resolver->hubVisible($user), 403);

        return Inertia::render('Soporte/Manuales/Index', [
            'manuales' => $this->resolver->listarPara($user),
        ]);
    }

    public function show(Request $request, string $slug): InertiaResponse
    {
        $user = $request->user();
        $resolved = $this->resolver->resolverShow($slug, $user);
        abort_if($resolved === null && ManualesCatalog::porSlug($slug) === null, 404);
        abort_if($resolved === null, 403);

        $ids = array_column($resolved['secciones'], 'id');
        $contenido = match ($slug) {
            ManualesCatalog::SLUG_CONTROL_PEDIDOS => ControlPedidosManualContent::payload($ids),
            default => null,
        };
        abort_if($contenido === null, 404);

        return Inertia::render('Soporte/Manuales/Show', [
            'manual' => $resolved['manual'],
            'secciones' => $resolved['secciones'],
            'contenido' => $contenido,
            'pdf_url' => $resolved['pdf_url'],
            'seccion_inicial' => $request->query('seccion'),
        ]);
    }

    public function pdf(Request $request, string $slug): Response
    {
        $user = $request->user();
        abort_if(ManualesCatalog::porSlug($slug) === null, 404);

        $response = $this->pdfService->descargar($slug, $user);
        abort_if($response === null, 403);

        return $response;
    }
}
