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
use App\Http\Controllers\AromasListasController;

// ══════════════════════════════════════════════════════════════════════
// 1. REDIRECCIÓN INICIAL
// ══════════════════════════════════════════════════════════════════════
Route::get('/', function () {
    return redirect()->route('login');
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
    Route::put('/solicitudes/{solicitud}/confirmar-lista', [SolicitudController::class, 'confirmarCambioLista'])->name('solicitudes.confirmar_lista');
    Route::put('/solicitudes/{solicitud}/confirmar-rollback', [SolicitudController::class, 'confirmarRollback'])->name('solicitudes.confirmar_rollback');
    Route::post('/solicitudes/{solicitud}/consultas', [SolicitudController::class, 'storeConsulta'])->middleware('can:solicitudes.consultar')->name('solicitudes.consultas.store');
    Route::put('/solicitudes/{solicitud}/consultas/{consulta}', [SolicitudController::class, 'responderConsulta'])->name('solicitudes.consultas.responder');
    Route::put('/solicitudes/{solicitud}/consultas/{consulta}/leer', [SolicitudController::class, 'marcarConsultaLeida'])->name('solicitudes.consultas.leer');
    Route::delete('/solicitudes/{solicitud}', [SolicitudController::class, 'destroy'])->name('solicitudes.destroy');

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
    // MÓDULO DE ADMINISTRACIÓN (GELIANV CORE)
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('admin')->name('admin.')->group(function () {

        // --- 1. Gestión de Usuarios y Accesos ---
        Route::middleware(['can:usuarios.gestionar'])->group(function () {
            Route::get('/usuarios', [AdminController::class, 'usuarios'])->name('usuarios');
            Route::post('/usuarios', [AdminController::class, 'storeUsuario'])->name('usuarios.store');
            Route::put('/usuarios/{user}', [AdminController::class, 'updateUsuario'])->name('usuarios.update');

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
            });
        });

        // --- 4. Comisiones ---
        Route::middleware(['can:comisiones.gestionar'])->group(function () {
            Route::get('/comisiones', [AdminController::class, 'comisiones'])->name('comisiones');
            Route::put('/comisiones/{id}', [AdminController::class, 'actualizarComision'])->name('comisiones.update');
        });

        // --- 5. Auditorías del Sistema ---
        Route::middleware(['can:sistema.auditorias.ver'])->group(function () {
            // Corrección: Cambiar el llamado de 'auditorias' a 'index'
            Route::get('/auditorias-sistema', [AuditoriaListaDescuentoController::class, 'index'])
                ->name('auditorias_sistema.index');
        });
    });

    // ══════════════════════════════════════════════════════════════════════
    // API INTERNA DEL SISTEMA
    // ══════════════════════════════════════════════════════════════════════
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/clientes', [ClienteApiController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/{numero}', [ClienteApiController::class, 'show'])->name('clientes.show');
        Route::middleware(['auth'])->post('/entregas/cotizar', [CotizacionEntregaController::class, 'calcularCosto'])->name('entregas.cotizar');
    });
});
