<?php

namespace App\Services\ApiExterna;

use Barryvdh\DomPDF\Facade\Pdf;

class ApiDocumentacionPdfService
{
    public function __construct(
        protected ApiDocumentacionService $documentacionService
    ) {}

    public function generar()
    {
        $datos = $this->documentacionService->construirDatos();

        return Pdf::loadView('api.documentacion', $datos)
            ->setPaper('a4');
    }
}
