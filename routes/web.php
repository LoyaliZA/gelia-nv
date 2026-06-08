<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\{LoginController,RegistroController};
use App\Http\Controllers\{DashboardController,AdminController,CatalogoController,ProductoCatalogoController,ClienteController,AutoCobranzaController,ProfileController};
use App\Http\Controllers\Solicitudes\SolicitudController;
use App\Http\Controllers\Api\{CotizacionEntregaController,ClienteApiController};
use App\Http\Controllers\EntregasController;
use App\Http\Controllers\MapaLogisticoController;
use App\Http\Controllers\Admin\{AuditoriaListaDescuentoController,PersonalizacionController,ConfiguracionSistemaController};
use App\Http\Controllers\AromasListasController;
use App\Http\Controllers\Activos\{ActivoController,CategoriaActivoController,TipoActivoController};
use App\Http\Controllers\Rh\{ColaboradorController,ConfiguracionRhController,CatalogoPuestoController,CatalogoTipoFaltaController,CatalogoBonoController,CatalogoReglaIncidenciaController,DashboardRhController,HorasExtraController,DeduccionController,PeriodoPagoController,PrestamoPagoFijoController,SalidaPersonalController,ConsolidadoDeduccionesController};
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\Facturas\{SolicitudFacturaController,DatosFiscalesController,ArchivoFacturaController};
use App\Http\Controllers\CancelacionesCotizaciones\SolicitudOperativaController;
use App\Http\Controllers\Mensajeria\{ConversacionController,MensajeController,AdjuntoMensajeController};
use App\Http\Controllers\WebPushController;

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
        ->middleware('signed');

    Route::post('/registro/colaborador', [RegistroController::class, 'almacenar'])
        ->name('registro.store')
        ->middleware('signed');
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
    // MÓDULO: AUTO-COBRANZA
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('auto-cobranza')->name('auto-cobranza.')->group(function () {
        Route::get('/', [AutoCobranzaController::class, 'index'])->name('index');
        Route::post('/importar', [AutoCobranzaController::class, 'importarReporte'])->name('importar');
        Route::put('/alertas/{alerta}', [AutoCobranzaController::class, 'actualizarAlerta'])->name('alertas.update');
        Route::get('/clientes/{clienteId}/bitacora', [AutoCobranzaController::class, 'bitacora'])->name('bitacora');
        Route::get('/historial', [AutoCobranzaController::class, 'historial'])->name('historial');
        Route::get('/abonos-hoy', [AutoCobranzaController::class, 'abonosDelDia'])->name('abonos-hoy');
        Route::post('/clientes/{cliente}/resolver-aumento', [AutoCobranzaController::class, 'resolverAumento'])->name('alertas.resolver-aumento');
        Route::put('/clientes/{cliente}/reparar-fecha', [AutoCobranzaController::class, 'repararFechaInicio'])->name('clientes.reparar-fecha');
        Route::post('/facturas/{factura}/verificar', [AutoCobranzaController::class, 'verificarPago'])->name('facturas.verificar');
        Route::post('/configuracion', [AutoCobranzaController::class, 'guardarConfiguracion'])->name('configuracion.store');
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
    Route::post('/solicitudes/{solicitud}/consultas', [SolicitudController::class, 'storeConsulta'])->middleware('can:solicitudes.consultar')->name('solicitudes.consultas.store');
    Route::put('/solicitudes/{solicitud}/consultas/{consulta}', [SolicitudController::class, 'responderConsulta'])->name('solicitudes.consultas.responder');
    Route::put('/solicitudes/{solicitud}/consultas/{consulta}/leer', [SolicitudController::class, 'marcarConsultaLeida'])->name('solicitudes.consultas.leer');
    Route::delete('/solicitudes/{solicitud}', [SolicitudController::class, 'destroy'])->name('solicitudes.destroy');

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

        Route::redirect('/incidencias', '/rh/deducciones')->name('incidencias.index');
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
            Route::get('/consolidado-deducciones', [ConsolidadoDeduccionesController::class, 'index'])->name('consolidado_deducciones.index');
            Route::post('/consolidado-deducciones/sellar', [ConsolidadoDeduccionesController::class, 'sellarPeriodo'])->name('consolidado_deducciones.sellar');
            Route::get('/consolidado-horas-extra', [ConsolidadoHorasExtraController::class, 'index'])->name('consolidado_horas_extra.index');
            Route::post('/consolidado-horas-extra/liquidar', [ConsolidadoHorasExtraController::class, 'liquidar'])->name('consolidado_horas_extra.liquidar');
        });

        Route::middleware(['can:rh.configurar'])->group(function () {
            Route::get('/configuracion', [ConfiguracionRhController::class, 'index'])->name('configuracion');
            Route::put('/configuracion', [ConfiguracionRhController::class, 'update'])->name('configuracion.update');
            Route::post('/configuracion/preview-folio', [ConfiguracionRhController::class, 'previewFolio'])->name('configuracion.preview_folio');
        });

        Route::middleware(['can:rh.catalogos.puestos'])->prefix('catalogos/puestos')->name('catalogos.puestos.')->group(function () {
            Route::post('/', [CatalogoPuestoController::class, 'store'])->name('store');
            Route::put('/{puesto}', [CatalogoPuestoController::class, 'update'])->name('update');
            Route::delete('/{puesto}', [CatalogoPuestoController::class, 'destroy'])->name('destroy');
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

        // --- 1. Gestión de Usuarios ---
        Route::middleware(['can:usuarios.gestionar'])->group(function () {
            Route::get('/usuarios', [AdminController::class, 'usuarios'])->name('usuarios');
            Route::post('/usuarios', [AdminController::class, 'storeUsuario'])->name('usuarios.store');
            Route::put('/usuarios/{user}', [AdminController::class, 'updateUsuario'])->name('usuarios.update');

            Route::put('/roles/{role}/permisos-heredados', [AdminController::class, 'updateRolePermisosHerencia'])->name('roles.permisos.update');
            Route::post('/roles/grupos', [AdminController::class, 'storeGrupoPredefinido'])->name('roles.grupos.store');
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

            Route::get('/clientes/{cliente}/historial', [AdminController::class, 'historialCliente'])->name('clientes.historial');
            Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
            Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        });

        // --- 3. Catálogos Globales (Acceso Estricto Administrativo) ---
        Route::middleware(['can:catalogos.gestionar'])->group(function () {
            Route::get('/catalogos', [AdminController::class, 'catalogos'])->name('catalogos');

            // --- Catálogo Maestro Sprint 2.1 ---
            Route::get('/catalogo-maestro', [ProductoCatalogoController::class, 'index'])->name('catalogo-maestro.index');
            Route::post('/catalogo-maestro/import-preview', [ProductoCatalogoController::class, 'importPreview'])->name('catalogo-maestro.import_preview');
            Route::post('/catalogo-maestro/import-process', [ProductoCatalogoController::class, 'importProcess'])->name('catalogo-maestro.import_process');

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

                // Productos / Inventario
                Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
                Route::put('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
                Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->name('productos.destroy');
                Route::post('/productos/import', [ProductoController::class, 'importar'])->name('productos.import');

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
        Route::middleware(['can:sistema.auditorias.ver'])->group(function () {
            // Corrección: Cambiar el llamado de 'auditorias' a 'index'
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
    // API INTERNA DEL SISTEMA
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/clientes', [ClienteApiController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/{numero}', [ClienteApiController::class, 'show'])->name('clientes.show');
        Route::get('/activos/usuarios', [ActivoController::class, 'buscarUsuarios'])->name('activos.usuarios');
        Route::get('/activos/buscar', [ActivoController::class, 'buscarActivos'])->name('activos.buscar');
        Route::get('/activos/marcas', [ActivoController::class, 'buscarMarcas'])->name('activos.marcas');
        Route::get('/activos/modelos', [ActivoController::class, 'buscarModelos'])->name('activos.modelos');
        Route::middleware(['auth'])->post('/entregas/cotizar', [CotizacionEntregaController::class, 'calcularCosto'])->name('entregas.cotizar');
    });
});
