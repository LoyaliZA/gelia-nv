<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CatalogoProceso;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoTipoCliente; // <-- NUEVO
use App\Models\Cliente;
use App\Models\Departamento; // <-- NUEVO
use App\Models\Area;         // <-- NUEVO
use App\Models\CatalogoZonaEntrega; // <-- NUEVO
use App\Models\CatalogoHorarioEntrega;
use App\Models\CatalogoPorcentajeEscalonamientoLista;
use App\Models\CatalogoPorcentajeListadoLista;
use App\Models\CatalogoBanco;
use App\Models\Sucursal;
use App\Models\CatalogoTipoAlmacen;
use App\Models\CatalogoMarcaProducto;
use App\Models\Almacen;
use App\Models\CatalogoCategoriaProducto;
use Illuminate\Support\Facades\DB;
use App\Services\Catalogos\ImportarCatalogoAlmacenService;
use App\Services\Catalogos\PlantillaImportacionCatalogoService;
use App\Services\Almacenes\RegistrarAuditoriaAlmacenService;
use App\Services\Almacenes\NormalizarTextoImportacionService;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoAlmacenSalida;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\CatalogoTipoGuiaPedido;
use App\Models\ControlPedidos\CatalogoEnvioTienda;
use App\Models\ControlPedidos\PedidoBma;

class CatalogoController extends Controller
{
    // --- 1. CATÁLOGO DE PROCESOS ---
    public function storeProceso(Request $request) {
        CatalogoProceso::create($request->validate(['nombre' => 'required', 'descripcion' => 'nullable', 'activo' => 'boolean']));
        return back()->with('success', 'Proceso creado correctamente.');
    }

    public function updateProceso(Request $request, $id) {
        CatalogoProceso::findOrFail($id)->update($request->validate(['nombre' => 'required', 'descripcion' => 'nullable', 'activo' => 'boolean']));
        return back()->with('success', 'Proceso actualizado.');
    }

    public function destroyProceso($id) {
        CatalogoProceso::findOrFail($id)->delete();
        return back()->with('success', 'Proceso eliminado.');
    }

    // --- 2. CATÁLOGO DE ESTADOS ---
    public function storeEstado(Request $request) {
        CatalogoEstadoSolicitud::create($request->validate(['nombre' => 'required', 'descripcion' => 'nullable', 'activo' => 'boolean']));
        return back()->with('success', 'Estado creado correctamente.');
    }

    public function updateEstado(Request $request, $id) {
        CatalogoEstadoSolicitud::findOrFail($id)->update($request->validate(['nombre' => 'required', 'descripcion' => 'nullable', 'activo' => 'boolean']));
        return back()->with('success', 'Estado actualizado.');
    }

    public function destroyEstado($id) {
        CatalogoEstadoSolicitud::findOrFail($id)->delete();
        return back()->with('success', 'Estado eliminado.');
    }

    // --- 3. CATÁLOGO DE LISTAS DE DESCUENTO (Con Revinculación) ---
    public function storeLista(Request $request) {
        CatalogoListaDescuento::create($request->validate(['nombre' => 'required', 'monto_requerido' => 'required|numeric', 'porcentaje_descuento' => 'required|numeric|min:0|max:100', 'activo' => 'boolean']));
        return back()->with('success', 'Lista creada correctamente.');
    }

    public function updateLista(Request $request, $id) {
        CatalogoListaDescuento::findOrFail($id)->update($request->validate(['nombre' => 'required', 'monto_requerido' => 'required|numeric', 'porcentaje_descuento' => 'required|numeric|min:0|max:100', 'activo' => 'boolean']));
        return back()->with('success', 'Lista actualizada.');
    }

    public function destroyLista(Request $request, $id) {
        $lista = CatalogoListaDescuento::findOrFail($id);
        
        // Si el usuario especificó a qué lista revincular a los clientes, los movemos primero.
        if ($request->has('reubicar_en_id') && $request->reubicar_en_id) {
            Cliente::where('lista_actual_id', $id)->update(['lista_actual_id' => $request->reubicar_en_id]);
        }

        $lista->delete();
        return back()->with('success', 'Lista eliminada y clientes revinculados exitosamente.');
    }

