<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\SafCredito;
use App\Services\SaldosAFavor\ActualizarPagoPedidoBmaService;
use App\Services\SaldosAFavor\ConsultarCuentaClienteSafService;
use App\Services\SaldosAFavor\EliminarPagoPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Services\SaldosAFavor\RevisarPagoPedidoBmaService;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use App\Support\SaldosAFavor\AlcanceSaf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class PedidoBmaSaldosPagosController extends Controller
{
    public function cuentaCliente(Request $request, Cliente $cliente, ConsultarCuentaClienteSafService $consultar): JsonResponse
    {
        $alcance = [];
        $pedidoId = $request->integer('pedido_id');
        if ($pedidoId > 0) {
            $pedido = PedidoBma::with(['almacen.sucursal', 'vendedor.departamento'])->find($pedidoId);
            if ($pedido) {
                VisibilidadPedidoBma::assertPuedeConsultar(Auth::user(), $pedido);
                $alcance = AlcanceSaf::desdePedido($pedido);
            }
        } elseif ($request->filled('almacen_id')) {
            $alcance = AlcanceSaf::desdeAlmacenYUsuario($request->integer('almacen_id'), Auth::user());
        }

        $cuenta = $consultar->handle($cliente->id, 'MXN', $alcance);

        return response()->json([
            'disponible' => $cuenta['disponible'],
            'reservado' => $cuenta['reservado'],
            'creditos_usables' => $cuenta['creditos_usables']->map(fn (SafCredito $c) => [
                'id' => $c->id,
                'folio' => $c->folio,
                'canal_origen' => $c->canal_origen,
                'sucursal' => $c->sucursal,
                'departamento' => $c->departamento,
                'pedido_bma_id' => $c->pedido_bma_id,
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
        VisibilidadPedidoBma::assertPuedeMutarComoVendedora(Auth::user(), $pedidoBma);
        $forma = $request->input('forma_pago');
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'catalogo_banco_id' => [
                Rule::requiredIf(fn () => PedidoBmaPago::formaRequiereBanco($forma)),
                'nullable',
                'exists:catalogo_bancos,id',
            ],
            'forma_pago' => ['nullable', 'in:'.implode(',', PedidoBmaPago::FORMAS_PAGO)],
            'fecha_pago' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:128'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'comprobante' => ['required', 'file', 'max:10240'],
        ]);

        try {
            $service->handle($pedidoBma, $datos, $request->file('comprobante'), Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Exhibición de pago registrada.');
    }

    public function actualizarPago(
        Request $request,
        PedidoBmaPago $pago,
        ActualizarPagoPedidoBmaService $service,
    ): RedirectResponse {
        $this->assertPuedeMutarPago($pago);
        $forma = $request->input('forma_pago', $pago->forma_pago);
        $datos = $request->validate([
            'monto' => ['sometimes', 'numeric', 'min:0.01'],
            'catalogo_banco_id' => [
                Rule::requiredIf(fn () => PedidoBmaPago::formaRequiereBanco($forma)),
                'nullable',
                'exists:catalogo_bancos,id',
            ],
            'forma_pago' => ['sometimes', 'nullable', 'in:'.implode(',', PedidoBmaPago::FORMAS_PAGO)],
            'fecha_pago' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:128'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'comprobante' => ['nullable', 'file', 'max:10240'],
        ]);

        try {
            $service->handle($pago, $datos, $request->file('comprobante'), Auth::id());
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Exhibición de pago actualizada.');
    }

    public function eliminarPago(
        PedidoBmaPago $pago,
        EliminarPagoPedidoBmaService $service,
    ): RedirectResponse {
        $this->assertPuedeMutarPago($pago);
        try {
            $service->handle($pago, Auth::id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Exhibición de pago eliminada.');
    }

    public function resumenPago(PedidoBma $pedidoBma, RegistrarPagoPedidoBmaService $service): JsonResponse
    {
        VisibilidadPedidoBma::assertPuedeConsultar(Auth::user(), $pedidoBma);
        $pedidoBma->load(['pagosExhibicion.banco', 'banco']);

        return response()->json([
            'pagos' => $pedidoBma->pagosExhibicion,
            'resumen' => $service->resumenPago($pedidoBma),
            'formas_pago' => PedidoBmaPago::formasPagoCatalogo(),
        ]);
    }

    public function generarSaldoExcedente(
        PedidoBma $pedidoBma,
        RegistrarPagoPedidoBmaService $pagos,
    ): RedirectResponse {
        VisibilidadPedidoBma::assertPuedeMutarComoVendedora(Auth::user(), $pedidoBma);
        if (! $pedidoBma->cliente_id) {
            return back()->with('error', 'El pedido no tiene cliente.');
        }

        $credito = $pagos->generarExcedenteSiAplica($pedidoBma, Auth::id());
        if (! $credito) {
            $resumen = $pagos->resumenPago($pedidoBma);
            if ((float) ($resumen['excedente'] ?? 0) <= 0) {
                return back()->with('error', 'No hay excedente de este pedido para generar saldo a favor.');
            }

            return back()->with('success', 'El saldo a favor por excedente ya estaba registrado.');
        }

        return back()->with(
            'success',
            "Saldo {$credito->folio} generado por excedente de este pedido ({$credito->monto_original}). Disponible a partir del siguiente pedido."
        );
    }

    public function revisarPago(
        Request $request,
        PedidoBmaPago $pago,
        RevisarPagoPedidoBmaService $service,
    ): RedirectResponse {
        $pago->loadMissing('pedido');
        if ($pago->pedido) {
            VisibilidadPedidoBma::assertPuedeConsultar(Auth::user(), $pago->pedido);
        }
        $datos = $request->validate([
            'estado_revision' => ['required', 'in:'.implode(',', PedidoBmaPago::ESTADOS_REVISION)],
            'observaciones' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:estado_revision,'.PedidoBmaPago::REVISION_CON_OBSERVACIONES,
                'required_if:estado_revision,'.PedidoBmaPago::REVISION_RECHAZADO,
            ],
        ]);

        try {
            $service->handle(
                $pago,
                $datos['estado_revision'],
                Auth::id(),
                $datos['observaciones'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Exhibición actualizada.');
    }

    private function assertPuedeMutarPago(PedidoBmaPago $pago): void
    {
        $pago->loadMissing('pedido');
        if (! $pago->pedido) {
            abort(404);
        }
        VisibilidadPedidoBma::assertPuedeMutarComoVendedora(Auth::user(), $pago->pedido);
    }
}
