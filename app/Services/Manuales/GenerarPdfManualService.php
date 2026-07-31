<?php

namespace App\Services\Manuales;

use App\Support\Manuales\Content\ControlPedidosManualContent;
use App\Support\Manuales\ManualesCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Symfony\Component\HttpFoundation\Response;

class GenerarPdfManualService
{
    public function __construct(
        private ResolverManualesVisiblesService $resolver,
    ) {}

    /**
     * Datos que se pasan a la vista PDF (también útiles en tests).
     *
     * @return array{slug: string, titulo: string, modulo: string, secciones_ids: list<string>, contenido: array<string, mixed>, fecha: string, cargos: list<string>}|null
     */
    public function payload(string $slug, Authorizable $user): ?array
    {
        $resolved = $this->resolver->resolverShow($slug, $user);
        if (! $resolved) {
            return null;
        }

        $ids = array_column($resolved['secciones'], 'id');
        $contenido = $this->contenidoPara($slug, $ids);
        if ($contenido === null) {
            return null;
        }

        return [
            'slug' => $slug,
            'titulo' => $resolved['manual']['titulo'],
            'modulo' => $resolved['manual']['modulo'],
            'secciones_ids' => $ids,
            'cargos' => array_column($resolved['secciones'], 'cargo'),
            'contenido' => $contenido,
            'fecha' => now()->format('d/m/Y H:i'),
        ];
    }

    public function descargar(string $slug, Authorizable $user): ?Response
    {
        $data = $this->payload($slug, $user);
        if (! $data) {
            return null;
        }

        $view = $this->vistaPara($slug);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4');

        $nombre = 'Manual_'.$slug.'_'.now()->format('Ymd').'.pdf';

        return $pdf->download($nombre);
    }

    /** @param  list<string>  $ids */
    private function contenidoPara(string $slug, array $ids): ?array
    {
        return match ($slug) {
            ManualesCatalog::SLUG_CONTROL_PEDIDOS => ControlPedidosManualContent::payload($ids),
            default => null,
        };
    }

    private function vistaPara(string $slug): string
    {
        return match ($slug) {
            ManualesCatalog::SLUG_CONTROL_PEDIDOS => 'manuales.control_pedidos',
            default => 'manuales.control_pedidos',
        };
    }
}
