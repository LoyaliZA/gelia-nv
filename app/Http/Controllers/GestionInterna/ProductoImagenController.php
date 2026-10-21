<?php

namespace App\Http\Controllers\GestionInterna;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoImagenLote;
use App\Services\Productos\Imagenes\AlmacenArchivoImagenProducto;
use App\Services\Productos\Imagenes\AutorizacionImagenProducto;
use App\Services\Productos\Imagenes\GestionarGaleriaProductoService;
use App\Services\Productos\Imagenes\GestionarLoteImagenProductoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductoImagenController extends Controller
{
    public function __construct(
        private readonly AutorizacionImagenProducto $autorizacion,
        private readonly GestionarGaleriaProductoService $galeria,
        private readonly GestionarLoteImagenProductoService $lotes,
        private readonly AlmacenArchivoImagenProducto $archivos,
    ) {}

    public function archivo(Request $request, Producto $producto, ProductoImagen $imagen): StreamedResponse
    {
        abort_unless($this->autorizacion->puedeConsultar($request->user()), 403);
        $this->pertenece($producto, $imagen);
        $variante = $request->query('variante', 'miniatura');
        $clave = $variante === 'original'
            ? $imagen->clave_original
            : ($imagen->clave_miniatura ?: $imagen->clave_original);
        abort_unless(is_string($clave) && $this->archivos->claveDelProducto($clave, $producto->id), 404);

        return $this->archivos->responder($clave);
    }

    public function store(Request $request, Producto $producto): JsonResponse
    {
        $this->autorizarGestion($request);
        $archivo = $request->file('archivo');
        abort_unless($archivo !== null, 422);
        $imagen = $this->galeria->agregar($producto, $archivo);

        return response()->json([
            'ok' => true,
            'imagen_id' => $imagen->id,
            'estado' => $imagen->estado,
        ]);
    }

    public function reemplazar(Request $request, Producto $producto, ProductoImagen $imagen): JsonResponse
    {
        $this->autorizarGestion($request);
        $this->pertenece($producto, $imagen);
        $archivo = $request->file('archivo');
        abort_unless($archivo !== null, 422);
        $actualizada = $this->galeria->reemplazar($producto, $imagen, $archivo);

        return response()->json([
            'ok' => true,
            'imagen_id' => $actualizada->id,
            'estado' => $actualizada->estado,
        ]);
    }

    public function principal(Request $request, Producto $producto, ProductoImagen $imagen): JsonResponse
    {
        $this->autorizarGestion($request);
        $this->galeria->marcarPrincipal($producto, $imagen);

        return response()->json(['ok' => true]);
    }

    public function mover(Request $request, Producto $producto, ProductoImagen $imagen): JsonResponse
    {
        $this->autorizarGestion($request);
        $direccion = $request->input('direccion');
        abort_unless(in_array($direccion, ['arriba', 'abajo'], true), 422);
        $this->galeria->mover($producto, $imagen, $direccion);

        return response()->json(['ok' => true]);
    }

    public function orden(Request $request, Producto $producto): JsonResponse
    {
        $this->autorizarGestion($request);
        $orden = $request->input('orden', []);
        abort_unless(is_array($orden), 422);
        $this->galeria->reordenar($producto, $orden);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Producto $producto, ProductoImagen $imagen): JsonResponse
    {
        $this->autorizarGestion($request);
        $this->galeria->desvincular($producto, $imagen);

        return response()->json(['ok' => true]);
    }

    public function miniatura(Request $request, Producto $producto, ProductoImagen $imagen): JsonResponse
    {
        $this->autorizarGestion($request);
        $actualizada = $this->galeria->reintentarMiniatura($producto, $imagen);

        return response()->json([
            'ok' => true,
            'estado' => $actualizada->estado,
        ]);
    }

    public function analizar(Request $request): JsonResponse
    {
        $this->autorizarGestion($request);
        $archivos = $request->file('archivos', []);
        abort_unless(is_array($archivos) && $archivos !== [], 422);
        $lote = $this->lotes->analizar($request->user(), array_values($archivos));

        return response()->json(['lote' => $this->lotes->presentar($lote)]);
    }

    public function mostrar(Request $request, ProductoImagenLote $lote): JsonResponse
    {
        $this->autorizarGestion($request);

        return response()->json(['lote' => $this->lotes->presentar($lote)]);
    }

    public function confirmar(Request $request, ProductoImagenLote $lote): JsonResponse
    {
        $this->autorizarGestion($request);
        $seleccion = $request->input('archivos', []);
        abort_unless(is_array($seleccion), 422);

        return response()->json([
            'archivos' => $this->lotes->confirmar($lote, $seleccion),
        ]);
    }

    private function autorizarGestion(Request $request): void
    {
        abort_unless($this->autorizacion->puedeGestionar($request->user()), 403);
    }

    private function pertenece(Producto $producto, ProductoImagen $imagen): void
    {
        abort_unless((int) $imagen->producto_id === (int) $producto->id, 404);
    }
}
