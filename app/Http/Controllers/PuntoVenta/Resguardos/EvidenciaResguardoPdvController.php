<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\User;
use App\Support\PuntoVenta\Resguardos\AutorizacionConsultaResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenciaResguardoPdvController extends Controller
{
    public function show(
        Request $request,
        ResguardoPdv $resguardo,
        ResguardoPdvEvidencia $evidencia,
        ResuelveAlcancePdv $alcance,
        AutorizacionConsultaResguardoPdv $autorizacion,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();

        $this->asegurarAcceso($user, $resguardo, $evidencia, $alcance, $autorizacion);

        if ($evidencia->tipo === ResguardoPdvEvidencia::TIPO_FIRMA) {
            abort(404);
        }

        $disco = Storage::disk('local');
        if (! $disco->exists($evidencia->ruta_interna)) {
            abort(404);
        }

        $nombre = $evidencia->nombre_original ?: basename($evidencia->ruta_interna);
        $mime = $evidencia->mime_type ?: 'application/octet-stream';

        return $disco->response($evidencia->ruta_interna, $nombre, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$this->nombreSeguroDescarga($nombre).'"',
        ]);
    }

    private function asegurarAcceso(
        User $user,
        ResguardoPdv $resguardo,
        ResguardoPdvEvidencia $evidencia,
        ResuelveAlcancePdv $alcance,
        AutorizacionConsultaResguardoPdv $autorizacion,
    ): void {
        if ((int) $evidencia->resguardo_id !== (int) $resguardo->id) {
            abort(404);
        }

        $autorizacion->asegurarDetalleResguardo($user, $resguardo);

        $activaId = $alcance->sucursalActivaId($user);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdvEvidencia::class, [$evidencia->id]);
        }
    }

    private function nombreSeguroDescarga(string $nombre): string
    {
        $limpio = preg_replace('/[\r\n"]/', '', $nombre) ?? 'evidencia';

        return $limpio !== '' ? $limpio : 'evidencia';
    }
}
