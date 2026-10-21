<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Services\Productos\ArmarFichaProductoService;
use App\Services\Productos\ConsultarPresenciaProductoService;
use App\Services\Productos\Imagenes\AutorizacionImagenProducto;
use App\Services\Productos\ResolverRetornoFichaProducto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FichaProductoAlmacenController extends Controller
{
    public function __construct(
        private readonly ArmarFichaProductoService $ficha,
        private readonly ConsultarPresenciaProductoService $presencia,
        private readonly ResolverRetornoFichaProducto $retorno,
        private readonly AutorizacionImagenProducto $autorizacion,
    ) {}

    public function show(Request $request, Producto $producto): Response
    {
        $user = $request->user();
        abort_unless($user && $this->autorizacion->puedeConsultar($user), 403);

        $retorno = $this->retorno->resolver($request, $user);
        $porPagina = (int) $request->query('per_page', (int) config('productos_imagenes.presencia_por_pagina', 20));
        $consulta = $this->presencia->consultar($user, $producto, (int) $request->query('presencia_page', 1), $porPagina);
        $galeria = ProductoImagen::query()
            ->where('producto_id', $producto->id)
            ->orderBy('posicion')
            ->paginate((int) config('productos_imagenes.galeria_por_pagina', 12), ['*'], 'galeria_page')
            ->withQueryString();
        $galeria->setCollection($galeria->getCollection()->map(fn (ProductoImagen $imagen) => [
            'id' => $imagen->id,
            'posicion' => $imagen->posicion,
            'es_principal' => $imagen->esPrincipal(),
            'estado' => $imagen->estado,
            'miniatura_url' => route('gestion_interna.productos.imagenes.archivo', [
                'producto' => $producto->id,
                'imagen' => $imagen->id,
                'variante' => 'miniatura',
            ]),
            'original_url' => route('gestion_interna.productos.imagenes.archivo', [
                'producto' => $producto->id,
                'imagen' => $imagen->id,
                'variante' => 'original',
            ]),
        ]));

        return Inertia::render('Almacenes/FichaProducto/Index', [
            'maestro' => $this->ficha->datosMaestros($producto),
            'presencia' => $consulta['filas'],
            'incluye_costos' => $consulta['incluye_costos'],
            'incluye_ventas' => $consulta['incluye_ventas'],
            'galeria' => $galeria,
            'puede_gestionar_imagenes' => $this->autorizacion->puedeGestionar($user),
            'retorno' => $retorno,
        ]);
    }
}
