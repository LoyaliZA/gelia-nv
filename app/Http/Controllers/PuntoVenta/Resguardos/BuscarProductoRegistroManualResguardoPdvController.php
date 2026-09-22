<?php

namespace App\Http\Controllers\PuntoVenta\Resguardos;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BuscarProductoRegistroManualResguardoPdvController extends Controller
{
    public function __invoke(
        Request $request,
        RegistroManualResguardoPdvConfig $config,
    ): JsonResponse {
        if (! $config->estaActivo()) {
            throw new AccessDeniedHttpException('El registro manual de resguardos no está habilitado.');
        }

        $termino = trim((string) $request->query('q', ''));
        if (mb_strlen($termino) < 2) {
            return response()->json(['data' => []]);
        }

        $productos = Producto::query()
            ->where('activo', true)
            ->buscarPorTexto($termino)
            ->orderBy('descripcion')
            ->limit(25)
            ->get(['id', 'sku', 'descripcion', 'folio', 'codigo_barras']);

        return response()->json([
            'data' => $productos->map(static fn (Producto $producto): array => [
                'id' => $producto->id,
                'sku' => $producto->sku,
                'descripcion' => $producto->descripcion,
                'folio' => $producto->folio,
                'codigo_barras' => $producto->codigo_barras,
            ])->values()->all(),
        ]);
    }
}
