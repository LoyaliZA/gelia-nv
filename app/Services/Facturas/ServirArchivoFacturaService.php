<?php

namespace App\Services\Facturas;

use App\Models\SolicitudFactura;
use App\Models\User;

class ServirArchivoFacturaService
{
    public function __construct(
        private ListarSolicitudesFacturaService $listarService
    ) {}

    public function puedeAcceder(User $usuario, SolicitudFactura $solicitud): bool
    {
        return $this->listarService->usuarioPuedeVer($usuario, $solicitud);
    }

    public function resolverArchivo(SolicitudFactura $solicitud, string $tipo, ?int $indice = null): ?array
    {
        return match ($tipo) {
            'fiscal' => $this->archivoSimple(
                $solicitud->archivo_fiscal_path,
                $this->nombreFiscal($solicitud->archivo_fiscal_path)
            ),
            'pdf' => $this->archivoSimple($solicitud->factura_pdf_path, $solicitud->factura_pdf_nombre ?? 'factura.pdf'),
            'xml' => $this->archivoSimple($solicitud->factura_xml_path, $solicitud->factura_xml_nombre ?? 'factura.xml'),
            'evidencia_error' => $this->archivoSimple($solicitud->evidencia_error_path, 'evidencia-error'),
            'voucher' => $this->resolverVoucher($solicitud, $indice ?? 0),
            default => null,
        };
    }

    private function nombreFiscal(?string $path): string
    {
        if (!$path) {
            return 'datos-fiscales.xlsx';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return 'datos-fiscales.' . ($ext ?: 'xlsx');
    }

    private function archivoSimple(?string $path, string $nombreDefault): ?array
    {
        if (!$path) {
            return null;
        }

        return [
            'path' => $path,
            'nombre' => basename($path) ?: $nombreDefault,
        ];
    }

    private function resolverVoucher(SolicitudFactura $solicitud, int $indice): ?array
    {
        $voucher = $solicitud->vouchers()->orderBy('orden')->skip($indice)->first();
        if (!$voucher) {
            return null;
        }

        $mime = $voucher->mime;
        if (!$mime && $voucher->path) {
            $mime = str_ends_with(strtolower($voucher->path), '.pdf')
                ? 'application/pdf'
                : 'image/webp';
        }

        return [
            'path' => $voucher->path,
            'nombre' => $voucher->nombre_original,
            'mime' => $mime,
        ];
    }
}