    // --- 4. CATÁLOGO DE DEPARTAMENTOS ---
    public function storeDepartamento(Request $request) {
        Departamento::create($request->validate([
            'nombre' => 'required|string|max:255', 
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Departamento creado correctamente.');
    }

    public function updateDepartamento(Request $request, $id) {
        Departamento::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255', 
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Departamento actualizado.');
    }

    public function destroyDepartamento($id) {
        // En BD el ON DELETE CASCADE se encargará de borrar las áreas vinculadas
        Departamento::findOrFail($id)->delete();
        return back()->with('success', 'Departamento eliminado exitosamente.');
    }

    // --- 5. CATÁLOGO DE ÁREAS ---
    public function storeArea(Request $request) {
        Area::create($request->validate([
            'nombre'          => 'required|string|max:255', 
            'departamento_id' => 'required|exists:departamentos,id'
        ]));
        return back()->with('success', 'Área creada correctamente.');
    }

    public function updateArea(Request $request, $id) {
        Area::findOrFail($id)->update($request->validate([
            'nombre'          => 'required|string|max:255', 
            'departamento_id' => 'required|exists:departamentos,id'
        ]));
        return back()->with('success', 'Área actualizada.');
    }

    public function destroyArea($id) {
        Area::findOrFail($id)->delete();
        return back()->with('success', 'Área eliminada exitosamente.');
    }

    // --- 6. CATÁLOGO DE TIPOS DE CLIENTE ---
    public function storeTipoCliente(Request $request) {
        CatalogoTipoCliente::create($request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Tipo de cliente registrado.');
    }

    public function updateTipoCliente(Request $request, $id) {
        CatalogoTipoCliente::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Tipo de cliente actualizado.');
    }

    public function destroyTipoCliente($id) {
        CatalogoTipoCliente::findOrFail($id)->delete();
        return back()->with('success', 'Tipo de cliente eliminado.');
    }

    // ----------------------------------------------------------------------
    // 7. CATÁLOGO DE ZONAS DE ENTREGA (Actualizado con Color)
    // ----------------------------------------------------------------------
    public function updateZonaEntrega(Request $request, $id) {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'color_hex' => 'required|string|size:7',
            'costo_base' => 'required|numeric|min:0',
            'activo' => 'boolean'
        ]);

        CatalogoZonaEntrega::findOrFail($id)->update([
            'nombre' => $request->nombre,
            'color_hex' => $request->color_hex,
            'costo_base' => $request->costo_base,
            'activo' => $request->activo ?? false
        ]);

        return back()->with('success', 'Costos y color de la zona logística actualizados.');
    }

    public function destroyZonaEntrega($id) {
        CatalogoZonaEntrega::findOrFail($id)->update(['activo' => false]);
        return back()->with('success', 'Zona de entrega desactivada del mapa.');
    }

    // ----------------------------------------------------------------------
    // 8. CATÁLOGO DE HORARIOS DE ENTREGA
    // ----------------------------------------------------------------------
    public function storeHorarioEntrega(Request $request) {
        CatalogoHorarioEntrega::create($request->validate([
            'zona_id' => 'required|exists:catalogo_zonas_entrega,id',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Horario de entrega registrado.');
    }

    public function updateHorarioEntrega(Request $request, $id) {
        CatalogoHorarioEntrega::findOrFail($id)->update($request->validate([
            'zona_id' => 'required|exists:catalogo_zonas_entrega,id',
            'hora_inicio' => 'required|date_format:H:i|date_format:H:i:s', // Soporta ambos formatos al editar
            'hora_fin' => 'required|date_format:H:i|date_format:H:i:s',
            'activo' => 'boolean'
        ]));
        return back()->with('success', 'Horario de entrega actualizado.');
    }

    public function destroyHorarioEntrega($id) {
        CatalogoHorarioEntrega::findOrFail($id)->delete();
        return back()->with('success', 'Horario de entrega eliminado.');
    }

    // --- 9. PORCENTAJES ESCALONAMIENTO (cotización / solicitudes) ---
    public function storePorcentajeEscalonamiento(Request $request) {
        CatalogoPorcentajeEscalonamientoLista::create($request->validate([
            'catalogo_lista_descuento_id' => 'required|exists:catalogo_listas_descuento,id|unique:catalogo_porcentajes_escalonamiento_lista,catalogo_lista_descuento_id',
            'porcentaje_descuento' => 'required|numeric|min:0|max:100',
            'activo' => 'boolean',
        ]));
        return back()->with('success', 'Porcentaje de escalonamiento registrado.');
    }

    public function updatePorcentajeEscalonamiento(Request $request, $id) {
        $porcentaje = CatalogoPorcentajeEscalonamientoLista::findOrFail($id);
        $porcentaje->update($request->validate([
            'catalogo_lista_descuento_id' => 'required|exists:catalogo_listas_descuento,id|unique:catalogo_porcentajes_escalonamiento_lista,catalogo_lista_descuento_id,' . $id,
            'porcentaje_descuento' => 'required|numeric|min:0|max:100',
            'activo' => 'boolean',
        ]));
        return back()->with('success', 'Porcentaje de escalonamiento actualizado.');
    }

    public function destroyPorcentajeEscalonamiento($id) {
        CatalogoPorcentajeEscalonamientoLista::findOrFail($id)->delete();
        return back()->with('success', 'Porcentaje de escalonamiento eliminado.');
    }

    // --- 10. PORCENTAJES LISTADO (resurtido / export Excel) ---
    public function storePorcentajeListado(Request $request) {
        CatalogoPorcentajeListadoLista::create($request->validate([
            'catalogo_lista_descuento_id' => 'required|exists:catalogo_listas_descuento,id|unique:catalogo_porcentajes_listado_lista,catalogo_lista_descuento_id',
            'porcentaje_descuento' => 'required|numeric|min:0|max:100',
            'activo' => 'boolean',
        ]));
        return back()->with('success', 'Porcentaje de listado registrado.');
    }

    public function updatePorcentajeListado(Request $request, $id) {
        $porcentaje = CatalogoPorcentajeListadoLista::findOrFail($id);
        $porcentaje->update($request->validate([
            'catalogo_lista_descuento_id' => 'required|exists:catalogo_listas_descuento,id|unique:catalogo_porcentajes_listado_lista,catalogo_lista_descuento_id,' . $id,
            'porcentaje_descuento' => 'required|numeric|min:0|max:100',
            'activo' => 'boolean',
        ]));
        return back()->with('success', 'Porcentaje de listado actualizado.');
    }

    public function destroyPorcentajeListado($id) {
        CatalogoPorcentajeListadoLista::findOrFail($id)->delete();
        return back()->with('success', 'Porcentaje de listado eliminado.');
    }

    // --- 11. CATÁLOGO DE BANCOS ---
    public function storeBanco(Request $request) {
        CatalogoBanco::create($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_bancos,nombre',
            'activo' => 'boolean',
        ]));
        return back()->with('success', 'Banco registrado correctamente.');
    }

    public function updateBanco(Request $request, $id) {
        CatalogoBanco::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_bancos,nombre,' . $id,
            'activo' => 'boolean',
        ]));
        return back()->with('success', 'Banco actualizado.');
    }

