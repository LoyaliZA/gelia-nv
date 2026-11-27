<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Events\ImpresionGuiasCedisActualizada;
use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ColaImpresionGuiasCedis;
use App\Services\ControlPedidos\CombinarPdfGuiasCedis;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Services\ControlPedidos\ServirArchivoPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImpresionGuiasCedisController extends Controller
{
    public function __construct(private ColaImpresionGuiasCedis $cola) {}

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tab' => 'in:pendientes,historial',
            'q' => 'nullable|string|max:100',
            'resumen' => 'boolean',
            'manifiesto' => 'boolean',
        ]);
        $guias = $this->cola->consultar($request->user());
        $pendientes = $guias->where('vigente', true)->where('abierta', false);
        $totales = [
            'pedidos' => $pendientes->pluck('pedido.id')->unique()->count(),
            'guias' => $pendientes->count(),
        ];
        if ($request->boolean('manifiesto')) {
            $ordenadas = $pendientes->sortBy([['pedido.id', 'asc'], ['orden', 'asc']]);

            return response()->json(['pendientes' => $totales + ['ids' => $ordenadas->keys()->values()]]);
        }
        if ($request->boolean('resumen')) {
            return response()->json(['pendientes' => $totales]);
        }

        $listado = ($datos['tab'] ?? 'pendientes') === 'historial'
            ? $guias->where('abierta', true)->sortByDesc('apertura_id')
            : $pendientes;

        return response()->json([
            'pedidos' => $this->cola->agrupar($listado, trim($datos['q'] ?? '')),
            'pendientes' => $totales,
        ]);
    }

    public function archivo(Request $request, int $guia, ServirArchivoPedidoService $archivos): BinaryFileResponse
    {
        $guia = $this->cola->consultar($request->user(), $guia)->get($guia);
        abort_unless($guia, 404);
        abort_unless($guia['abierta'] || $guia['vigente'], 409, 'La guía dejó de estar vigente.');
        [$disco, $ruta] = $this->cola->archivo($guia);

        $pdf = $archivos->responder($request, $disco, $ruta, $guia['nombre'], 'application/pdf');
        if ($request->boolean('preparar')) {
            $this->habilitarRegistro($pdf, $request, [$guia['id']]);
        }

        return $pdf;
    }

    public function lote(Request $request, CombinarPdfGuiasCedis $combinar): BinaryFileResponse|JsonResponse
    {
        $ids = $this->ids($request);
        $guias = $this->cola->consultar($request->user())->only($ids)->where('vigente', true)->where('abierta', false);
        abort_unless($guias->count() === count($ids), 409, 'Los pendientes cambiaron. Actualiza el lote e intenta de nuevo.');

        $archivos = [];
        foreach ($ids as $id) {
            [$disco, $ruta] = $this->cola->archivo($guias->get($id));
            abort_unless(Storage::disk($disco)->exists($ruta), 409, 'Falta el PDF de una guía. Abre las guías disponibles individualmente.');
            $archivos[] = Storage::disk($disco)->path($ruta);
        }

        try {
            $lote = $combinar->ejecutar($archivos);
        } catch (\RuntimeException $error) {
            report($error);

            return response()->json(['message' => $error->getMessage()], 503);
        }

        $pdf = response()->file($lote, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'])
            ->setContentDisposition('inline', 'guias-cedis.pdf')->deleteFileAfterSend();
        $this->habilitarRegistro($pdf, $request, $ids);

        return $pdf;
    }

    public function registrarApertura(Request $request, RegistrarHistorialPedidoService $historial): JsonResponse
    {
        $ids = $this->ids($request);
        $usuario = $request->user();
        abort_unless(URL::hasValidSignature($request, absolute: false)
            && (int) $request->query('usuario') === (int) $usuario->id
            && $request->query('lote') === hash('sha256', implode(',', $ids)), 403);
        $guias = $this->cola->consultar($usuario)->only($ids);
        abort_unless($guias->count() === count($ids), 404);
        $pedidoIds = $guias->pluck('pedido.id')->unique()->values()->all();

        DB::transaction(function () use ($usuario, $ids, $pedidoIds, $historial) {
            PedidoBma::query()->whereKey($pedidoIds)->orderBy('id')->lockForUpdate()->get(['id']);
            $guias = $this->cola->consultar($usuario, pedidoIds: $pedidoIds)->only($ids);
            abort_unless($guias->count() === count($ids), 404);

            // Una reapertura o un reintento conserva la primera apertura en Historial.
            $pendientes = $guias->where('abierta', false);
            foreach ($pendientes->groupBy('pedido.id') as $grupo) {
                $pedido = $grupo->first()['pedido'];
                $historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    'Abrió el PDF de guías desde Imprimir guías.',
                    AccionesHistorialPedidoBma::ABRIR_GUIAS_CEDIS,
                    snapshot: ['documentos' => $grupo->pluck('id')->all()],
                );
            }
            if ($pendientes->isNotEmpty()) {
                ImpresionGuiasCedisActualizada::dispatch();
            }
        });

        return response()->json(['ok' => true]);
    }

    /** @param list<int> $ids */
    private function habilitarRegistro(BinaryFileResponse $pdf, Request $request, array $ids): void
    {
        $url = URL::temporarySignedRoute('control_pedidos.cedis.impresion_guias.apertura', now()->addMinutes(5), [
            'usuario' => $request->user()->id,
            'lote' => hash('sha256', implode(',', $ids)),
        ], absolute: false);
        $pdf->headers->set('X-Gelia-Apertura', $url);
        $pdf->headers->set('Cache-Control', 'private, no-store');
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        $datos = $request->validate(['ids' => 'required|array|list|min:1', 'ids.*' => 'required|integer|min:1|distinct']);

        return array_map('intval', $datos['ids']);
    }
}
