<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Events\ImpresionGuiasCedisActualizada;
use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\ImpresionGuiaCedis;
use App\Services\ControlPedidos\ColaImpresionGuiasCedis;
use App\Services\ControlPedidos\CombinarPdfGuiasCedis;
use App\Services\ControlPedidos\ServirArchivoPedidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImpresionGuiasCedisController extends Controller
{
    public function __construct(private ColaImpresionGuiasCedis $cola) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(config('impresion_guias_cedis.activa'), 404);
        $datos = $request->validate([
            'tab' => 'in:pendientes,historial',
            'q' => 'nullable|string|max:100',
            'resumen' => 'boolean',
            'manifiesto' => 'boolean',
        ]);
        $usuario = $request->user();
        $base = $this->cola->consulta($usuario);
        $pendientes = (clone $base)->whereNull('impresion_confirmada_at')->latest('comunicada_at')->get()
            ->filter(fn (ImpresionGuiaCedis $guia) => $this->cola->vigente($guia) && $this->cola->visible($usuario, $guia));
        $totales = [
            'pedidos' => $pendientes->pluck('pedido_bma_id')->unique()->count(),
            'guias' => $pendientes->count(),
        ];
        if ($request->boolean('manifiesto')) {
            $ordenadas = $pendientes->sortBy([['pedido_bma_id', 'asc'], ['orden_caja', 'asc']])->values();

            return response()->json(['pendientes' => $totales + ['ids' => $ordenadas->modelKeys()]]);
        }
        if ($request->boolean('resumen')) {
            return response()->json(['pendientes' => $totales]);
        }

        $guias = ($datos['tab'] ?? 'pendientes') === 'historial'
            ? $base->whereNotNull('impresion_confirmada_at')->latest('impresion_confirmada_at')->get()
                ->filter(fn (ImpresionGuiaCedis $guia) => $this->cola->visible($usuario, $guia))
            : $pendientes;

        return response()->json([
            'pedidos' => $this->cola->agrupar($guias, trim($datos['q'] ?? '')),
            'pendientes' => $totales,
        ]);
    }

    public function archivo(Request $request, int $guia, ServirArchivoPedidoService $archivos): BinaryFileResponse
    {
        abort_unless(config('impresion_guias_cedis.activa'), 404);
        $guia = $this->cola->consulta($request->user())->findOrFail($guia);
        abort_unless($this->cola->visible($request->user(), $guia), 404);
        abort_unless($guia->impresion_confirmada_at || $this->cola->vigente($guia), 409, 'La guía dejó de estar vigente.');
        [$disco, $ruta] = $this->cola->archivo($guia);

        return $archivos->responder($request, $disco, $ruta, $guia->nombre_pdf, 'application/pdf');
    }

    public function lote(Request $request, CombinarPdfGuiasCedis $combinar): BinaryFileResponse|JsonResponse
    {
        abort_unless(config('impresion_guias_cedis.activa'), 404);
        $ids = $this->ids($request);
        $usuario = $request->user();
        $guias = $this->cola->consulta($usuario)->whereKey($ids)->whereNull('impresion_confirmada_at')->get()->keyBy('id');
        abort_unless($guias->count() === count($ids), 409, 'Los pendientes cambiaron. Actualice el lote e intente nuevamente.');
        $archivos = [];
        foreach ($ids as $id) {
            $guia = $guias->get($id);
            abort_unless($this->cola->visible($usuario, $guia) && $this->cola->vigente($guia), 409, 'Los pendientes cambiaron. Actualice el lote e intente nuevamente.');
            [$disco, $ruta] = $this->cola->archivo($guia);
            abort_unless(Storage::disk($disco)->exists($ruta), 409, 'Falta el PDF de una guía. Imprima las guías disponibles individualmente.');
            $archivos[] = Storage::disk($disco)->path($ruta);
        }

        try {
            $lote = $combinar->ejecutar($archivos);
        } catch (\RuntimeException $error) {
            report($error);

            return response()->json(['message' => $error->getMessage()], 503);
        }

        return response()->file($lote, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'])
            ->setContentDisposition('inline', 'guias-cedis.pdf')->deleteFileAfterSend();
    }

    public function confirmar(Request $request): JsonResponse
    {
        abort_unless(config('impresion_guias_cedis.activa'), 404);
        $ids = $this->ids($request);
        $usuario = $request->user();
        DB::transaction(function () use ($ids, $usuario) {
            $guias = $this->cola->consulta($usuario)->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            abort_unless($guias->count() === count($ids), 404);
            foreach ($guias as $guia) {
                abort_unless($this->cola->visible($usuario, $guia), 404);
                abort_unless($guia->impresion_confirmada_at || $this->cola->vigente($guia), 409, 'Los pendientes cambiaron. Revise las guías antes de confirmar.');
            }
            $confirmadas = ImpresionGuiaCedis::query()->whereKey($ids)->whereNull('impresion_confirmada_at')->update([
                'impresion_confirmada_at' => now(),
                'impresion_confirmada_por_id' => $usuario->id,
            ]);
            if ($confirmadas > 0) {
                ImpresionGuiasCedisActualizada::dispatch();
            }
        });

        return response()->json(['ok' => true]);
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        $datos = $request->validate(['ids' => 'required|array|list|min:1', 'ids.*' => 'required|integer|min:1|distinct']);

        return array_map('intval', $datos['ids']);
    }
}
