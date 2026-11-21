<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Exceptions\ControlPedidos\CaratulaMunicipalException;
use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Services\ControlPedidos\Municipal\GenerarCaratulaMunicipalService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Carátula del envío municipal generada desde el pedido, para la sucursal que lo recibe. */
class CaratulaMunicipalController extends Controller
{
    public function generar(PedidoBma $pedidoBma, GenerarCaratulaMunicipalService $service): RedirectResponse
    {
        try {
            $service->ejecutar($pedidoBma, Auth::user());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->mensaje($e));
        }

        return redirect()->back()->with('success', 'Carátula generada.');
    }

    public function regenerar(PedidoBma $pedidoBma, Request $request, GenerarCaratulaMunicipalService $service): RedirectResponse
    {
        $datos = $request->validate(['motivo_regeneracion' => ['required', 'string', 'max:500']]);

        try {
            $service->ejecutar($pedidoBma, Auth::user(), $datos['motivo_regeneracion']);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->mensaje($e))->withInput();
        }

        return redirect()->back()->with('success', 'Carátula regenerada.');
    }

    public function descargar(PedidoBma $pedidoBma, PedidoBmaCaratula $caratula, RegistrarHistorialPedidoService $historial): Response
    {
        $usuario = Auth::user();
        abort_unless($pedidoBma->sucursal_destino_id && $usuario->idsSucursalesOperables()->contains((int) $pedidoBma->sucursal_destino_id), 403);
        abort_unless((int) $caratula->pedido_bma_id === (int) $pedidoBma->id && $caratula->pedido_bma_tarea_preparacion_id === null, 404);
        abort_unless($caratula->ruta_pdf && Storage::disk('local')->exists($caratula->ruta_pdf), 404);

        $pedidoBma->loadMissing('estatus');
        if ($pedidoBma->estatus) {
            $historial->ejecutar($pedidoBma->id, $usuario->id, $pedidoBma->estatus->id, $pedidoBma->estatus->id,
                "Descarga carátula v{$caratula->version}.", AccionesHistorialPedidoBma::DESCARGA_CARATULA);
        }

        return response(Storage::disk('local')->get($caratula->ruta_pdf), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="caratula-v'.$caratula->version.'.pdf"',
        ]);
    }

    /** Solo los rechazos de negocio llegan al usuario; cualquier otra falla se registra y se resume. */
    private function mensaje(\Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return (string) collect($e->errors())->flatten()->first();
        }
        if ($e instanceof CaratulaMunicipalException) {
            return $e->getMessage();
        }
        report($e);

        return 'No se pudo generar la carátula. Intente de nuevo; si continúa, avise a Sistemas.';
    }
}
