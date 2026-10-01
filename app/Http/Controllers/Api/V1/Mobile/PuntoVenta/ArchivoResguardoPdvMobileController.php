<?php

namespace App\Http\Controllers\Api\V1\Mobile\PuntoVenta;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\User;
use App\Support\PuntoVenta\Resguardos\AutorizacionConsultaResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchivoResguardoPdvMobileController extends Controller
{
    public function evidencia(
        Request $request,
        ResguardoPdv $resguardo,
        ResguardoPdvEvidencia $evidencia,
        ResuelveAlcancePdv $alcance,
        AutorizacionConsultaResguardoPdv $autorizacion,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $this->asegurarResguardo($user, $resguardo, $alcance, $autorizacion);

        if ((int) $evidencia->resguardo_id !== (int) $resguardo->id) {
            abort(404);
        }

        return $this->responderArchivo('local', $evidencia->ruta_interna, $evidencia->nombre_original, $evidencia->mime_type);
    }

    public function documento(
        Request $request,
        ResguardoPdv $resguardo,
        PedidoBmaDocumento $documento,
        ResuelveAlcancePdv $alcance,
        AutorizacionConsultaResguardoPdv $autorizacion,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $this->asegurarResguardo($user, $resguardo, $alcance, $autorizacion);

        if ((int) $resguardo->pedido_bma_id !== (int) $documento->pedido_bma_id) {
            abort(404);
        }

        return $this->responderArchivo('public', $documento->ruta_archivo, $documento->nombre_original, $documento->mime_type);
    }

    private function asegurarResguardo(
        User $user,
        ResguardoPdv $resguardo,
        ResuelveAlcancePdv $alcance,
        AutorizacionConsultaResguardoPdv $autorizacion,
    ): void {
        $autorizacion->asegurarDetalleResguardo($user, $resguardo);

        $activaId = $alcance->sucursalActivaId($user);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }
    }

    private function responderArchivo(string $discoNombre, ?string $ruta, ?string $nombreOriginal, ?string $mime): StreamedResponse
    {
        $disco = Storage::disk($discoNombre);
        if ($ruta === null || $ruta === '' || ! $disco->exists($ruta)) {
            abort(404);
        }

        $nombre = $this->nombreSeguro($nombreOriginal ?: basename($ruta));

        return $disco->response($ruta, $nombre, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
        ]);
    }

    private function nombreSeguro(string $nombre): string
    {
        $limpio = preg_replace('/[\r\n"]/', '', $nombre) ?? 'archivo';

        return $limpio !== '' ? $limpio : 'archivo';
    }
}
