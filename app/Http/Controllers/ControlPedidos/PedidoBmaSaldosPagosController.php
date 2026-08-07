<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafCredito;
use App\Services\SaldosAFavor\ConsultarCuentaClienteSafService;
use App\Services\SaldosAFavor\GenerarCreditoSafService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PedidoBmaSaldosPagosController extends Controller
{
    public function cuentaCliente(Cliente $cliente, ConsultarCuentaClienteSafService $consultar): JsonResponse
    {
        $cuenta = $consultar->handle($cliente->id);

        return response()->json([
            'disponible' => $cuenta['disponible'],
            'reservado' => $cuenta['reservado'],
            'creditos_usables' => $cuenta['creditos_usables']->map(fn (SafCredito $c) => [
                'id' => $c->id,
                'folio' => $c->folio,
                'canal_origen' => $c->canal_origen,
                'monto_disponible' => (float) $c->monto_disponible,
                'fecha_vencimiento' => $c->fecha_vencimiento?->toDateString(),
                'estado_revision' => $c->estado_revision,
            ]),
        ]);
    }

    public function registrarPago(
        Request $request,
        PedidoBma $pedidoBma,
        RegistrarPagoPedidoBmaService $service,
    ): RedirectResponse {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'catalogo_banco_id' => ['nullable', 'exists:catalogo_bancos,id'],
            'forma_pago' => ['nullable', 'in:'.implode(',', PedidoBmaPago::FORMAS_PAGO)],
            'fecha_pago' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:128'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'comprobante' => ['nullable', 'file', 'max:10240'],
        ]);

        $service->handle($pedidoBma, $datos, $request->file('comprobante'), Auth::id());

        return back()->with('success', 'Exhibición de pago registrada.');
    }

    public function resumenPago(PedidoBma $pedidoBma, RegistrarPagoPedidoBmaService $service): JsonResponse
    {
        return response()->json([
            'pagos' => $pedidoBma->pagosExhibicion()->with('banco:id,nombre')->get(),
            'resumen' => $service->resumenPago($pedidoBma),
        ]);
    }

    public function generarSaldoExcedente(
        Request $request,
        PedidoBma $pedidoBma,
        RegistrarPagoPedidoBmaService $pagos,
        GenerarCreditoSafService $generar,
    ): RedirectResponse {
        if (! $pedidoBma->cliente_id) {
            return back()->with('error', 'El pedido no tiene cliente.');
        }

        $resumen = $pagos->resumenPago($pedidoBma);
        $excedente = (float) ($resumen['excedente'] ?? 0);
        if ($excedente <= 0) {
            return back()->with('error', 'No hay excedente para generar saldo a favor.');
        }

        $datos = $request->validate([
            'saf_motivo_id' => ['nullable', 'exists:saf_motivos,id'],
            'detalle_motivo' => ['nullable', 'string', 'max:2000'],
        ]);

        $motivoId = $datos['saf_motivo_id']
            ?? \App\Models\SaldosAFavor\SafMotivo::where('codigo', 'pago_de_mas')->value('id');

        try {
            $credito = $generar->handle([
                'cliente_id' => $pedidoBma->cliente_id,
                'monto' => $excedente,
                'saf_motivo_id' => $motivoId,
                'detalle_motivo' => $datos['detalle_motivo'] ?? 'Excedente de pagos del pedido',
                'canal_origen' => 'bellaroma',
                'pedido_bma_id' => $pedidoBma->id,
                'documento_origen' => $pedidoBma->folio_remision ?: $pedidoBma->folio,
                'generado_por_id' => Auth::id(),
                'origen_manual' => false,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Saldo {$credito->folio} generado por excedente ({$excedente}).");
    }

    public function revisarPago(Request $request, PedidoBmaPago $pago): RedirectResponse
    {
        $datos = $request->validate([
            'estado_revision' => ['required', 'in:pendiente,confirmado,con_diferencia'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $pago->update([
            'estado_revision' => $datos['estado_revision'],
            'observaciones' => $datos['observaciones'] ?? $pago->observaciones,
            'revisado_por_id' => Auth::id(),
            'revisado_at' => now(),
        ]);

        return back()->with('success', 'Exhibición actualizada.');
    }
}
