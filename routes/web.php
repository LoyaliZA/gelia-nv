<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\{LoginController,RegistroController};
use App\Http\Controllers\{DashboardController,AdminController,CatalogoController,ClienteController,LimpiezaClientesController,AutoCobranzaController,ProfileController};
use App\Http\Controllers\Solicitudes\SolicitudController;
use App\Http\Controllers\Reportes\ReporteSolicitudesController;
use App\Http\Controllers\Api\{CotizacionEntregaController,ClienteApiController};
use App\Http\Controllers\EntregasController;
use App\Http\Controllers\MapaLogisticoController;
use App\Http\Controllers\Admin\{AuditoriaListaDescuentoController,PersonalizacionController,ConfiguracionSistemaController,MonitoreoMensajeriaController};
use App\Http\Controllers\AromasListasController;
use App\Http\Controllers\Activos\{ActivoController,CategoriaActivoController,TipoActivoController};
use App\Http\Controllers\Rh\{ColaboradorController,ConfiguracionRhController,CatalogoPuestoController,CatalogoTipoFaltaController,CatalogoBonoController,CatalogoReglaIncidenciaController,DashboardRhController,HorasExtraController,DeduccionController,IncidenciaGerenteController,ReciboRhController,PeriodoPagoController,PrestamoPagoFijoController,SalidaPersonalController,ConsolidadoDeduccionesController,ConsolidadoHorasExtraController,BancoTiempoController};
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\Almacenes\{ProductoController as AlmacenProductoController, InventarioController as AlmacenInventarioController, CostoController as AlmacenCostoController, ImportacionAlmacenController};
use App\Http\Controllers\Facturas\{SolicitudFacturaController,DatosFiscalesController,ArchivoFacturaController};
use App\Http\Controllers\CancelacionesCotizaciones\SolicitudOperativaController;
use App\Http\Controllers\ControlPedidos\PedidoBmaController;
use App\Http\Controllers\ControlPedidos\PedidoBmaAuditoriaController;
use App\Http\Controllers\ControlPedidos\PedidoBmaCedisController;
use App\Http\Controllers\ControlPedidos\PedidoBmaDelegadoController;
use App\Http\Controllers\Mensajeria\{ConversacionController,MensajeController,AdjuntoMensajeController};
use App\Http\Controllers\WebPushController;
use App\Http\Controllers\GestionInterna\DirectorioController;

// ══════════════════════════════════════════════════════════════════════
// 1. REDIRECCIÓN INICIAL
// ══════════════════════════════════════════════════════════════════════
Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/activos/consulta/{token}', [ActivoController::class, 'consultaPublica'])->name('activos.consulta.publica');
    Route::get('/activos/consulta/{token}/qr.svg', [ActivoController::class, 'consultaQr'])->name('activos.consulta.qr');
});

// ══════════════════════════════════════════════════════════════════════
// 2. RUTAS PÚBLICAS (GUEST)
// ══════════════════════════════════════════════════════════════════════
Route::middleware('guest')->group(function () {
    // Login
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Registro Seguro (Vía enlace firmado) - CORRECCIÓN APLICADA AQUÍ
    Route::get('/registro/colaborador', [RegistroController::class, 'mostrarFormulario'])
        ->name('registro.formulario')
        ->middleware('signed:relative');

    Route::post('/registro/colaborador', [RegistroController::class, 'almacenar'])
        ->name('registro.store')
        ->middleware('signed:relative');
});

