<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Solicitudes\SolicitudController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\CotizacionEntregaController;
use App\Http\Controllers\Api\ClienteApiController;
use App\Http\Controllers\EntregasController;
use App\Http\Controllers\Admin\AuditoriaListaDescuentoController;
use App\Http\Controllers\Admin\PersonalizacionController;
use App\Http\Controllers\AromasListasController;
use App\Http\Controllers\Activos\ActivoController;
use App\Http\Controllers\Activos\TipoActivoController;
use App\Http\Controllers\Facturas\SolicitudFacturaController;
use App\Http\Controllers\Facturas\DatosFiscalesController;
use App\Http\Controllers\Facturas\ArchivoFacturaController;
use App\Http\Controllers\CancelacionesCotizaciones\SolicitudOperativaController;
use App\Http\Controllers\Mensajeria\ConversacionController;
use App\Http\Controllers\Mensajeria\MensajeController;
use App\Http\Controllers\Mensajeria\AdjuntoMensajeController;

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
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/notificaciones/{id}/leer', [AdminController::class, 'marcarNotificacionLeida'])->name('notifications.read');
    Route::post('/notificaciones/limpiar', [AdminController::class, 'limpiarNotificaciones'])->name('notifications.clear');

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

    Route::middleware(['can:solicitudes.ver_listado'])->group(function () {
        Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
        Route::get('/solicitudes/exportar', [SolicitudController::class, 'exportar'])->name('solicitudes.exportar');
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
    });

    Route::middleware(['can:facturas.ver_listado'])->prefix('facturas')->name('facturas.')->group(function () {
        Route::get('/', [SolicitudFacturaController::class, 'index'])->name('index');
        Route::get('/exportar', [SolicitudFacturaController::class, 'exportar'])->middleware('can:facturas.exportar')->name('exportar');
        Route::get('/{factura}/datos-fiscales', [SolicitudFacturaController::class, 'datosFiscales'])->name('datos_fiscales');
        Route::get('/{factura}/archivo/{tipo}', [ArchivoFacturaController::class, 'show'])->name('archivo');
        Route::get('/{factura}', [SolicitudFacturaController::class, 'show'])->name('show');
    });

    Route::middleware(['can:facturas.responder'])->prefix('facturas')->name('facturas.')->group(function () {
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

    Route::middleware(['can:entregas.configurar_zonas'])->prefix('admin/mapa-logistico')->name('admin.mapa_logistico.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MapaLogisticoController::class, 'index'])->name('index');
        Route::get('/exportar/{tipo}', [\App\Http\Controllers\MapaLogisticoController::class, 'exportar'])->name('exportar');
        Route::post('/importar/{tipo}', [\App\Http\Controllers\MapaLogisticoController::class, 'importar'])->name('importar');
        Route::post('/{tipo}', [\App\Http\Controllers\MapaLogisticoController::class, 'store'])->name('store');
        Route::put('/{tipo}/{id}/poligono', [\App\Http\Controllers\MapaLogisticoController::class, 'actualizarPoligono'])->name('poligono.update');
        Route::put('/periferia/{id}', [\App\Http\Controllers\MapaLogisticoController::class, 'actualizarPeriferia'])->name('periferia.update');
        Route::put('/{tipo}/{id}/activo', [\App\Http\Controllers\MapaLogisticoController::class, 'toggleActivo'])->name('toggle');
        Route::delete('/{tipo}/{id}', [\App\Http\Controllers\MapaLogisticoController::class, 'eliminar'])->name('eliminar');
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
        Route::get('/alertas', [ActivoController::class, 'alertas'])->name('alertas');
        Route::get('/{activo}/qr.svg', [ActivoController::class, 'qr'])->name('qr');
        Route::get('/{activo}/qr.png', [ActivoController::class, 'qrPng'])->name('qr_png');
        Route::get('/{activo}', [ActivoController::class, 'show'])->name('show');

        Route::post('/', [ActivoController::class, 'store'])->middleware('can:activos.crear')->name('store');
        Route::put('/{activo}', [ActivoController::class, 'update'])->middleware('can:activos.editar')->name('update');
        Route::post('/{activo}/asignar', [ActivoController::class, 'asignar'])->middleware('can:activos.asignar')->name('asignar');
        Route::post('/{activo}/devolver', [ActivoController::class, 'devolver'])->middleware('can:activos.asignar')->name('devolver');
        Route::post('/{activo}/transferir', [ActivoController::class, 'transferir'])->middleware('can:activos.transferir')->name('transferir');
        Route::post('/{activo}/estado', [ActivoController::class, 'cambiarEstado'])->middleware('can:activos.cambiar_estado')->name('estado');
        Route::post('/{activo}/mantenimiento', [ActivoController::class, 'programarMantenimiento'])->middleware('can:activos.cambiar_estado')->name('mantenimiento');
        Route::post('/{activo}/mantenimiento/{mantenimiento}/completar', [ActivoController::class, 'completarMantenimiento'])->middleware('can:activos.cambiar_estado')->name('mantenimiento.completar');
        Route::post('/{activo}/fotos', [ActivoController::class, 'subirFotos'])->middleware('can:activos.editar')->name('fotos.store');
        Route::delete('/{activo}/fotos/{foto}', [ActivoController::class, 'eliminarFoto'])->middleware('can:activos.editar')->name('fotos.destroy');
    });


    // ══════════════════════════════════════════════════════════════════════
    // MÓDULO DE ADMINISTRACIÓN (GELIANV CORE)
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('admin')->name('admin.')->group(function () {

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

                // Tipos de Activo
                Route::post('/tipos-activo', [TipoActivoController::class, 'store'])->name('tipos_activo.store')->middleware('can:activos.configurar_tipos');
                Route::put('/tipos-activo/{id}', [TipoActivoController::class, 'update'])->name('tipos_activo.update')->middleware('can:activos.configurar_tipos');
                Route::delete('/tipos-activo/{id}', [TipoActivoController::class, 'destroy'])->name('tipos_activo.destroy')->middleware('can:activos.configurar_tipos');
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

        // --- 6. API Externa ---
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
        Route::get('/activos/marcas', [ActivoController::class, 'buscarMarcas'])->name('activos.marcas');
        Route::get('/activos/modelos', [ActivoController::class, 'buscarModelos'])->name('activos.modelos');
        Route::middleware(['auth'])->post('/entregas/cotizar', [CotizacionEntregaController::class, 'calcularCosto'])->name('entregas.cotizar');
    });
});
