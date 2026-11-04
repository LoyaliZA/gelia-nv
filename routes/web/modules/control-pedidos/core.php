<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControlPedidos\PedidoBmaController;
use App\Http\Controllers\ControlPedidos\DireccionesAuxiliarController;
use App\Http\Controllers\ControlPedidos\PedidoBmaSaldosPagosController;
use App\Http\Controllers\ControlPedidos\PlazosRetrasoPedidoBmaController;

Route::prefix('control-pedidos')->name('control_pedidos.')->group(function () {
    Route::get('/{pedidoBma}/transferencia-tienda', [\App\Http\Controllers\ControlPedidos\TransferenciaTiendaController::class, 'show'])->name('transferencia_tienda.show');
    Route::post('/{pedidoBma}/transferencia-tienda/{accion}', [\App\Http\Controllers\ControlPedidos\TransferenciaTiendaController::class, 'update'])->name('transferencia_tienda.update');
    Route::middleware(['can:control_pedidos.ver_listado'])->group(function () {
        Route::get('/', [PedidoBmaController::class, 'index'])->name('index');
        Route::get('/listado', [PedidoBmaController::class, 'listado'])->name('listado');
        Route::get('/exportar', [PedidoBmaController::class, 'exportar'])
            ->middleware('can:control_pedidos.exportar')
            ->name('exportar');
    });

    Route::middleware(['can:control_pedidos.configurar_plazos'])
        ->prefix('plazos')
        ->name('plazos.')
        ->group(function () {
            Route::get('/', [PlazosRetrasoPedidoBmaController::class, 'index'])->name('index');
            Route::put('/', [PlazosRetrasoPedidoBmaController::class, 'update'])->name('update');
        });

    Route::get('/pedidos/{pedidoBma}/preparacion/{tarea}/documentos/{documento}', \App\Http\Controllers\ControlPedidos\DocumentoPreparacionController::class)->name('preparacion.documento');

    // Descarga autenticada de documentos (visibilidad por VisibilidadPedidoBma::puedeConsultar).
    Route::get('/pedidos/{pedidoBma}/documentos/{documento}', [PedidoBmaController::class, 'documento'])
        ->name('documentos.show');

    Route::middleware(['can:control_pedidos.crear'])->group(function () {
        Route::post('/', [PedidoBmaController::class, 'store'])->name('store');
        Route::post('/autoguardar', [PedidoBmaController::class, 'autoguardar'])->name('autoguardar');
        Route::post('/{pedidoBma}/solicitud-tienda', [PedidoBmaController::class, 'solicitarTienda'])->name('solicitud_tienda');
        Route::post('/{pedidoBma}/cobertura-tienda', [PedidoBmaController::class, 'coberturaTienda'])->name('cobertura_tienda');
        Route::post('/{pedidoBma}/anexo-tienda', [PedidoBmaController::class, 'anexoTienda'])->name('anexo_tienda');
        Route::post('/{pedidoBma}/retiro-piezas-tienda', [PedidoBmaController::class, 'retiroPiezasTienda'])->name('retiro_piezas_tienda');
        Route::post('/{pedidoBma}/retiro-custodia-envio', [PedidoBmaController::class, 'retirarCustodiaEnvio'])->name('retiro_custodia_envio');
        Route::post('/{pedidoBma}/sucursal-destino', [PedidoBmaController::class, 'cambiarSucursalTienda'])->name('sucursal_destino');
        Route::get('/candidatos-principal', [PedidoBmaController::class, 'candidatosPrincipal'])->name('candidatos_principal');
        Route::get('/candidatos-resguardo-envio', [PedidoBmaController::class, 'candidatosResguardoEnvio'])->name('candidatos_resguardo_envio');
        Route::post('/{pedidoBma}/completar-envio-resguardo', [PedidoBmaController::class, 'completarEnvioResguardo'])->name('completar_envio_resguardo');
        Route::post('/{pedidoBma}/cargar-guia-cliente', [PedidoBmaController::class, 'cargarGuiaCliente'])->name('cargar_guia_cliente');
        Route::put('/{pedidoBma}/enviar', [PedidoBmaController::class, 'enviar'])->name('enviar');
        Route::post('/{pedidoBma}/anexar-pago-envio', [PedidoBmaController::class, 'anexarPagoEnvio'])->name('anexar_pago_envio');
        Route::post('/{pedidoBma}/pdf-pedido', [PedidoBmaController::class, 'subirPdfPedido'])->name('pdf_pedido.store');
        Route::post('/{pedidoBma}/anexo-piezas', [PedidoBmaController::class, 'subirAnexoPiezas'])->name('anexo_piezas.store');
        Route::post('/{pedidoBma}/solicitar-pesaje', [PedidoBmaController::class, 'solicitarPesaje'])->name('solicitar_pesaje');
        Route::post('/{pedidoBma}/solicitar-preparacion-tienda', [PedidoBmaController::class, 'solicitarPreparacionTienda'])->name('solicitar_preparacion_tienda');
        Route::post('/{pedidoBma}/desglose-preparacion', [PedidoBmaController::class, 'guardarDesglosePreparacion'])->name('desglose_preparacion');
        Route::post('/{pedidoBma}/solicitar-repesaje', [PedidoBmaController::class, 'solicitarRepesaje'])->name('solicitar_repesaje');
        Route::post('/{pedidoBma}/cerrar-consulta', [PedidoBmaController::class, 'cerrarConsulta'])->name('cerrar_consulta');
        Route::post('/{pedidoBma}/reabrir-consulta', [PedidoBmaController::class, 'reabrirConsulta'])->name('reabrir_consulta');
        Route::post('/{pedidoBma}/atender-sin-existencia', [PedidoBmaController::class, 'atenderSinExistencia'])->name('atender_sin_existencia');
        Route::post('/{pedidoBma}/volver-borrador', [PedidoBmaController::class, 'volverBorrador'])->name('volver_borrador');
        Route::post('/actualizar-campos-direccion', [PedidoBmaController::class, 'actualizarCamposDireccion'])->name('actualizar_campos_direccion');
        Route::post('/registrar-direccion-catalogo', [PedidoBmaController::class, 'registrarDireccionCatalogo'])
            ->middleware('can:clientes.direcciones.crear')
            ->name('registrar_direccion_catalogo');
        Route::middleware(['can:clientes.direcciones.generar_enlace'])->group(function () {
            Route::post('/cliente/{cliente}/enlace-direccion', [DireccionesAuxiliarController::class, 'generarEnlace'])
                ->name('enlace_direccion');
        });
        Route::get('/cliente/{cliente}/saldo-favor', [PedidoBmaSaldosPagosController::class, 'cuentaCliente'])
            ->name('cliente.saldo_favor');
        Route::post('/{pedidoBma}/pagos', [PedidoBmaSaldosPagosController::class, 'registrarPago'])->name('pagos.store');
        Route::get('/{pedidoBma}/pagos', [PedidoBmaSaldosPagosController::class, 'resumenPago'])->name('pagos.resumen');
        Route::post('/pagos/{pago}', [PedidoBmaSaldosPagosController::class, 'actualizarPago'])->name('pagos.update');
        Route::delete('/pagos/{pago}', [PedidoBmaSaldosPagosController::class, 'eliminarPago'])->name('pagos.destroy');
        Route::post('/pagos/{pago}/sustituir', [PedidoBmaSaldosPagosController::class, 'sustituirPago'])->name('pagos.sustituir');
        Route::post('/{pedidoBma}/generar-saldo-excedente', [PedidoBmaSaldosPagosController::class, 'generarSaldoExcedente'])
            ->name('generar_saldo_excedente');
    });

    Route::post('/{pedidoBma}/segundo-traslado', [PedidoBmaController::class, 'resolverSegundoTraslado'])
        ->middleware('can:control_pedidos.tienda.autorizar_segundo_traslado')
        ->name('segundo_traslado');
    Route::post('/{pedidoBma}/segundo-traslado/salida', [PedidoBmaController::class, 'salirSegundoTraslado'])
        ->middleware('role_or_permission:control_pedidos.tienda.trasladar|control_pedidos.tienda.responder')
        ->name('segundo_traslado.salida');
    Route::post('/{pedidoBma}/segundo-traslado/recepcion', [PedidoBmaController::class, 'recibirSegundoTraslado'])
        ->middleware('role_or_permission:control_pedidos.tienda.trasladar|control_pedidos.tienda.responder')
        ->name('segundo_traslado.recepcion');
    Route::post('/{pedidoBma}/retiro-custodia-envio/trasladar', [PedidoBmaController::class, 'trasladarCustodiaCedis'])
        ->middleware('can:control_pedidos.tienda.trasladar')
        ->name('retiro_custodia_envio.trasladar');
    Route::post('/{pedidoBma}/retiro-custodia-envio/recibir-cedis', [PedidoBmaController::class, 'recibirCustodiaCedis'])
        ->middleware('can:control_pedidos.cedis')
        ->name('retiro_custodia_envio.recibir_cedis');
    Route::post('/{pedidoBma}/anexo-tienda/integrar', [PedidoBmaController::class, 'integrarAnexoTienda'])
        ->middleware('can:control_pedidos.tienda.responder')
        ->name('anexo_tienda.integrar');

    // crear | editar se valida en UpdatePedidoBmaRequest (borradores autoguardados)
    Route::put('/{pedidoBma}', [PedidoBmaController::class, 'update'])->name('update');

    Route::middleware(['can:control_pedidos.eliminar'])->group(function () {
        Route::delete('/{pedidoBma}', [PedidoBmaController::class, 'destroy'])->name('destroy');
    });

    Route::middleware(['can:control_pedidos.eliminar_registro'])->group(function () {
        Route::delete('/{pedidoBma}/eliminar-registro', [PedidoBmaController::class, 'eliminarRegistro'])->name('eliminar_registro');
    });

    Route::middleware(['can:control_pedidos.eliminados'])->group(function () {
        Route::put('/{pedidoBma}/restaurar-registro', [PedidoBmaController::class, 'restaurarRegistro'])->name('restaurar_registro');
    });

    Route::middleware(['can:control_pedidos.cancelar'])->group(function () {
        Route::get('/{pedidoBma}/cancelar/preview', [PedidoBmaController::class, 'previewCancelacion'])->name('cancelar.preview');
        Route::post('/{pedidoBma}/cancelar', [PedidoBmaController::class, 'cancelar'])->name('cancelar');
        Route::post('/{pedidoBma}/espera-pago', [PedidoBmaController::class, 'marcarEsperaPago'])
            ->middleware('can:control_pedidos.espera_pago')
            ->name('espera_pago');
        Route::post('/{pedidoBma}/cancelacion-operativa/{cancelacion}/reactivar', [PedidoBmaController::class, 'reactivarCancelacionOperativa'])
            ->middleware('can:control_pedidos.cancelacion_operativa.reactivar')
            ->name('cancelacion_operativa.reactivar');
        Route::post('/{pedidoBma}/cancelacion-operativa/{cancelacion}/resolver-financiera', [PedidoBmaController::class, 'resolverFinancieroCancelacion'])
            ->middleware('can:control_pedidos.cancelacion_operativa.resolver_financiera')
            ->name('cancelacion_operativa.resolver_financiera');
        Route::post('/{pedidoBma}/cancelacion-operativa/{cancelacion}/concluir-admin', [PedidoBmaController::class, 'concluirCancelacionAdmin'])
            ->middleware('can:control_pedidos.cancelacion_operativa.concluir_admin')
            ->name('cancelacion_operativa.concluir_admin');
    });

    Route::middleware(['can:control_pedidos.direccion.cambiar'])->group(function () {
        Route::post('/{pedidoBma}/cambiar-direccion', [PedidoBmaController::class, 'cambiarDireccion'])->name('cambiar_direccion');
    });
});