// ══════════════════════════════════════════════════════════════════════
// 3. RUTAS PROTEGIDAS (Requieren Autenticación)
// ══════════════════════════════════════════════════════════════════════
Route::middleware(['auth'])->group(function () {

    // --- ACCIONES BASE Y SESIÓN ---
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // --- DASHBOARD ---
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::put('/dashboard/preferencias', [DashboardController::class, 'actualizarPreferencias'])->name('dashboard.preferencias');

    // --- PERFIL DE USUARIO Y NOTIFICACIONES ---
    Route::get('/perfil', [ProfileController::class, 'index'])->name('profile.index');
    Route::delete('/perfil/sesiones/otras', [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroy-others');
    Route::get('/perfil/preferencias', [ProfileController::class, 'edit'])->name('profile.preferencias');
    Route::get('/perfil/novedades', [ProfileController::class, 'novedades'])->name('profile.novedades');
    Route::post('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/notificaciones/{id}/leer', [AdminController::class, 'marcarNotificacionLeida'])->name('notifications.read');
    Route::post('/notificaciones/limpiar', [AdminController::class, 'limpiarNotificaciones'])->name('notifications.clear');

    Route::get('/push/vapid-public-key', [\App\Http\Controllers\WebPushController::class, 'vapidPublicKey'])->name('push.vapid');
    Route::post('/push/subscribe', [\App\Http\Controllers\WebPushController::class, 'subscribe'])->name('push.subscribe');
    Route::delete('/push/unsubscribe', [\App\Http\Controllers\WebPushController::class, 'unsubscribe'])->name('push.unsubscribe');

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: MENSAJERÍA INTERNA
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('mensajeria')->name('mensajeria.')->group(function () {
        Route::get('/', [ConversacionController::class, 'index'])->name('index');
        Route::get('/conversaciones', [ConversacionController::class, 'list'])->name('conversaciones.list');
        Route::post('/conversaciones', [ConversacionController::class, 'store'])->name('conversaciones.store');
        Route::get('/usuarios', [ConversacionController::class, 'usuarios'])->name('usuarios');
        Route::get('/conversaciones/{conversacion}/mensajes', [MensajeController::class, 'index'])->name('mensajes.index');
        Route::post('/conversaciones/{conversacion}/mensajes', [MensajeController::class, 'store'])->name('mensajes.store');
        Route::put('/conversaciones/{conversacion}/leer', [MensajeController::class, 'marcarLeida'])->name('conversaciones.leer');
        Route::get('/conversaciones/{conversacion}/medios', [ConversacionController::class, 'medios'])->name('conversaciones.medios');
        Route::get('/presencia/catalogo', [\App\Http\Controllers\Mensajeria\PresenciaController::class, 'catalogo'])->name('presencia.catalogo');
        Route::get('/presencia', [\App\Http\Controllers\Mensajeria\PresenciaController::class, 'show'])->name('presencia.show');
        Route::put('/presencia', [\App\Http\Controllers\Mensajeria\PresenciaController::class, 'update'])->name('presencia.update');
        Route::post('/presencia/heartbeat', [\App\Http\Controllers\Mensajeria\PresenciaController::class, 'heartbeat'])->name('presencia.heartbeat');
        Route::get('/buscar', [\App\Http\Controllers\Mensajeria\BuscarMensajeriaController::class, 'buscar'])->name('buscar');
        Route::get('/conversaciones/{conversacion}/contexto', [\App\Http\Controllers\Mensajeria\BuscarMensajeriaController::class, 'contexto'])->name('conversaciones.contexto');
        Route::post('/conversaciones/{conversacion}/adjuntos', [AdjuntoMensajeController::class, 'store'])->name('adjuntos.store');
        Route::get('/adjuntos/{adjunto}', [AdjuntoMensajeController::class, 'show'])->name('adjuntos.show');
    });


    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO OPERATIVO: SOLICITUDES
    // ══════════════════════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: MIS CLIENTES
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:mis_clientes.gestionar'])->group(function () {
        Route::get('/mis-clientes', [ClienteController::class, 'misClientes'])->name('mis_clientes.index');
        Route::post('/mis-clientes/rapido', [ClienteController::class, 'registroRapido'])->name('mis_clientes.rapido');
    });

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: LIMPIEZA DE CLIENTES (Función Operativa)
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:funciones.limpieza_clientes'])->group(function () {
        Route::get('/funciones/limpieza-clientes', [LimpiezaClientesController::class, 'index'])->name('funciones.limpieza-clientes.index');
        Route::post('/funciones/limpieza-clientes/procesar', [LimpiezaClientesController::class, 'procesar'])->name('funciones.limpieza-clientes.procesar');
    });

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULOS: FUNCIONES OPERATIVAS ADICIONALES
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:funciones.asistencia'])->group(function () {
        Route::get('/funciones/asistencia', [\App\Http\Controllers\FuncionesOperativas\AsistenciaController::class, 'index'])->name('funciones.asistencia.index');
        Route::post('/funciones/asistencia/procesar', [\App\Http\Controllers\FuncionesOperativas\AsistenciaController::class, 'procesar'])->name('funciones.asistencia.procesar');
    });

    Route::middleware(['can:funciones.avisos'])->group(function () {
        Route::get('/funciones/avisos', [\App\Http\Controllers\FuncionesOperativas\AvisosController::class, 'index'])->name('funciones.avisos.index');
        Route::post('/funciones/avisos/procesar', [\App\Http\Controllers\FuncionesOperativas\AvisosController::class, 'procesar'])->name('funciones.avisos.procesar');
    });

    Route::middleware(['can:funciones.gastos'])->group(function () {
        Route::get('/funciones/gastos', [\App\Http\Controllers\FuncionesOperativas\GastosController::class, 'index'])->name('funciones.gastos.index');
        Route::post('/funciones/gastos/procesar', [\App\Http\Controllers\FuncionesOperativas\GastosController::class, 'procesar'])->name('funciones.gastos.procesar');
    });

    Route::middleware(['can:funciones.limpieza_archivos'])->group(function () {
        Route::get('/funciones/limpieza-archivos', [\App\Http\Controllers\FuncionesOperativas\LimpiezaArchivosController::class, 'index'])->name('funciones.limpieza_archivos.index');
        Route::post('/funciones/limpieza-archivos/procesar', [\App\Http\Controllers\FuncionesOperativas\LimpiezaArchivosController::class, 'procesar'])->name('funciones.limpieza_archivos.procesar');
    });

    Route::middleware(['can:funciones.transacciones'])->group(function () {
        Route::get('/funciones/transacciones', [\App\Http\Controllers\FuncionesOperativas\TransaccionesController::class, 'index'])->name('funciones.transacciones.index');
        Route::post('/funciones/transacciones/procesar', [\App\Http\Controllers\FuncionesOperativas\TransaccionesController::class, 'procesar'])->name('funciones.transacciones.procesar');
    });

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: PLANTILLA BELLAROMA
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:plantilla_pedidos.ver'])->prefix('plantilla-bellaroma')->name('plantilla_bellaroma.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PlantillaBellaromaController::class, 'index'])->name('index');
        Route::post('/generar', [\App\Http\Controllers\PlantillaBellaromaController::class, 'generar'])->name('generar');
        Route::get('/{id}/descargar', [\App\Http\Controllers\PlantillaBellaromaController::class, 'descargar'])->name('descargar');
        Route::delete('/{id}', [\App\Http\Controllers\PlantillaBellaromaController::class, 'eliminar'])->name('eliminar');
        Route::post('/configuracion', [\App\Http\Controllers\PlantillaBellaromaController::class, 'guardarConfiguracion'])->name('configuracion.guardar');
    });

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: WOOCOMMERCE — SINCRONIZAR PRECIOS
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:woocommerce.ver'])->prefix('woocommerce')->name('woocommerce.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WooCommerceController::class, 'index'])->name('index');
        Route::get('/auditoria', [\App\Http\Controllers\WooCommerceController::class, 'auditoria'])->name('auditoria')->middleware('can:woocommerce.auditoria');
        Route::get('/auditoria/{id}/descargar', [\App\Http\Controllers\WooCommerceController::class, 'descargarAuditoria'])->name('auditoria.descargar')->middleware('can:woocommerce.auditoria');
        Route::get('/alertas', [\App\Http\Controllers\WooCommerceController::class, 'alertas'])->name('alertas');
        Route::get('/progreso/{id}', [\App\Http\Controllers\WooCommerceController::class, 'progreso'])->name('progreso');
        Route::get('/sync/activo', [\App\Http\Controllers\WooCommerceController::class, 'syncActivo'])->name('sync.activo');
        Route::get('/templates/{id}/descargar', [\App\Http\Controllers\WooCommerceController::class, 'descargar'])->name('descargar');
        Route::put('/configuracion', [\App\Http\Controllers\WooCommerceController::class, 'guardarConfiguracion'])->name('configuracion.update')->middleware('can:woocommerce.configurar');
        Route::post('/configuracion/probar-conexion', [\App\Http\Controllers\WooCommerceController::class, 'probarConexionApi'])->name('configuracion.probar_conexion')->middleware('can:woocommerce.configurar');
        Route::post('/import-preview', [\App\Http\Controllers\WooCommerceController::class, 'importPreview'])->name('import_preview')->middleware('can:woocommerce.sincronizar');
        Route::post('/previsualizar-mapeo', [\App\Http\Controllers\WooCommerceController::class, 'previsualizarMapeo'])->name('previsualizar_mapeo')->middleware('can:woocommerce.sincronizar');
        Route::post('/previsualizar', [\App\Http\Controllers\WooCommerceController::class, 'previsualizar'])->name('previsualizar')->middleware('can:woocommerce.sincronizar');
        Route::post('/procesar', [\App\Http\Controllers\WooCommerceController::class, 'procesar'])->name('procesar')->middleware('can:woocommerce.sincronizar');
        Route::post('/sincronizar', [\App\Http\Controllers\WooCommerceController::class, 'sincronizar'])->name('sincronizar')->middleware('can:woocommerce.sincronizar');
        Route::post('/fetch-precios', [\App\Http\Controllers\WooCommerceController::class, 'fetchPrecios'])->name('fetch_precios')->middleware('can:woocommerce.sincronizar');
        Route::post('/catalogo/sincronizar', [\App\Http\Controllers\WooCommerceController::class, 'sincronizarCatalogo'])->name('catalogo.sincronizar')->middleware('can:woocommerce.sincronizar');
        Route::post('/precios-locales', [\App\Http\Controllers\WooCommerceController::class, 'actualizarPreciosLocales'])->name('precios_locales')->middleware('can:woocommerce.sincronizar');
        Route::get('/productos/{id}/consultar', [\App\Http\Controllers\WooCommerceController::class, 'consultarPrecioIndividual'])->name('productos.consultar')->middleware('can:woocommerce.sincronizar');
        Route::put('/productos/{id}', [\App\Http\Controllers\WooCommerceController::class, 'actualizarPrecioIndividual'])->name('productos.update')->middleware('can:woocommerce.sincronizar');
        Route::post('/emergencia/ocultar', [\App\Http\Controllers\WooCommerceController::class, 'emergenciaOcultar'])->name('emergencia.ocultar')->middleware('can:woocommerce.emergencia');
        Route::delete('/sync/fantasmas', [\App\Http\Controllers\WooCommerceController::class, 'descartarTodosFantasmas'])->name('sync.descartar_todos')->middleware('can:woocommerce.sincronizar');
        Route::delete('/sync/{id}/descartar', [\App\Http\Controllers\WooCommerceController::class, 'descartarSync'])->name('sync.descartar')->middleware('can:woocommerce.sincronizar');
        Route::delete('/sync/{id}/cancelar', [\App\Http\Controllers\WooCommerceController::class, 'cancelarSync'])->name('sync.cancelar')->middleware('can:woocommerce.sincronizar');
        Route::post('/sync/{id}/continuar', [\App\Http\Controllers\WooCommerceController::class, 'continuarSync'])->name('sync.continuar')->middleware('can:woocommerce.sincronizar');
        Route::post('/sync/{id}/reanudar', [\App\Http\Controllers\WooCommerceController::class, 'reanudarSync'])->name('sync.reanudar')->middleware('can:woocommerce.sincronizar');
        Route::delete('/templates/{id}', [\App\Http\Controllers\WooCommerceController::class, 'eliminar'])->name('templates.eliminar')->middleware('can:woocommerce.sincronizar');
    });

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: AUTO-COBRANZA
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('auto-cobranza')->name('auto-cobranza.')->group(function () {
        Route::get('/', [AutoCobranzaController::class, 'index'])->name('index');
        Route::post('/importar/preview', [AutoCobranzaController::class, 'previsualizarImporte'])->name('importar.preview');
        Route::post('/importar', [AutoCobranzaController::class, 'importarReporte'])->name('importar');
        Route::put('/alertas/{alerta}', [AutoCobranzaController::class, 'actualizarAlerta'])->name('alertas.update');
        Route::get('/clientes/{clienteId}/bitacora', [AutoCobranzaController::class, 'bitacora'])->name('bitacora');
        Route::get('/clientes/{cliente}/folios', [AutoCobranzaController::class, 'foliosCliente'])->name('clientes.folios');
        Route::get('/historial', [AutoCobranzaController::class, 'historial'])->name('historial');
        Route::get('/abonos-hoy', [AutoCobranzaController::class, 'abonosDelDia'])->name('abonos-hoy');
        Route::post('/clientes/{cliente}/resolver-aumento', [AutoCobranzaController::class, 'resolverAumento'])->name('alertas.resolver-aumento');
        Route::put('/clientes/{cliente}/reparar-fecha', [AutoCobranzaController::class, 'repararFechaInicio'])->name('clientes.reparar-fecha');
        Route::post('/clientes/{cliente}/recalcular-credito', [AutoCobranzaController::class, 'recalcularCredito'])->name('clientes.recalcular-credito');
        Route::post('/recalcular-creditos', [AutoCobranzaController::class, 'recalcularCreditosMasivo'])->name('recalcular-creditos');
        Route::post('/facturas/{cobranzaFactura}/confirmar-pago', [AutoCobranzaController::class, 'confirmarPagoCobranza'])->name('facturas.confirmar-pago');
        Route::post('/facturas/{cobranzaFactura}/descartar-pago', [AutoCobranzaController::class, 'descartarPagoCobranza'])->name('facturas.descartar-pago');
        Route::post('/facturas/{cobranzaFactura}/verificar', [AutoCobranzaController::class, 'verificarPago'])->name('facturas.verificar');
        Route::post('/configuracion', [AutoCobranzaController::class, 'guardarConfiguracion'])->name('configuracion.store');
        
        Route::middleware(['can:cobranza.reportes'])->prefix('reportes')->name('reportes.')->group(function () {
            Route::post('/generar', [\App\Http\Controllers\Reportes\ReporteCobranzaController::class, 'generar'])->name('generar');
            Route::get('/estado/{jobId}', [\App\Http\Controllers\Reportes\ReporteCobranzaController::class, 'estado'])->name('estado');
            Route::get('/descargar/{jobId}', [\App\Http\Controllers\Reportes\ReporteCobranzaController::class, 'descargar'])->name('descargar');
        });
    });

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: CONTABILIDAD BELLAROMA
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:contabilidad.ver'])->prefix('contabilidad')->name('contabilidad.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'index'])->name('index');
        Route::get('/retiros', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'retiros'])->name('retiros');
        Route::get('/dashboard-data', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'dashboardData'])->name('dashboard_data');
        Route::get('/exportar-pdf', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'exportarPdf'])->name('exportar_pdf');
        Route::get('/exportar-csv', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'exportarCsv'])->name('exportar_csv');
        Route::post('/lista-preview', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'listaPreview'])
            ->middleware('can:contabilidad.importar')
            ->name('lista_preview');
        Route::post('/previsualizar-mapeo', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'previsualizarMapeo'])
            ->middleware('can:contabilidad.importar')
            ->name('previsualizar_mapeo');
        Route::post('/procesar-lista', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'procesarLista'])
            ->middleware('can:contabilidad.importar')
            ->name('procesar_lista');
        Route::post('/pedidos', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'store'])
            ->middleware('can:contabilidad.pedidos.crear')
            ->name('pedidos.store');
        Route::put('/pedidos/{pedido}', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'update'])
            ->middleware('can:contabilidad.pedidos.editar')
            ->name('pedidos.update');
        Route::delete('/pedidos/{pedido}', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'destroy'])
            ->middleware('can:contabilidad.pedidos.eliminar')
            ->name('pedidos.destroy');
        Route::post('/pedidos/{pedido}/confirmar-retiro', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'confirmarRetiro'])
            ->middleware('can:contabilidad.retiros.confirmar')
            ->name('pedidos.confirmar_retiro');
        Route::post('/retiros/confirmar-lote', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'confirmarLote'])
            ->middleware('can:contabilidad.retiros.confirmar')
            ->name('retiros.confirmar_lote');
        Route::put('/plataformas/comisiones', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'actualizarComisiones'])
            ->middleware('can:contabilidad.plataformas.configurar')
            ->name('plataformas.comisiones');
        Route::put('/configuracion', [\App\Http\Controllers\Contabilidad\ContabilidadController::class, 'actualizarConfiguracion'])
            ->middleware('can:contabilidad.plataformas.configurar')
            ->name('configuracion.update');
    });

    Route::middleware(['can:solicitudes.ver_listado'])->group(function () {
        Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
    });

    Route::middleware(['can:solicitudes.exportar'])->group(function () {
        Route::get('/solicitudes/exportar', [SolicitudController::class, 'exportar'])->name('solicitudes.exportar');
        Route::get('/reportes/solicitudes', [ReporteSolicitudesController::class, 'index'])->name('reportes.solicitudes.index');
        Route::get('/reportes/solicitudes/exportar', [ReporteSolicitudesController::class, 'exportar'])->name('reportes.solicitudes.exportar');
    });

    Route::middleware(['can:solicitudes.crear'])->group(function () {
        Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');
    });

    // Control de pagos (Limitado)
    // CORRECCIÓN: Se actualizó el middleware para exigir el permiso atómico correcto
    Route::put('/solicitudes/{solicitud}/confirmar-pago', [SolicitudController::class, 'confirmarPago'])
        ->middleware('can:solicitudes.confirmar_pago')
        ->name('solicitudes.confirmar_pago');

    // Control de estados (Verificación mediante Gate en el Controlador)
    Route::put('/solicitudes/{solicitud}', [SolicitudController::class, 'update'])->name('solicitudes.update');
    Route::put('/solicitudes/{solicitud}/rechazar-pago', [SolicitudController::class, 'rechazarPago'])->name('solicitudes.rechazar_pago');
    // AGREGA ESTA LÍNEA: Ruta específica para la revisión administrativa
    Route::put('/solicitudes/{solicitud}/estado', [SolicitudController::class, 'actualizarEstado'])->name('solicitudes.actualizar_estado');
    Route::put('/solicitudes/{solicitud}/confirmar-lista', [SolicitudController::class, 'confirmarCambioLista'])
        ->middleware('can:solicitudes.confirmar_cambio_lista')
        ->name('solicitudes.confirmar_lista');
    Route::put('/solicitudes/{solicitud}/confirmar-rollback', [SolicitudController::class, 'confirmarRollback'])->name('solicitudes.confirmar_rollback');
    Route::post('/solicitudes/{solicitud}/solicitar-cancelacion', [SolicitudController::class, 'solicitarCancelacion'])
        ->middleware('can:solicitudes.solicitar_cancelacion')
        ->name('solicitudes.solicitar_cancelacion');
    Route::put('/solicitudes/{solicitud}/cancelar', [SolicitudController::class, 'cancelar'])->name('solicitudes.cancelar')->middleware('can:solicitudes.cancelar');
    Route::post('/solicitudes/{solicitud}/consultas', [SolicitudController::class, 'storeConsulta'])
        ->name('solicitudes.consultas.store');
    Route::put('/solicitudes/{solicitud}/consultas/{consulta}', [SolicitudController::class, 'responderConsulta'])->name('solicitudes.consultas.responder');
    Route::put('/solicitudes/{solicitud}/consultas/{consulta}/leer', [SolicitudController::class, 'marcarConsultaLeida'])->name('solicitudes.consultas.leer');
    Route::delete('/solicitudes/{solicitud}', [SolicitudController::class, 'destroy'])->name('solicitudes.destroy');
    Route::put('/solicitudes/{id}/restaurar', [SolicitudController::class, 'restaurar'])->name('solicitudes.restaurar');

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: SOLICITUDES DE FACTURAS
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:facturas.gestionar_datos_fiscales'])->prefix('facturas/datos-fiscales')->name('facturas.datos_fiscales.')->group(function () {
        Route::get('/', [DatosFiscalesController::class, 'index'])->name('index');
        Route::put('/{cliente}', [DatosFiscalesController::class, 'update'])->name('update');
    });

    Route::middleware(['can:facturas.crear'])->prefix('facturas')->name('facturas.')->group(function () {
        Route::get('/plantilla-fiscales/descargar', [SolicitudFacturaController::class, 'descargarPlantilla'])->name('plantilla_fiscales');
        Route::post('/', [SolicitudFacturaController::class, 'store'])->name('store');
        Route::put('/{factura}/reparar', [SolicitudFacturaController::class, 'reparar'])->name('reparar');
    });

    Route::middleware(['can:facturas.ver_listado'])->prefix('facturas')->name('facturas.')->group(function () {
        Route::get('/', [SolicitudFacturaController::class, 'index'])->name('index');
        Route::get('/exportar', [SolicitudFacturaController::class, 'exportar'])->middleware('can:facturas.exportar')->name('exportar');
        Route::get('/{factura}/datos-fiscales', [SolicitudFacturaController::class, 'datosFiscales'])->name('datos_fiscales');
        Route::get('/{factura}/archivo/{tipo}', [ArchivoFacturaController::class, 'show'])->name('archivo');
        Route::get('/{factura}', [SolicitudFacturaController::class, 'show'])->name('show');
    });

    Route::prefix('facturas')->name('facturas.')->group(function () {
        Route::put('/{factura}/estado', [SolicitudFacturaController::class, 'actualizarEstado'])->name('actualizar_estado');
    });

    Route::middleware(['can:facturas.verificar'])->prefix('facturas')->name('facturas.')->group(function () {
        Route::put('/{factura}/verificar', [SolicitudFacturaController::class, 'verificar'])->name('verificar');
    });

    Route::middleware(['can:facturas.eliminar'])->prefix('facturas')->name('facturas.')->group(function () {
        Route::delete('/{factura}', [SolicitudFacturaController::class, 'destroy'])->name('destroy');
    });

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: CANCELACIONES Y COTIZACIONES
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:cancelaciones_cotizaciones.ver_listado'])->prefix('cancelaciones-cotizaciones')->name('cancelaciones_cotizaciones.')->group(function () {
        Route::get('/', [SolicitudOperativaController::class, 'index'])->name('index');
        Route::get('/exportar', [SolicitudOperativaController::class, 'exportar'])->middleware('can:cancelaciones_cotizaciones.exportar')->name('exportar');
    });

    Route::middleware(['can:cancelaciones_cotizaciones.crear'])->prefix('cancelaciones-cotizaciones')->name('cancelaciones_cotizaciones.')->group(function () {
        Route::post('/', [SolicitudOperativaController::class, 'store'])->name('store');
    });

    Route::put('/cancelaciones-cotizaciones/{solicitud}/estado', [SolicitudOperativaController::class, 'actualizarEstado'])
        ->name('cancelaciones_cotizaciones.actualizar_estado');

    Route::middleware(['can:cancelaciones_cotizaciones.solicitar_cancelacion'])->prefix('cancelaciones-cotizaciones')->name('cancelaciones_cotizaciones.')->group(function () {
        Route::post('/{solicitud}/solicitar-cancelacion', [SolicitudOperativaController::class, 'solicitarCancelacion'])->name('solicitar_cancelacion');
    });

    Route::middleware(['can:cancelaciones_cotizaciones.cancelar'])->prefix('cancelaciones-cotizaciones')->name('cancelaciones_cotizaciones.')->group(function () {
        Route::put('/{solicitud}/cancelar', [SolicitudOperativaController::class, 'cancelar'])->name('cancelar');
    });

    Route::middleware(['can:cancelaciones_cotizaciones.eliminar'])->prefix('cancelaciones-cotizaciones')->name('cancelaciones_cotizaciones.')->group(function () {
        Route::delete('/{solicitud}', [SolicitudOperativaController::class, 'destroy'])->name('destroy');
    });

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: CONTROL DE PEDIDOS (PedidosBMA)
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:control_pedidos.ver_listado'])->prefix('control-pedidos')->name('control_pedidos.')->group(function () {
        Route::get('/', [PedidoBmaController::class, 'index'])->name('index');
        Route::get('/exportar', [PedidoBmaController::class, 'exportar'])->middleware('can:control_pedidos.exportar')->name('exportar');
    });

    Route::middleware(['can:control_pedidos.crear'])->prefix('control-pedidos')->name('control_pedidos.')->group(function () {
        Route::post('/', [PedidoBmaController::class, 'store'])->name('store');
        Route::put('/{pedidoBma}/enviar', [PedidoBmaController::class, 'enviar'])->name('enviar');
    });

    Route::middleware(['can:control_pedidos.editar'])->prefix('control-pedidos')->name('control_pedidos.')->group(function () {
        Route::put('/{pedidoBma}', [PedidoBmaController::class, 'update'])->name('update');
    });

    Route::middleware(['can:control_pedidos.eliminar'])->prefix('control-pedidos')->name('control_pedidos.')->group(function () {
        Route::delete('/{pedidoBma}', [PedidoBmaController::class, 'destroy'])->name('destroy');
    });

    Route::middleware(['can:control_pedidos.auditar'])->prefix('control-pedidos/auditar')->name('control_pedidos.auditar.')->group(function () {
        Route::get('/', [PedidoBmaAuditoriaController::class, 'index'])->name('index');
        Route::post('/{pedidoBma}/validar-pago', [PedidoBmaAuditoriaController::class, 'validarPago'])->name('validar_pago');
        Route::post('/{pedidoBma}/remision', [PedidoBmaAuditoriaController::class, 'subirRemision'])->name('remision.store');
        Route::delete('/{pedidoBma}/remision', [PedidoBmaAuditoriaController::class, 'eliminarRemision'])->name('remision.destroy');
        Route::post('/{pedidoBma}/aprobar', [PedidoBmaAuditoriaController::class, 'aprobar'])->name('aprobar');
        Route::post('/{pedidoBma}/rechazar', [PedidoBmaAuditoriaController::class, 'rechazar'])->name('rechazar');
        Route::post('/{pedidoBma}/liberar-resguardo', [PedidoBmaAuditoriaController::class, 'liberarResguardo'])->name('liberar_resguardo');
    });

    Route::middleware(['can:control_pedidos.cedis'])->prefix('control-pedidos/cedis')->name('control_pedidos.cedis.')->group(function () {
        Route::get('/', [PedidoBmaCedisController::class, 'index'])->name('index');
        Route::post('/{pedidoBma}/marcar-empacado', [PedidoBmaCedisController::class, 'marcarEmpacado'])->name('marcar_empacado');
        Route::post('/{pedidoBma}/marcar-enviado', [PedidoBmaCedisController::class, 'marcarEnviado'])->name('marcar_enviado');
        Route::post('/{pedidoBma}/revertir-empacado', [PedidoBmaCedisController::class, 'revertirEmpacado'])->name('revertir_empacado');
        Route::post('/{pedidoBma}/reportar-incidencia', [PedidoBmaCedisController::class, 'reportarIncidencia'])->name('reportar_incidencia');
    });

    Route::middleware(['can:control_pedidos.delegado'])->prefix('control-pedidos/delegado')->name('control_pedidos.delegado.')->group(function () {
        Route::get('/', [PedidoBmaDelegadoController::class, 'index'])->name('index');
        Route::get('/exportar', [PedidoBmaDelegadoController::class, 'exportar'])->name('exportar');
        Route::post('/importar', [PedidoBmaDelegadoController::class, 'importar'])->name('importar');
        Route::post('/{pedidoBma}/asignar-guia', [PedidoBmaDelegadoController::class, 'asignarGuia'])->name('asignar_guia');
        Route::post('/{pedidoBma}/actualizar-guia', [PedidoBmaDelegadoController::class, 'actualizarGuia'])->name('actualizar_guia');
        Route::post('/{pedidoBma}/guia-pdf', [PedidoBmaDelegadoController::class, 'subirGuiaPdf'])->name('guia_pdf.store');
        Route::delete('/{pedidoBma}/guia-pdf', [PedidoBmaDelegadoController::class, 'eliminarGuiaPdf'])->name('guia_pdf.destroy');
    });

    // --- Nuevo Módulo: Interfaz de Entregas ---
    Route::middleware(['can:entregas.cotizar'])->group(function () {
        Route::get('/entregas/cotizador', [EntregasController::class, 'index'])->name('entregas.index');
        Route::put('/entregas/configuracion', [EntregasController::class, 'actualizarConfiguracion'])->name('entregas.configuracion.update')->middleware('can:entregas.configurar_zonas');
        Route::post('/entregas/zonas', [EntregasController::class, 'storeZona'])->name('entregas.zonas.store')->middleware('can:entregas.configurar_zonas');
    });

    // ══════════════════════════════════════════════════════════════════════
    // FUNCIONES OPERATIVAS: CRUCE DE INVENTARIOS (LISTADOS)
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:listados.ver'])->prefix('funciones/listados')->name('listados.')->group(function () {
        Route::get('/', [AromasListasController::class, 'index'])->name('index');
        Route::post('/generar', [AromasListasController::class, 'generar'])->name('generar');
        Route::get('/descargar-temporal', [AromasListasController::class, 'descargarTemporal'])->name('descargar_temporal');
        
        Route::post('/guardar', [AromasListasController::class, 'guardarLista'])->name('guardar')->middleware('can:listados.crear');
        Route::post('/{id}/actualizar', [AromasListasController::class, 'actualizarLista'])->name('actualizar')->middleware('can:listados.editar');
        Route::delete('/{id}', [AromasListasController::class, 'eliminarLista'])->name('eliminar')->middleware('can:listados.eliminar');
        
        Route::get('/configuracion', [AromasListasController::class, 'obtenerConfiguracion'])->name('config.obtener')->middleware('can:listados.configurar_porcentajes');
        Route::post('/configuracion', [AromasListasController::class, 'guardarConfiguracion'])->name('config.guardar')->middleware('can:listados.configurar_porcentajes');
    });

    // ══════════════════════════════════════════════════════════════════════
    // FUNCIONES OPERATIVAS: EJERCICIO ESCALONAMIENTO
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:ejercicio_escalonamiento.ver'])->prefix('funciones/ejercicio-escalonamiento')->name('ejercicio_escalonamiento.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Herramientas\EjercicioEscalonamientoController::class, 'index'])->name('index');
    });


    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: ALMACENES (Productos · Inventarios · Costos)
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('almacenes')->name('almacenes.')->group(function () {
        Route::middleware(['role_or_permission:almacenes.productos.ver|catalogos.gestionar'])->prefix('productos')->name('productos.')->group(function () {
            Route::get('/', [AlmacenProductoController::class, 'index'])->name('index');
            Route::get('/buscar', [AlmacenProductoController::class, 'buscar'])
                ->middleware('role_or_permission:almacenes.productos.ver|almacenes.inventarios.ver|almacenes.costos.ver|catalogos.gestionar')
                ->name('buscar');
            Route::post('/', [AlmacenProductoController::class, 'store'])->middleware('role_or_permission:almacenes.productos.gestionar|catalogos.gestionar')->name('store');
            Route::put('/{producto}', [AlmacenProductoController::class, 'update'])->middleware('role_or_permission:almacenes.productos.gestionar|catalogos.gestionar')->name('update');
            Route::delete('/{producto}', [AlmacenProductoController::class, 'destroy'])->middleware('role_or_permission:almacenes.productos.gestionar|catalogos.gestionar')->name('destroy');
            Route::get('/plantilla-importacion', [AlmacenProductoController::class, 'descargarPlantillaImportacion'])->middleware('role_or_permission:almacenes.productos.gestionar|catalogos.gestionar')->name('plantilla_importacion');
            Route::post('/import-preview', [AlmacenProductoController::class, 'importPreview'])->middleware('role_or_permission:almacenes.productos.gestionar|catalogos.gestionar')->name('import_preview');
            Route::post('/import-iniciar', [AlmacenProductoController::class, 'importIniciar'])->middleware('role_or_permission:almacenes.productos.gestionar|catalogos.gestionar')->name('import_iniciar');
        });

        Route::middleware(['role_or_permission:almacenes.inventarios.ver|catalogos.gestionar'])->prefix('inventarios')->name('inventarios.')->group(function () {
            Route::get('/', [AlmacenInventarioController::class, 'index'])->name('index');
            Route::post('/', [AlmacenInventarioController::class, 'store'])->middleware('role_or_permission:almacenes.inventarios.gestionar|catalogos.gestionar')->name('store');
            Route::put('/{inventario}', [AlmacenInventarioController::class, 'update'])->middleware('role_or_permission:almacenes.inventarios.gestionar|catalogos.gestionar')->name('update');
            Route::delete('/{inventario}', [AlmacenInventarioController::class, 'destroy'])->middleware('role_or_permission:almacenes.inventarios.gestionar|catalogos.gestionar')->name('destroy');
            Route::post('/import-preview', [AlmacenInventarioController::class, 'importPreview'])->middleware('role_or_permission:almacenes.inventarios.importar|catalogos.gestionar')->name('import_preview');
            Route::post('/import-iniciar', [AlmacenInventarioController::class, 'importIniciar'])->middleware('role_or_permission:almacenes.inventarios.importar|catalogos.gestionar')->name('import_iniciar');
            Route::get('/plantilla-importacion', [AlmacenInventarioController::class, 'descargarPlantillaImportacion'])->middleware('role_or_permission:almacenes.inventarios.importar|catalogos.gestionar')->name('plantilla_importacion');
        });

        Route::middleware(['role_or_permission:almacenes.costos.ver|catalogos.gestionar'])->prefix('costos')->name('costos.')->group(function () {
            Route::get('/', [AlmacenCostoController::class, 'index'])->name('index');
            Route::post('/', [AlmacenCostoController::class, 'store'])->middleware('role_or_permission:almacenes.costos.gestionar|catalogos.gestionar')->name('store');
            Route::put('/{costo}', [AlmacenCostoController::class, 'update'])->middleware('role_or_permission:almacenes.costos.gestionar|catalogos.gestionar')->name('update');
            Route::delete('/{costo}', [AlmacenCostoController::class, 'destroy'])->middleware('role_or_permission:almacenes.costos.gestionar|catalogos.gestionar')->name('destroy');
            Route::get('/plantilla-importacion', [AlmacenCostoController::class, 'descargarPlantillaImportacion'])->middleware('role_or_permission:almacenes.costos.importar|catalogos.gestionar')->name('plantilla_importacion');
            Route::post('/import-preview', [AlmacenCostoController::class, 'importPreview'])->middleware('role_or_permission:almacenes.costos.importar|catalogos.gestionar')->name('import_preview');
            Route::post('/import-iniciar', [AlmacenCostoController::class, 'importIniciar'])->middleware('role_or_permission:almacenes.costos.importar|catalogos.gestionar')->name('import_iniciar');
        });

        Route::prefix('importaciones')->name('importaciones.')->group(function () {
            Route::get('/progreso/{id}', [ImportacionAlmacenController::class, 'progreso'])
                ->middleware('role_or_permission:almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar')
                ->name('progreso');
            Route::get('/activo', [ImportacionAlmacenController::class, 'activo'])
                ->middleware('role_or_permission:almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar')
                ->name('activo');
            Route::delete('/{id}/cancelar', [ImportacionAlmacenController::class, 'cancelar'])
                ->middleware('role_or_permission:almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar')
                ->name('cancelar');
            Route::post('/{id}/continuar', [ImportacionAlmacenController::class, 'continuar'])
                ->middleware('role_or_permission:almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar')
                ->name('continuar');
        });

        Route::get('/importaciones/reporte-errores/{token}', [ImportacionAlmacenController::class, 'descargarReporteErrores'])
            ->middleware('role_or_permission:almacenes.productos.gestionar|almacenes.inventarios.importar|almacenes.costos.importar|catalogos.gestionar')
            ->name('importaciones.reporte_errores');
    });

    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: CONTROL DE ACTIVOS
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:activos.ver'])->prefix('activos')->name('activos.')->group(function () {
        Route::get('/', [ActivoController::class, 'index'])->name('index');
        Route::get('/resolver-codigo', [ActivoController::class, 'resolverCodigo'])->name('resolver_codigo');
        Route::get('/exportar', [ActivoController::class, 'exportar'])->middleware('can:activos.exportar')->name('exportar');
        Route::get('/etiquetas', [ActivoController::class, 'etiquetas'])->middleware('can:activos.exportar')->name('etiquetas');
        Route::get('/etiquetas/contar', [ActivoController::class, 'etiquetasContar'])->middleware('can:activos.exportar')->name('etiquetas.contar');
        Route::get('/etiquetas/vista-previa', [ActivoController::class, 'etiquetasVistaPrevia'])->middleware('can:activos.exportar')->name('etiquetas.vista_previa');
        Route::get('/etiquetas/descargar', [ActivoController::class, 'etiquetasDescargar'])->middleware('can:activos.exportar')->name('etiquetas.descargar');
        Route::get('/alertas', [ActivoController::class, 'alertas'])->name('alertas');
        Route::get('/{activo}/qr.svg', [ActivoController::class, 'qr'])->name('qr');
        Route::get('/{activo}/qr.png', [ActivoController::class, 'qrPng'])->name('qr_png');
        Route::get('/{activo}', [ActivoController::class, 'show'])->name('show');

        Route::post('/', [ActivoController::class, 'store'])->middleware('can:activos.crear')->name('store');
        Route::put('/{activo}', [ActivoController::class, 'update'])->middleware('can:activos.editar')->name('update');
        Route::post('/{activo}/asignar', [ActivoController::class, 'asignar'])->middleware('can:activos.asignar')->name('asignar');
        Route::post('/{activo}/devolver', [ActivoController::class, 'devolver'])->middleware('can:activos.asignar')->name('devolver');
        Route::post('/asignaciones/{asignacion}/firmar', [ActivoController::class, 'firmar'])->name('asignaciones.firmar');
        Route::get('/asignaciones/{asignacion}/responsiva', [ActivoController::class, 'responsiva'])->name('asignaciones.responsiva');
        Route::get('/asignaciones/{asignacion}/responsiva/vista-previa', [ActivoController::class, 'responsivaVistaPrevia'])->name('asignaciones.responsiva_vista_previa');
        Route::post('/asignaciones/firmar-conjunto', [ActivoController::class, 'firmarConjunto'])->name('asignaciones.firmar_conjunto');
        Route::get('/usuarios/{usuario}/responsiva-conjunta', [ActivoController::class, 'responsivaConjunta'])->name('usuarios.responsiva_conjunta');
        Route::get('/usuarios/{usuario}/responsiva-conjunta/vista-previa', [ActivoController::class, 'responsivaConjuntaVistaPrevia'])->name('usuarios.responsiva_conjunta_vista_previa');
        Route::post('/configuracion', [ActivoController::class, 'guardarConfiguracion'])->middleware('can:activos.configurar_tipos')->name('configuracion.guardar');
        Route::post('/{activo}/transferir', [ActivoController::class, 'transferir'])->middleware('can:activos.transferir')->name('transferir');
        Route::post('/{activo}/estado', [ActivoController::class, 'cambiarEstado'])->middleware('can:activos.cambiar_estado')->name('estado');
        Route::post('/{activo}/mantenimiento', [ActivoController::class, 'programarMantenimiento'])->middleware('can:activos.cambiar_estado')->name('mantenimiento');
        Route::post('/{activo}/mantenimiento/{mantenimiento}/completar', [ActivoController::class, 'completarMantenimiento'])->middleware('can:activos.cambiar_estado')->name('mantenimiento.completar');
        Route::post('/{activo}/fotos', [ActivoController::class, 'subirFotos'])->middleware('can:activos.editar')->name('fotos.store');
        Route::delete('/{activo}/fotos/{foto}', [ActivoController::class, 'eliminarFoto'])->middleware('can:activos.editar')->name('fotos.destroy');
    });


    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO: RECURSOS HUMANOS
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:rh.ver'])->prefix('rh')->name('rh.')->group(function () {
        Route::get('/', [DashboardRhController::class, 'index'])->name('index');
        Route::get('/descargar-manual', [DashboardRhController::class, 'descargarManual'])->name('descargar_manual');

        Route::get('/colaboradores', [ColaboradorController::class, 'index'])->name('colaboradores.index');
        Route::post('/colaboradores/preview-calculos', [ColaboradorController::class, 'previewCalculos'])->name('colaboradores.preview_calculos');
        Route::get('/colaboradores/usuarios/{usuario}/sincronizar', [ColaboradorController::class, 'sincronizarUsuario'])
            ->middleware('can:rh.colaboradores.vincular_usuario')
            ->name('colaboradores.sincronizar_usuario');
        Route::get('/colaboradores/plantilla-importacion', [ColaboradorController::class, 'descargarPlantillaImportacion'])
            ->middleware('can:rh.colaboradores.crear')
            ->name('colaboradores.plantilla_importacion');
        Route::post('/colaboradores/importar', [ColaboradorController::class, 'importar'])
            ->middleware('can:rh.colaboradores.crear')
            ->name('colaboradores.importar');
        Route::get('/colaboradores/{colaborador}', [ColaboradorController::class, 'show'])->name('colaboradores.show');
        Route::post('/colaboradores', [ColaboradorController::class, 'store'])
            ->middleware('can:rh.colaboradores.crear')
            ->name('colaboradores.store');
        Route::put('/colaboradores/{colaborador}', [ColaboradorController::class, 'update'])
            ->middleware('can:rh.colaboradores.editar')
            ->name('colaboradores.update');

        Route::middleware(['can:rh.horas_extra.ver'])->group(function () {
            Route::get('/horas-extra', [HorasExtraController::class, 'index'])->name('horas_extra.index');
            Route::post('/horas-extra/preview-calculos', [HorasExtraController::class, 'previewCalculos'])->name('horas_extra.preview_calculos');
            Route::get('/horas-extra/{horasExtra}', [HorasExtraController::class, 'show'])->name('horas_extra.show');
            Route::post('/horas-extra', [HorasExtraController::class, 'store'])
                ->middleware('can:rh.horas_extra.crear')
                ->name('horas_extra.store');
            Route::put('/horas-extra/{horasExtra}', [HorasExtraController::class, 'update'])
                ->middleware('can:rh.horas_extra.editar')
                ->name('horas_extra.update');
        });

        Route::middleware(['can:rh.salidas_personales.ver'])->group(function () {
            Route::get('/salidas-personales', [SalidaPersonalController::class, 'index'])->name('salidas_personales.index');
            Route::post('/salidas-personales/preview-calculos', [SalidaPersonalController::class, 'previewCalculos'])->name('salidas_personales.preview_calculos');
            Route::post('/salidas-personales/sellar-periodo', [SalidaPersonalController::class, 'sellarPeriodo'])->name('salidas_personales.sellar_periodo');
            Route::get('/salidas-personales/{salidaPersonal}', [SalidaPersonalController::class, 'show'])->name('salidas_personales.show');
            Route::post('/salidas-personales', [SalidaPersonalController::class, 'store'])
                ->middleware('can:rh.salidas_personales.crear')
                ->name('salidas_personales.store');
            Route::put('/salidas-personales/{salidaPersonal}', [SalidaPersonalController::class, 'update'])
                ->middleware('can:rh.salidas_personales.editar')
                ->name('salidas_personales.update');
            Route::delete('/salidas-personales/{salidaPersonal}', [SalidaPersonalController::class, 'destroy'])
                ->middleware('can:rh.salidas_personales.eliminar')
                ->name('salidas_personales.destroy');
        });

        Route::middleware(['can:rh.incidencias.ver'])->group(function () {
            Route::get('/deducciones', [DeduccionController::class, 'index'])->name('deducciones.index');
            Route::get('/deducciones/incidencias', [DeduccionController::class, 'incidencias'])->name('deducciones.incidencias.index');
            Route::get('/deducciones/pagos-pendientes', [DeduccionController::class, 'pagosPendientes'])->name('deducciones.pagos_pendientes.index');
            Route::get('/deducciones/reglas-disponibles', [DeduccionController::class, 'reglasDisponibles'])->name('deducciones.reglas_disponibles');
            Route::get('/deducciones/buscar-sku', [DeduccionController::class, 'buscarSku'])->name('deducciones.buscar_sku');
            Route::post('/deducciones/preview-calculos', [DeduccionController::class, 'previewCalculos'])->name('deducciones.preview_calculos');
            Route::get('/deducciones/{deduccion}', [DeduccionController::class, 'show'])->name('deducciones.show');
            Route::post('/deducciones', [DeduccionController::class, 'store'])
                ->middleware('can:rh.incidencias.crear')
                ->name('deducciones.store');
            Route::put('/deducciones/{deduccion}', [DeduccionController::class, 'update'])
                ->middleware('can:rh.incidencias.editar')
                ->name('deducciones.update');
            Route::post('/deducciones/{deduccion}/aplicar', [DeduccionController::class, 'aplicar'])
                ->middleware('can:rh.incidencias.aplicar')
                ->name('deducciones.aplicar');
        });

        Route::middleware(['can:rh.recibos.ver'])->group(function () {
            Route::get('/deducciones/{deduccion}/recibo/vista-previa', [ReciboRhController::class, 'incidenciaVistaPrevia'])
                ->name('deducciones.recibo.vista_previa');
        });

        Route::middleware(['can:rh.recibos.generar'])->group(function () {
            Route::get('/deducciones/{deduccion}/recibo', [ReciboRhController::class, 'incidenciaDescargar'])
                ->name('deducciones.recibo');
            Route::post('/deducciones/{deduccion}/recibo/firmar', [ReciboRhController::class, 'incidenciaFirmar'])
                ->name('deducciones.recibo.firmar');
        });

        Route::get('/incidencias', [DeduccionController::class, 'index'])->name('incidencias.index');
        Route::get('/incidencias/{deduccion}', fn ($deduccion) => redirect()->route('rh.deducciones.show', $deduccion))->name('incidencias.show');

        Route::middleware(['can:rh.prestamos.ver'])->group(function () {
            Route::get('/prestamos-pagos-fijos', [PrestamoPagoFijoController::class, 'index'])->name('prestamos.index');
            Route::post('/prestamos-pagos-fijos/generar-cuotas', [PrestamoPagoFijoController::class, 'generarCuotas'])
                ->middleware('can:rh.prestamos.generar')
                ->name('prestamos.generar_cuotas');
            Route::get('/prestamos-pagos-fijos/{prestamo}', [PrestamoPagoFijoController::class, 'show'])->name('prestamos.show');
            Route::post('/prestamos-pagos-fijos', [PrestamoPagoFijoController::class, 'store'])
                ->middleware('can:rh.prestamos.crear')
                ->name('prestamos.store');
            Route::put('/prestamos-pagos-fijos/{prestamo}', [PrestamoPagoFijoController::class, 'update'])
                ->middleware('can:rh.prestamos.editar')
                ->name('prestamos.update');
            Route::post('/prestamos-pagos-fijos/{prestamo}/pausar', [PrestamoPagoFijoController::class, 'pausar'])
                ->middleware('can:rh.prestamos.detener')
                ->name('prestamos.pausar');
            Route::post('/prestamos-pagos-fijos/{prestamo}/reanudar', [PrestamoPagoFijoController::class, 'reanudar'])
                ->middleware('can:rh.prestamos.detener')
                ->name('prestamos.reanudar');
            Route::post('/prestamos-pagos-fijos/{prestamo}/cancelar', [PrestamoPagoFijoController::class, 'cancelar'])
                ->middleware('can:rh.prestamos.detener')
                ->name('prestamos.cancelar');
        });

        Route::middleware(['can:rh.banco_tiempo.ver'])->group(function () {
            Route::get('/banco-tiempo', [BancoTiempoController::class, 'index'])->name('banco_tiempo.index');
            Route::post('/banco-tiempo', [BancoTiempoController::class, 'store'])
                ->middleware('can:rh.banco_tiempo.crear')
                ->name('banco_tiempo.store');
            Route::put('/banco-tiempo/{bancoTiempo}', [BancoTiempoController::class, 'update'])
                ->middleware('can:rh.banco_tiempo.editar')
                ->name('banco_tiempo.update');
            Route::post('/banco-tiempo/{bancoTiempo}/saldar', [BancoTiempoController::class, 'saldar'])
                ->middleware('can:rh.banco_tiempo.saldar')
                ->name('banco_tiempo.saldar');
            Route::delete('/banco-tiempo/{bancoTiempo}', [BancoTiempoController::class, 'destroy'])
                ->middleware('can:rh.banco_tiempo.eliminar')
                ->name('banco_tiempo.destroy');
        });

        Route::middleware(['can:rh.ver'])->group(function () {
            Route::get('/periodo-pago', [PeriodoPagoController::class, 'index'])->name('periodo_pago.index');
            Route::post('/periodo-pago/cerrar', [PeriodoPagoController::class, 'cerrar'])
                ->name('periodo_pago.cerrar');
            Route::get('/periodo-pago/{colaborador}/recibo-nomina/desglose', [ReciboRhController::class, 'nominaDesglose'])
                ->middleware('can:rh.recibos.ver')
                ->name('periodo_pago.recibo_nomina.desglose');
            Route::get('/periodo-pago/{colaborador}/recibo-nomina/vista-previa', [ReciboRhController::class, 'nominaVistaPrevia'])
                ->middleware('can:rh.recibos.ver')
                ->name('periodo_pago.recibo_nomina.vista_previa');
            Route::get('/periodo-pago/{colaborador}/recibo-nomina', [ReciboRhController::class, 'nominaDescargar'])
                ->middleware('can:rh.recibos.generar')
                ->name('periodo_pago.recibo_nomina');
            Route::post('/periodo-pago/{colaborador}/recibo-nomina/firmar', [ReciboRhController::class, 'nominaFirmar'])
                ->middleware('can:rh.recibos.generar')
                ->name('periodo_pago.recibo_nomina.firmar');
            Route::get('/periodo-pago/{colaborador}/recibo-incidencias/vista-previa', [ReciboRhController::class, 'periodoVistaPrevia'])
                ->middleware('can:rh.recibos.ver')
                ->name('periodo_pago.recibo_incidencias.vista_previa');
            Route::get('/periodo-pago/{colaborador}/recibo-incidencias', [ReciboRhController::class, 'periodoDescargar'])
                ->middleware('can:rh.recibos.generar')
                ->name('periodo_pago.recibo_incidencias');
            Route::get('/consolidado-deducciones', [ConsolidadoDeduccionesController::class, 'index'])->name('consolidado_deducciones.index');
            Route::post('/consolidado-deducciones/sellar', [ConsolidadoDeduccionesController::class, 'sellarPeriodo'])->name('consolidado_deducciones.sellar');
            Route::get('/consolidado-horas-extra', [ConsolidadoHorasExtraController::class, 'index'])->name('consolidado_horas_extra.index');
            Route::post('/consolidado-horas-extra/liquidar', [ConsolidadoHorasExtraController::class, 'liquidar'])->name('consolidado_horas_extra.liquidar');
        });

        Route::middleware(['can:rh.configurar'])->group(function () {
            Route::get('/configuracion', [ConfiguracionRhController::class, 'index'])->name('configuracion');
            Route::put('/configuracion', [ConfiguracionRhController::class, 'update'])->name('configuracion.update');
            Route::post('/configuracion/preview-folio', [ConfiguracionRhController::class, 'previewFolio'])->name('configuracion.preview_folio');
            Route::post('/configuracion/periodo-actual', [ConfiguracionRhController::class, 'updatePeriodoActual'])->name('configuracion.periodo_actual.update');
            Route::post('/configuracion/avanzar-periodo', [ConfiguracionRhController::class, 'avanzarPeriodo'])->name('configuracion.periodo_actual.avanzar');
        });

        Route::middleware(['can:rh.catalogos.puestos'])->prefix('catalogos/puestos')->name('catalogos.puestos.')->group(function () {
            Route::post('/', [CatalogoPuestoController::class, 'store'])->name('store');
            Route::put('/{puesto}', [CatalogoPuestoController::class, 'update'])->name('update');
            Route::delete('/{puesto}', [CatalogoPuestoController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('catalogos/turnos')->name('catalogos.turnos.')->group(function () {
            Route::post('/', [\App\Http\Controllers\Rh\CatalogoTurnoController::class, 'store'])->name('store');
            Route::put('/{turno}', [\App\Http\Controllers\Rh\CatalogoTurnoController::class, 'update'])->name('update');
            Route::delete('/{turno}', [\App\Http\Controllers\Rh\CatalogoTurnoController::class, 'destroy'])->name('destroy');
        });

        Route::middleware(['can:rh.catalogos.tipos_faltas'])->prefix('catalogos/tipos-faltas')->name('catalogos.tipos_faltas.')->group(function () {
            Route::post('/', [CatalogoTipoFaltaController::class, 'store'])->name('store');
            Route::put('/{tipoFalta}', [CatalogoTipoFaltaController::class, 'update'])->name('update');
            Route::delete('/{tipoFalta}', [CatalogoTipoFaltaController::class, 'destroy'])->name('destroy');
        });

        Route::middleware(['can:rh.catalogos.bonos'])->prefix('catalogos/bonos')->name('catalogos.bonos.')->group(function () {
            Route::post('/', [CatalogoBonoController::class, 'store'])->name('store');
            Route::put('/{bono}', [CatalogoBonoController::class, 'update'])->name('update');
            Route::delete('/{bono}', [CatalogoBonoController::class, 'destroy'])->name('destroy');
        });

        Route::middleware(['can:rh.catalogos.incidencias_generales'])->prefix('catalogos/reglas-incidencia')->name('catalogos.reglas_incidencia.')->group(function () {
            Route::post('/', [CatalogoReglaIncidenciaController::class, 'store'])->name('store');
            Route::put('/{reglaIncidencia}', [CatalogoReglaIncidenciaController::class, 'update'])->name('update');
            Route::delete('/{reglaIncidencia}', [CatalogoReglaIncidenciaController::class, 'destroy'])->name('destroy');
        });
    });

    Route::middleware(['auth'])->prefix('rh')->name('rh.')->group(function () {
        Route::middleware(['can:rh.incidencias.gerente.ver'])->prefix('incidencias-gerente')->name('incidencias_gerente.')->group(function () {
            Route::get('/', [IncidenciaGerenteController::class, 'index'])->name('index');
            Route::get('/reglas-disponibles', [DeduccionController::class, 'reglasDisponibles'])->name('reglas_disponibles');
            Route::get('/crear', [IncidenciaGerenteController::class, 'create'])
                ->middleware('can:rh.incidencias.gerente.crear')
                ->name('create');
            Route::post('/', [IncidenciaGerenteController::class, 'store'])
                ->middleware('can:rh.incidencias.gerente.crear')
                ->name('store');
            Route::get('/deducciones/{deduccion}', [DeduccionController::class, 'show'])->name('deducciones.show');
        });

        Route::middleware(['can:rh.recibos.ver'])->group(function () {
            Route::get('/incidencias-gerente/deducciones/{deduccion}/recibo/vista-previa', [ReciboRhController::class, 'incidenciaVistaPrevia'])
                ->name('incidencias_gerente.deducciones.recibo.vista_previa');
        });

        Route::middleware(['can:rh.recibos.generar'])->group(function () {
            Route::get('/incidencias-gerente/deducciones/{deduccion}/recibo', [ReciboRhController::class, 'incidenciaDescargar'])
                ->name('incidencias_gerente.deducciones.recibo');
            Route::post('/incidencias-gerente/deducciones/{deduccion}/recibo/firmar', [ReciboRhController::class, 'incidenciaFirmar'])
                ->name('incidencias_gerente.deducciones.recibo.firmar');
        });
    });


    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO DE ADMINISTRACIÓN (GELIANV CORE)
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('admin')->name('admin.')->group(function () {

        Route::get('/', [AdminController::class, 'panel'])->name('index');

        // --- Configuración Global del Sistema ---
        Route::middleware(['can:configuracion_sistema.gestionar'])->prefix('configuracion-sistema')->name('configuracion_sistema.')->group(function () {
            Route::get('/', [ConfiguracionSistemaController::class, 'index'])->name('index');
            Route::post('/', [ConfiguracionSistemaController::class, 'store'])->name('store');
            Route::put('/{configuracion}', [ConfiguracionSistemaController::class, 'update'])->name('update');
            Route::delete('/{configuracion}', [ConfiguracionSistemaController::class, 'destroy'])->name('destroy');
            Route::post('/test-mail', [ConfiguracionSistemaController::class, 'testMail'])->name('test_mail');
        });

        // --- Monitoreo de Mensajería ---
        Route::middleware(['can:mensajeria.monitorear'])->prefix('mensajeria-monitoreo')->name('mensajeria_monitoreo.')->group(function () {
            Route::get('/', [MonitoreoMensajeriaController::class, 'index'])->name('index');
            Route::get('/conversaciones', [MonitoreoMensajeriaController::class, 'conversaciones'])->name('conversaciones');
            Route::get('/conversaciones/{conversacion}/mensajes', [MonitoreoMensajeriaController::class, 'mensajes'])->name('mensajes');
            Route::delete('/conversaciones/{conversacion}', [MonitoreoMensajeriaController::class, 'destroyConversacion'])->name('conversaciones.destroy');
            Route::delete('/mensajes/{mensaje}', [MonitoreoMensajeriaController::class, 'destroyMensaje'])->name('mensajes.destroy');
        });

        // --- 1. Gestión de Usuarios ---
        Route::middleware(['can:usuarios.gestionar'])->group(function () {
            Route::get('/usuarios', [AdminController::class, 'usuarios'])->name('usuarios');
            Route::post('/usuarios', [AdminController::class, 'storeUsuario'])->name('usuarios.store');
            Route::put('/usuarios/{user}', [AdminController::class, 'updateUsuario'])->name('usuarios.update');

            Route::put('/roles/{role}/permisos-heredados', [AdminController::class, 'updateRolePermisosHerencia'])->name('roles.permisos.update');
            Route::post('/roles/grupos', [AdminController::class, 'storeGrupoPredefinido'])->name('roles.grupos.store');
        });

        Route::middleware(['can:usuarios.archivar'])->group(function () {
            Route::delete('/usuarios/{user}/archivar', [AdminController::class, 'archivarUsuario'])->name('usuarios.archivar');
        });

        // --- 1b. Generación de Accesos (permiso separado) ---
        Route::middleware(['can:usuarios.generar_permisos'])->group(function () {
            Route::get('/enlaces', [AdminController::class, 'enlaces'])->name('enlaces');
            Route::post('/generar-enlace-registro', [RegistroController::class, 'generarEnlaceInvitacion'])->name('enlaces.generar');
        });

        // --- 2. Base de Clientes ---
        Route::middleware(['can:clientes.ver'])->group(function () {
            Route::get('/clientes', [AdminController::class, 'clientes'])->name('clientes');
            Route::post('/clientes/importar', [AdminController::class, 'importarClientes'])->name('clientes.importar');
            
            // --- NUEVAS RUTAS PARA EL BLINDAJE DE LISTAS ---
            Route::get('/clientes/especiales/protegidos', [ClienteController::class, 'obtenerEspeciales'])->name('clientes.especiales');
            Route::post('/clientes/toggle-bloqueo', [ClienteController::class, 'toggleBloqueoLista'])->name('clientes.toggle_bloqueo');
            Route::post('/clientes/toggle-bloqueo-masivo', [ClienteController::class, 'toggleBloqueoMasivo'])->name('clientes.toggle_bloqueo_masivo');
            // -----------------------------------------------

            Route::get('/clientes/auditoria/datos', [\App\Http\Controllers\Admin\AuditoriaMontosClienteController::class, 'datosAuditoria'])->name('clientes.auditoria.datos');
            Route::get('/clientes/importaciones/{importacion}/auditoria', [\App\Http\Controllers\Admin\AuditoriaMontosClienteController::class, 'auditoriaImportacion'])->name('clientes.importaciones.auditoria');
            Route::get('/clientes/importaciones/{importacion}/archivo', [\App\Http\Controllers\Admin\AuditoriaMontosClienteController::class, 'descargarArchivo'])->name('clientes.importaciones.archivo');
            Route::get('/clientes/{cliente}/historial', [AdminController::class, 'historialCliente'])->name('clientes.historial');
            Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
            Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        });

        // --- 3. Catálogos Globales (Acceso Estricto Administrativo) ---
        Route::middleware(['can:catalogos.gestionar'])->group(function () {
            Route::get('/catalogos', [AdminController::class, 'catalogos'])->name('catalogos');

            // --- Catálogo Maestro (legacy → Almacenes) ---
            Route::redirect('/catalogo-maestro', '/almacenes/inventarios')->name('catalogo-maestro.index');
            Route::redirect('/catalogo-maestro/import-preview', '/almacenes/inventarios');
            Route::redirect('/catalogo-maestro/import-process', '/almacenes/inventarios');

            Route::prefix('catalogos')->name('catalogos.')->group(function () {
                // Departamentos
                Route::post('/departamentos', [CatalogoController::class, 'storeDepartamento'])->name('departamentos.store');
                Route::put('/departamentos/{id}', [CatalogoController::class, 'updateDepartamento'])->name('departamentos.update');
                Route::delete('/departamentos/{id}', [CatalogoController::class, 'destroyDepartamento'])->name('departamentos.destroy');

                // Áreas
                Route::post('/areas', [CatalogoController::class, 'storeArea'])->name('areas.store');
                Route::put('/areas/{id}', [CatalogoController::class, 'updateArea'])->name('areas.update');
                Route::delete('/areas/{id}', [CatalogoController::class, 'destroyArea'])->name('areas.destroy');

                // Procesos
                Route::post('/procesos', [CatalogoController::class, 'storeProceso'])->name('procesos.store');
                Route::put('/procesos/{id}', [CatalogoController::class, 'updateProceso'])->name('procesos.update');
                Route::delete('/procesos/{id}', [CatalogoController::class, 'destroyProceso'])->name('procesos.destroy');

                // Listas
                Route::post('/listas', [CatalogoController::class, 'storeLista'])->name('listas.store');
                Route::put('/listas/{id}', [CatalogoController::class, 'updateLista'])->name('listas.update');
                Route::delete('/listas/{id}', [CatalogoController::class, 'destroyLista'])->name('listas.destroy');

                // Estados
                Route::post('/estados', [CatalogoController::class, 'storeEstado'])->name('estados.store');
                Route::put('/estados/{id}', [CatalogoController::class, 'updateEstado'])->name('estados.update');
                Route::delete('/estados/{id}', [CatalogoController::class, 'destroyEstado'])->name('estados.destroy');

                // Tipo de Clientes
                Route::post('/tipo-clientes', [CatalogoController::class, 'storeTipoCliente'])->name('tipo_clientes.store');
                Route::put('/tipo-clientes/{id}', [CatalogoController::class, 'updateTipoCliente'])->name('tipo_clientes.update');
                Route::delete('/tipo-clientes/{id}', [CatalogoController::class, 'destroyTipoCliente'])->name('tipo_clientes.destroy');

                // Zonas Logísticas (Entregas)
                Route::put('/zonas-entrega/{id}', [CatalogoController::class, 'updateZonaEntrega'])->name('zonas_entrega.update')->middleware('can:entregas.configurar_zonas');
                Route::delete('/zonas-entrega/{id}', [CatalogoController::class, 'destroyZonaEntrega'])->name('zonas_entrega.destroy')->middleware('can:entregas.configurar_zonas');

                // Horarios de Entrega
                Route::post('/horarios-entrega', [CatalogoController::class, 'storeHorarioEntrega'])->name('horarios_entrega.store')->middleware('can:entregas.configurar_zonas');
                Route::put('/horarios-entrega/{id}', [CatalogoController::class, 'updateHorarioEntrega'])->name('horarios_entrega.update')->middleware('can:entregas.configurar_zonas');
                Route::delete('/horarios-entrega/{id}', [CatalogoController::class, 'destroyHorarioEntrega'])->name('horarios_entrega.destroy')->middleware('can:entregas.configurar_zonas');

                // Porcentajes Escalonamiento (solicitudes)
                Route::post('/porcentajes-escalonamiento', [CatalogoController::class, 'storePorcentajeEscalonamiento'])->name('porcentajes_escalonamiento.store');
                Route::put('/porcentajes-escalonamiento/{id}', [CatalogoController::class, 'updatePorcentajeEscalonamiento'])->name('porcentajes_escalonamiento.update');
                Route::delete('/porcentajes-escalonamiento/{id}', [CatalogoController::class, 'destroyPorcentajeEscalonamiento'])->name('porcentajes_escalonamiento.destroy');

                // Porcentajes Listado (resurtido / Excel)
                Route::post('/porcentajes-listado', [CatalogoController::class, 'storePorcentajeListado'])->name('porcentajes_listado.store');
                Route::put('/porcentajes-listado/{id}', [CatalogoController::class, 'updatePorcentajeListado'])->name('porcentajes_listado.update');
                Route::delete('/porcentajes-listado/{id}', [CatalogoController::class, 'destroyPorcentajeListado'])->name('porcentajes_listado.destroy');

                // Bancos
                Route::post('/bancos', [CatalogoController::class, 'storeBanco'])->name('bancos.store');
                Route::put('/bancos/{id}', [CatalogoController::class, 'updateBanco'])->name('bancos.update');
                Route::delete('/bancos/{id}', [CatalogoController::class, 'destroyBanco'])->name('bancos.destroy');

                // Control Pedidos — catálogos
                Route::middleware(['can:control_pedidos.configurar_catalogos'])->group(function () {
                    Route::post('/estatus-pedidos', [CatalogoController::class, 'storeEstatusPedido'])->name('estatus_pedidos.store');
                    Route::put('/estatus-pedidos/{id}', [CatalogoController::class, 'updateEstatusPedido'])->name('estatus_pedidos.update');
                    Route::delete('/estatus-pedidos/{id}', [CatalogoController::class, 'destroyEstatusPedido'])->name('estatus_pedidos.destroy');

                    Route::post('/paqueterias-pedido', [CatalogoController::class, 'storePaqueteriaPedido'])->name('paqueterias_pedido.store');
                    Route::put('/paqueterias-pedido/{id}', [CatalogoController::class, 'updatePaqueteriaPedido'])->name('paqueterias_pedido.update');
                    Route::delete('/paqueterias-pedido/{id}', [CatalogoController::class, 'destroyPaqueteriaPedido'])->name('paqueterias_pedido.destroy');

                    Route::post('/tipos-caja-pedido', [CatalogoController::class, 'storeTipoCajaPedido'])->name('tipos_caja_pedido.store');
                    Route::put('/tipos-caja-pedido/{id}', [CatalogoController::class, 'updateTipoCajaPedido'])->name('tipos_caja_pedido.update');
                    Route::delete('/tipos-caja-pedido/{id}', [CatalogoController::class, 'destroyTipoCajaPedido'])->name('tipos_caja_pedido.destroy');

                    Route::post('/tipos-guia-pedido', [CatalogoController::class, 'storeTipoGuiaPedido'])->name('tipos_guia_pedido.store');
                    Route::put('/tipos-guia-pedido/{id}', [CatalogoController::class, 'updateTipoGuiaPedido'])->name('tipos_guia_pedido.update');
                    Route::delete('/tipos-guia-pedido/{id}', [CatalogoController::class, 'destroyTipoGuiaPedido'])->name('tipos_guia_pedido.destroy');

                    Route::post('/zonas-pedido', [CatalogoController::class, 'storeZonaPedido'])->name('zonas_pedido.store');
                    Route::put('/zonas-pedido/{id}', [CatalogoController::class, 'updateZonaPedido'])->name('zonas_pedido.update');
                    Route::delete('/zonas-pedido/{id}', [CatalogoController::class, 'destroyZonaPedido'])->name('zonas_pedido.destroy');

                    Route::post('/envios-tienda', [CatalogoController::class, 'storeEnvioTienda'])->name('envios_tienda.store');
                    Route::put('/envios-tienda/{id}', [CatalogoController::class, 'updateEnvioTienda'])->name('envios_tienda.update');
                    Route::delete('/envios-tienda/{id}', [CatalogoController::class, 'destroyEnvioTienda'])->name('envios_tienda.destroy');

                    Route::post('/origenes-pedido', [CatalogoController::class, 'storeOrigenPedido'])->name('origenes_pedido.store');
                    Route::put('/origenes-pedido/{id}', [CatalogoController::class, 'updateOrigenPedido'])->name('origenes_pedido.update');
                    Route::delete('/origenes-pedido/{id}', [CatalogoController::class, 'destroyOrigenPedido'])->name('origenes_pedido.destroy');
                });

                // Sucursales
                Route::get('/sucursales/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'sucursales')->name('sucursales.plantilla_importacion');
                Route::post('/sucursales/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'sucursales')->name('sucursales.importar');
                Route::post('/sucursales', [CatalogoController::class, 'storeSucursal'])->name('sucursales.store');
                Route::put('/sucursales/{id}', [CatalogoController::class, 'updateSucursal'])->name('sucursales.update');
                Route::delete('/sucursales/{id}', [CatalogoController::class, 'destroySucursal'])->name('sucursales.destroy');

                // Tipos de Almacén
                Route::get('/tipos-almacen/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'tipos_almacen')->name('tipos_almacen.plantilla_importacion');
                Route::post('/tipos-almacen/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'tipos_almacen')->name('tipos_almacen.importar');
                Route::post('/tipos-almacen', [CatalogoController::class, 'storeTipoAlmacen'])->name('tipos_almacen.store');
                Route::put('/tipos-almacen/{id}', [CatalogoController::class, 'updateTipoAlmacen'])->name('tipos_almacen.update');
                Route::delete('/tipos-almacen/{id}', [CatalogoController::class, 'destroyTipoAlmacen'])->name('tipos_almacen.destroy');

                // Marcas de Producto
                Route::get('/marcas-producto/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'marcas_producto')->name('marcas_producto.plantilla_importacion');
                Route::post('/marcas-producto/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'marcas_producto')->name('marcas_producto.importar');
                Route::post('/marcas-producto', [CatalogoController::class, 'storeMarcaProducto'])->name('marcas_producto.store');
                Route::put('/marcas-producto/{id}', [CatalogoController::class, 'updateMarcaProducto'])->name('marcas_producto.update');
                Route::delete('/marcas-producto/{id}', [CatalogoController::class, 'destroyMarcaProducto'])->name('marcas_producto.destroy');

                // Almacenes
                Route::get('/almacenes/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'almacenes')->name('almacenes.plantilla_importacion');
                Route::post('/almacenes/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'almacenes')->name('almacenes.importar');
                Route::post('/almacenes', [CatalogoController::class, 'storeAlmacen'])->name('almacenes.store');
                Route::put('/almacenes/{id}', [CatalogoController::class, 'updateAlmacen'])->name('almacenes.update');
                Route::delete('/almacenes/{id}', [CatalogoController::class, 'destroyAlmacen'])->name('almacenes.destroy');

                // Categorías de Producto
                Route::get('/categorias-producto/plantilla-importacion', [CatalogoController::class, 'descargarPlantillaImportacion'])->defaults('tipo', 'categorias_producto')->name('categorias_producto.plantilla_importacion');
                Route::post('/categorias-producto/importar', [CatalogoController::class, 'importarCatalogoAlmacen'])->defaults('tipo', 'categorias_producto')->name('categorias_producto.importar');
                Route::post('/categorias-producto', [CatalogoController::class, 'storeCategoriaProducto'])->name('categorias_producto.store');
                Route::put('/categorias-producto/{id}', [CatalogoController::class, 'updateCategoriaProducto'])->name('categorias_producto.update');
                Route::delete('/categorias-producto/{id}', [CatalogoController::class, 'destroyCategoriaProducto'])->name('categorias_producto.destroy');

                // Tipos de Activo
                Route::post('/tipos-activo', [TipoActivoController::class, 'store'])->name('tipos_activo.store')->middleware('can:activos.configurar_tipos');
                Route::put('/tipos-activo/{id}', [TipoActivoController::class, 'update'])->name('tipos_activo.update')->middleware('can:activos.configurar_tipos');
                Route::delete('/tipos-activo/{id}', [TipoActivoController::class, 'destroy'])->name('tipos_activo.destroy')->middleware('can:activos.configurar_tipos');

                Route::post('/categorias-activo', [CategoriaActivoController::class, 'store'])->name('categorias_activo.store')->middleware('can:activos.configurar_tipos');
                Route::put('/categorias-activo/{id}', [CategoriaActivoController::class, 'update'])->name('categorias_activo.update')->middleware('can:activos.configurar_tipos');
                Route::delete('/categorias-activo/{id}', [CategoriaActivoController::class, 'destroy'])->name('categorias_activo.destroy')->middleware('can:activos.configurar_tipos');
            });
        });

        // --- 4. Comisiones ---
        Route::middleware(['can:comisiones.gestionar'])->group(function () {
            Route::get('/comisiones', [AdminController::class, 'comisiones'])->name('comisiones');
            Route::put('/comisiones/{id}', [AdminController::class, 'actualizarComision'])->name('comisiones.update');
        });

        // --- 5. Gestor de Personalización ---
        Route::middleware(['can:personalizacion.gestionar'])->prefix('personalizacion')->name('personalizacion.')->group(function () {
            Route::get('/', [PersonalizacionController::class, 'index'])->name('index');

            Route::post('/tonos', [PersonalizacionController::class, 'storeTono'])->name('tonos.store');
            Route::post('/tonos/{id}', [PersonalizacionController::class, 'updateTono'])->name('tonos.update');
            Route::delete('/tonos/{id}', [PersonalizacionController::class, 'destroyTono'])->name('tonos.destroy');

            Route::post('/fondos', [PersonalizacionController::class, 'storeFondo'])->name('fondos.store');
            Route::post('/fondos/{id}', [PersonalizacionController::class, 'updateFondo'])->name('fondos.update');
            Route::delete('/fondos/{id}', [PersonalizacionController::class, 'destroyFondo'])->name('fondos.destroy');

            Route::post('/temas', [PersonalizacionController::class, 'storeTema'])->name('temas.store');
            Route::put('/temas/{id}', [PersonalizacionController::class, 'updateTema'])->name('temas.update');
            Route::delete('/temas/{id}', [PersonalizacionController::class, 'destroyTema'])->name('temas.destroy');
        });

        // --- 5. Auditorías del Sistema ---
        Route::middleware(['permission:sistema.auditorias.ver|sistema.auditorias.accesos.ver'])->group(function () {
            Route::get('/auditorias-sistema', [AuditoriaListaDescuentoController::class, 'index'])
                ->name('auditorias_sistema.index');
        });

        // --- 6. Mapa logístico (zonas de entrega) ---
        Route::middleware(['can:entregas.configurar_zonas'])->prefix('mapa-logistico')->name('mapa_logistico.')->group(function () {
            Route::get('/', [MapaLogisticoController::class, 'index'])->name('index');
            Route::get('/exportar/{tipo}', [MapaLogisticoController::class, 'exportar'])->name('exportar');
            Route::post('/importar/{tipo}', [MapaLogisticoController::class, 'importar'])->name('importar');
            Route::post('/{tipo}', [MapaLogisticoController::class, 'store'])->name('store');
            Route::put('/{tipo}/{id}/poligono', [MapaLogisticoController::class, 'actualizarPoligono'])->name('poligono.update');
            Route::put('/periferia/{id}', [MapaLogisticoController::class, 'actualizarPeriferia'])->name('periferia.update');
            Route::put('/{tipo}/{id}/activo', [MapaLogisticoController::class, 'toggleActivo'])->name('toggle');
            Route::delete('/{tipo}/{id}', [MapaLogisticoController::class, 'eliminar'])->name('eliminar');
        });

        // --- 7. API Externa ---
        Route::prefix('api-externa')->name('api_externa.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ApiExternaController::class, 'index'])->name('index');
            Route::middleware(['can:api_externa.gestionar'])->group(function () {
                Route::post('/aplicaciones', [\App\Http\Controllers\Admin\ApiExternaController::class, 'storeAplicacion'])->name('aplicaciones.store');
                Route::put('/aplicaciones/{aplicacion}', [\App\Http\Controllers\Admin\ApiExternaController::class, 'updateAplicacion'])->name('aplicaciones.update');
                Route::post('/aplicaciones/{aplicacion}/regenerar-secret', [\App\Http\Controllers\Admin\ApiExternaController::class, 'regenerarSecret'])->name('aplicaciones.regenerar_secret');
                Route::post('/aplicaciones/{aplicacion}/revocar-tokens', [\App\Http\Controllers\Admin\ApiExternaController::class, 'revocarTokens'])->name('aplicaciones.revocar_tokens');
                Route::delete('/aplicaciones/{aplicacion}', [\App\Http\Controllers\Admin\ApiExternaController::class, 'destroyAplicacion'])->name('aplicaciones.destroy');
                Route::put('/recursos/{recurso}', [\App\Http\Controllers\Admin\ApiExternaController::class, 'updateRecurso'])->name('recursos.update');
                Route::put('/campos/{campo}', [\App\Http\Controllers\Admin\ApiExternaController::class, 'updateCampo'])->name('campos.update');
                Route::put('/permisos/{permiso}', [\App\Http\Controllers\Admin\ApiExternaController::class, 'updatePermiso'])->name('permisos.update');
                Route::put('/aplicacion-campos/{campo}', [\App\Http\Controllers\Admin\ApiExternaController::class, 'updateCampoAplicacion'])->name('aplicacion_campos.update');
                Route::get('/documentacion/pdf', [\App\Http\Controllers\Admin\ApiExternaController::class, 'descargarDocumentacion'])->name('documentacion.pdf');
            });
        });
    });

    // ══════════════════════════════════════════════════════════════════════
    // GESTIÓN INTERNA
    // ══════════════════════════════════════════════════════════════════════
    Route::middleware(['can:gestion_interna.directorio.ver'])->prefix('gestion-interna')->name('gestion_interna.')->group(function () {
        Route::get('/directorio', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'index'])->name('directorio.index');
        
        Route::post('/directorio/correos', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'storeCorreo'])->name('directorio.correos.store');
        Route::put('/directorio/correos/{id}', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'updateCorreo'])->name('directorio.correos.update');
        Route::delete('/directorio/correos/{id}', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'destroyCorreo'])->name('directorio.correos.destroy');
        
        Route::post('/directorio/telefonos', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'storeTelefono'])->name('directorio.telefonos.store');
        Route::put('/directorio/telefonos/{id}', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'updateTelefono'])->name('directorio.telefonos.update');
        Route::delete('/directorio/telefonos/{id}', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'destroyTelefono'])->name('directorio.telefonos.destroy');
        
        Route::post('/directorio/extensiones', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'storeExtension'])->name('directorio.extensiones.store');
        Route::put('/directorio/extensiones/{id}', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'updateExtension'])->name('directorio.extensiones.update');
        Route::delete('/directorio/extensiones/{id}', [App\Http\Controllers\GestionInterna\DirectorioController::class, 'destroyExtension'])->name('directorio.extensiones.destroy');
    });

    // ══════════════════════════════════════════════════════════════════════
    // API INTERNA DEL SISTEMA
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/clientes', [ClienteApiController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/id/{id}/direccion-envio', [ClienteApiController::class, 'direccionEnvio'])->name('clientes.direccion_envio');
        Route::get('/clientes/{numero}', [ClienteApiController::class, 'show'])->name('clientes.show');
        Route::get('/activos/usuarios', [ActivoController::class, 'buscarUsuarios'])->name('activos.usuarios');
        Route::get('/activos/buscar', [ActivoController::class, 'buscarActivos'])->name('activos.buscar');
        Route::get('/activos/marcas', [ActivoController::class, 'buscarMarcas'])->name('activos.marcas');
        Route::get('/activos/modelos', [ActivoController::class, 'buscarModelos'])->name('activos.modelos');
        Route::middleware(['auth'])->post('/entregas/cotizar', [CotizacionEntregaController::class, 'calcularCosto'])->name('entregas.cotizar');
    });
});

require __DIR__ . '/soporte.php';