    public function destroyBanco($id) {
        CatalogoBanco::findOrFail($id)->delete();
        return back()->with('success', 'Banco eliminado.');
    }

    // --- 12. SUCURSALES ---
    public function storeSucursal(Request $request, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate([
            'codigo' => 'required|string|max:20|unique:sucursales,codigo',
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $sucursal = Sucursal::create($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('creado', 'sucursal', $sucursal->id, $sucursal->codigo);
        return back()->with('success', 'Sucursal registrada.');
    }

    public function updateSucursal(Request $request, $id, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate([
            'codigo' => 'required|string|max:20|unique:sucursales,codigo,' . $id,
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $sucursal = Sucursal::findOrFail($id);
        $sucursal->update($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('actualizado', 'sucursal', $sucursal->id, $sucursal->codigo);
        return back()->with('success', 'Sucursal actualizada.');
    }

    public function destroySucursal($id) {
        $sucursal = Sucursal::findOrFail($id);
        $ref = $sucursal->codigo;
        $sucursalId = $sucursal->id;
        $sucursal->delete();
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('eliminado', 'sucursal', $sucursalId, $ref);
        return back()->with('success', 'Sucursal eliminada.');
    }

    // --- 13. TIPOS DE ALMACÉN ---
    public function storeTipoAlmacen(Request $request, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate(['nombre' => 'required|string|max:255|unique:catalogo_tipos_almacen,nombre']);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $tipo = CatalogoTipoAlmacen::create($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('creado', 'tipo_almacen', $tipo->id, $tipo->nombre);
        return back()->with('success', 'Tipo de almacén registrado.');
    }

    public function updateTipoAlmacen(Request $request, $id, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate(['nombre' => 'required|string|max:255|unique:catalogo_tipos_almacen,nombre,' . $id]);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $tipo = CatalogoTipoAlmacen::findOrFail($id);
        $tipo->update($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('actualizado', 'tipo_almacen', $tipo->id, $tipo->nombre);
        return back()->with('success', 'Tipo de almacén actualizado.');
    }

    public function destroyTipoAlmacen($id) {
        $tipo = CatalogoTipoAlmacen::findOrFail($id);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('eliminado', 'tipo_almacen', $tipo->id, $tipo->nombre);
        $tipo->delete();
        return back()->with('success', 'Tipo de almacén eliminado.');
    }

    // --- 14. MARCAS DE PRODUCTO ---
    public function storeMarcaProducto(Request $request, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_marcas_producto,nombre',
            'activo' => 'boolean',
        ]);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $marca = CatalogoMarcaProducto::create($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('creado', 'marca_producto', $marca->id, $marca->nombre);
        return back()->with('success', 'Marca registrada.');
    }

    public function updateMarcaProducto(Request $request, $id, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_marcas_producto,nombre,' . $id,
            'activo' => 'boolean',
        ]);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $marca = CatalogoMarcaProducto::findOrFail($id);
        $marca->update($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('actualizado', 'marca_producto', $marca->id, $marca->nombre);
        return back()->with('success', 'Marca actualizada.');
    }

    public function destroyMarcaProducto($id) {
        $marca = CatalogoMarcaProducto::findOrFail($id);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('eliminado', 'marca_producto', $marca->id, $marca->nombre);
        $marca->delete();
        return back()->with('success', 'Marca eliminada.');
    }

    // --- 15. ALMACENES ---
    public function storeAlmacen(Request $request, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate([
            'codigo' => 'required|string|max:50|unique:almacenes,codigo',
            'nombre' => 'required|string|max:255',
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'tipo_almacen_id' => 'nullable|exists:catalogo_tipos_almacen,id',
            'activo' => 'boolean',
        ]);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $almacen = Almacen::create($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('creado', 'almacen', $almacen->id, $almacen->codigo);
        return back()->with('success', 'Almacén registrado.');
    }

    public function updateAlmacen(Request $request, $id, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate([
            'codigo' => 'required|string|max:50|unique:almacenes,codigo,' . $id,
            'nombre' => 'required|string|max:255',
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'tipo_almacen_id' => 'nullable|exists:catalogo_tipos_almacen,id',
            'activo' => 'boolean',
        ]);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $almacen = Almacen::findOrFail($id);
        $almacen->update($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('actualizado', 'almacen', $almacen->id, $almacen->codigo);
        return back()->with('success', 'Almacén actualizado.');
    }

    public function destroyAlmacen($id) {
        $almacen = Almacen::findOrFail($id);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('eliminado', 'almacen', $almacen->id, $almacen->codigo);
        $almacen->delete();
        return back()->with('success', 'Almacén eliminado.');
    }

    // --- 16. CATEGORÍAS DE PRODUCTO ---
    public function storeCategoriaProducto(Request $request, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate(['nombre' => 'required|string|max:255|unique:catalogo_categoria_productos,nombre']);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $categoria = CatalogoCategoriaProducto::create($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('creado', 'categoria_producto', $categoria->id, $categoria->nombre);
        return back()->with('success', 'Categoría registrada.');
    }

    public function updateCategoriaProducto(Request $request, $id, NormalizarTextoImportacionService $normalizador) {
        $data = $request->validate(['nombre' => 'required|string|max:255|unique:catalogo_categoria_productos,nombre,' . $id]);
        $data['nombre'] = $normalizador->texto($data['nombre']);
        $categoria = CatalogoCategoriaProducto::findOrFail($id);
        $categoria->update($data);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('actualizado', 'categoria_producto', $categoria->id, $categoria->nombre);
        return back()->with('success', 'Categoría actualizada.');
    }

    public function destroyCategoriaProducto($id) {
        $categoria = CatalogoCategoriaProducto::findOrFail($id);
        app(RegistrarAuditoriaAlmacenService::class)->catalogoCrud('eliminado', 'categoria_producto', $categoria->id, $categoria->nombre);
        $categoria->delete();
        return back()->with('success', 'Categoría eliminada.');
    }

    // --- IMPORTACIÓN MASIVA CATÁLOGOS ALMACÉN ---
    private const TIPOS_IMPORTACION_ALMACEN = [
        'sucursales',
        'tipos_almacen',
        'marcas_producto',
        'categorias_producto',
        'almacenes',
    ];

    public function descargarPlantillaImportacion(string $tipo, PlantillaImportacionCatalogoService $plantillaService)
    {
        if (! in_array($tipo, self::TIPOS_IMPORTACION_ALMACEN, true)) {
            abort(404);
        }

        return $plantillaService->descargar($tipo);
    }

    public function importarCatalogoAlmacen(Request $request, string $tipo, ImportarCatalogoAlmacenService $importador)
    {
        if (! in_array($tipo, self::TIPOS_IMPORTACION_ALMACEN, true)) {
            abort(404);
        }

        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls,txt',
        ]);

        $stats = $importador->ejecutar($tipo, $request->file('archivo'));

        $mensaje = sprintf(
            'Importación completada: %d nuevos, %d actualizados, %d omitidos.',
            $stats['importados'],
            $stats['actualizados'],
            $stats['omitidos'],
        );

        if (! empty($stats['reporte_url'])) {
            $mensaje .= ' Descarga el reporte de errores para revisar las filas omitidas.';
        }

        return back()
            ->with('success', $mensaje)
            ->with('reporte_importacion_almacenes', $stats);
    }

    // --- CONTROL PEDIDOS: ESTATUS ---
    public function storeEstatusPedido(Request $request)
    {
        CatalogoEstatusPedido::create($request->validate([
            'codigo_interno' => 'required|string|max:50|unique:catalogo_estatus_pedidos,codigo_interno',
            'nombre_visual' => 'required|string|max:255',
            'color_hex' => 'required|string|max:7',
            'fase_ciclo' => 'required|string|max:50',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Estatus de pedido registrado.');
    }

    public function updateEstatusPedido(Request $request, $id)
    {
        $estatus = CatalogoEstatusPedido::findOrFail($id);
        $enUso = PedidoBma::where('catalogo_estatus_pedido_id', $id)->exists();

        $reglas = [
            'nombre_visual' => 'required|string|max:255',
            'color_hex' => 'required|string|max:7',
            'fase_ciclo' => 'required|string|max:50',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ];

        if (!$enUso) {
            $reglas['codigo_interno'] = 'required|string|max:50|unique:catalogo_estatus_pedidos,codigo_interno,' . $id;
        }

        $estatus->update($request->validate($reglas));

        return back()->with('success', 'Estatus de pedido actualizado.');
    }

    public function destroyEstatusPedido($id)
    {
        $estatus = CatalogoEstatusPedido::findOrFail($id);
        if (PedidoBma::where('catalogo_estatus_pedido_id', $id)->exists()) {
            return back()->with('error', 'No se puede eliminar un estatus en uso.');
        }
        $estatus->delete();

        return back()->with('success', 'Estatus de pedido eliminado.');
    }

    // --- CONTROL PEDIDOS: ALMACENES SALIDA ---
    public function storeAlmacenSalida(Request $request)
    {
        CatalogoAlmacenSalida::create($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_almacenes_salida,nombre',
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Almacén de salida registrado.');
    }

    public function updateAlmacenSalida(Request $request, $id)
    {
        CatalogoAlmacenSalida::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_almacenes_salida,nombre,' . $id,
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Almacén de salida actualizado.');
    }

    public function destroyAlmacenSalida($id)
    {
        if (PedidoBma::where('catalogo_almacen_salida_id', $id)->exists()) {
            return back()->with('error', 'No se puede eliminar un almacén en uso.');
        }
        CatalogoAlmacenSalida::findOrFail($id)->delete();

        return back()->with('success', 'Almacén de salida eliminado.');
    }

    // --- CONTROL PEDIDOS: PAQUETERÍAS ---
    public function storePaqueteriaPedido(Request $request)
    {
        CatalogoPaqueteriaPedido::create($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_paqueterias_pedido,nombre',
            'costo_seguro_default' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Paquetería registrada.');
    }

    public function updatePaqueteriaPedido(Request $request, $id)
    {
        CatalogoPaqueteriaPedido::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_paqueterias_pedido,nombre,' . $id,
            'costo_seguro_default' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Paquetería actualizada.');
    }

    public function destroyPaqueteriaPedido($id)
    {
        if (PedidoBma::where('catalogo_paqueteria_id', $id)->exists()) {
            return back()->with('error', 'No se puede eliminar una paquetería en uso.');
        }
        CatalogoPaqueteriaPedido::findOrFail($id)->delete();

        return back()->with('success', 'Paquetería eliminada.');
    }

    // --- CONTROL PEDIDOS: TIPOS DE CAJA ---
    public function storeTipoCajaPedido(Request $request)
    {
        CatalogoTipoCajaPedido::create($request->validate([
            'nombre' => 'required|string|max:255',
            'peso_volumetrico' => 'nullable|numeric|min:0',
            'medidas' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Tipo de caja registrado.');
    }

    public function updateTipoCajaPedido(Request $request, $id)
    {
        CatalogoTipoCajaPedido::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255',
            'peso_volumetrico' => 'nullable|numeric|min:0',
            'medidas' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Tipo de caja actualizado.');
    }

    public function destroyTipoCajaPedido($id)
    {
        if (PedidoBma::where('catalogo_tipo_caja_id', $id)->exists()) {
            return back()->with('error', 'No se puede eliminar un tipo de caja en uso.');
        }
        CatalogoTipoCajaPedido::findOrFail($id)->delete();

        return back()->with('success', 'Tipo de caja eliminado.');
    }

    // --- CONTROL PEDIDOS: TIPOS DE GUÍA ---
    public function storeTipoGuiaPedido(Request $request)
    {
        CatalogoTipoGuiaPedido::create($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_tipos_guia_pedido,nombre',
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Tipo de guía registrado.');
    }

    public function updateTipoGuiaPedido(Request $request, $id)
    {
        CatalogoTipoGuiaPedido::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_tipos_guia_pedido,nombre,' . $id,
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Tipo de guía actualizado.');
    }

    public function destroyTipoGuiaPedido($id)
    {
        if (PedidoBma::where('catalogo_tipo_guia_id', $id)->exists()) {
            return back()->with('error', 'No se puede eliminar un tipo de guía en uso.');
        }
        CatalogoTipoGuiaPedido::findOrFail($id)->delete();

        return back()->with('success', 'Tipo de guía eliminado.');
    }

    // --- CONTROL PEDIDOS: ZONAS ---
    public function storeZonaPedido(Request $request)
    {
        CatalogoZonaPedido::create($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_zonas_pedido,nombre',
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Zona de pedido registrada.');
    }

    public function updateZonaPedido(Request $request, $id)
    {
        CatalogoZonaPedido::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_zonas_pedido,nombre,' . $id,
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Zona de pedido actualizada.');
    }

    public function destroyZonaPedido($id)
    {
        if (PedidoBma::where('catalogo_zona_id', $id)->exists()) {
            return back()->with('error', 'No se puede eliminar una zona en uso.');
        }
        CatalogoZonaPedido::findOrFail($id)->delete();

        return back()->with('success', 'Zona de pedido eliminada.');
    }

    // --- CONTROL PEDIDOS: ENVÍOS / TIENDA ---
    public function storeEnvioTienda(Request $request)
    {
        CatalogoEnvioTienda::create($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_envios_tienda,nombre',
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Envío / tienda registrado.');
    }

    public function updateEnvioTienda(Request $request, $id)
    {
        CatalogoEnvioTienda::findOrFail($id)->update($request->validate([
            'nombre' => 'required|string|max:255|unique:catalogo_envios_tienda,nombre,' . $id,
            'activo' => 'boolean',
        ]));

        return back()->with('success', 'Envío / tienda actualizado.');
    }

    public function destroyEnvioTienda($id)
    {
        $item = CatalogoEnvioTienda::findOrFail($id);
        if ($item->es_otro) {
            return back()->with('error', 'No se puede eliminar la opción «Otro».');
        }
        if (PedidoBma::where('catalogo_envio_tienda_id', $id)->exists()) {
            return back()->with('error', 'No se puede eliminar un envío / tienda en uso.');
        }
        $item->delete();

        return back()->with('success', 'Envío / tienda eliminado.');
    }
}