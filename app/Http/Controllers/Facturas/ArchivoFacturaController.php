<?php

namespace App\Http\Controllers\Facturas;

use App\Http\Controllers\Controller;
use App\Models\SolicitudFactura;
use App\Services\Facturas\ServirArchivoFacturaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchivoFacturaController extends Controller
{
    public function show(
        Request $request,
        SolicitudFactura $factura,
        string $tipo,
        ServirArchivoFacturaService $servirService
    ): StreamedResponse {
        if (!$servirService->puedeAcceder(Auth::user(), $factura)) {
            abort(403);
        }

        $indice = $request->integer('indice', 0);
        $archivo = $servirService->resolverArchivo($factura, $tipo, $indice);

        if (!$archivo || !Storage::disk('public')->exists($archivo['path'])) {
            abort(404);
        }

        $mime = $archivo['mime'] ?? Storage::disk('public')->mimeType($archivo['path']);
        if (!$mime || $mime === 'application/octet-stream') {
            $mime = $this->inferirMime($archivo['path'], $tipo);
        }

        $forzarDescarga = $request->boolean('descargar');

        $disposition = match (true) {
            $tipo === 'pdf' && $forzarDescarga => 'attachment',
            in_array($tipo, ['voucher', 'pdf', 'fiscal'], true) => 'inline',
            default => 'attachment',
        };

        return Storage::disk('public')->response($archivo['path'], $archivo['nombre'], [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Content-Disposition' => "{$disposition}; filename=\"{$archivo['nombre']}\"",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function inferirMime(string $path, string $tipo): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => match ($tipo) {
                'voucher' => 'application/pdf',
                'fiscal' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => 'application/octet-stream',
            },
        };
    }
}
