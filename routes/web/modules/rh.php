<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rh\{
    BancoTiempoController,
    CatalogoBonoController,
    CatalogoPuestoController,
    CatalogoReglaIncidenciaController,
    CatalogoTipoFaltaController,
    CatalogoTurnoController,
    ColaboradorController,
    ConfiguracionRhController,
    ConsolidadoDeduccionesController,
    ConsolidadoHorasExtraController,
    DashboardRhController,
    DeduccionController,
    HorasExtraController,
    IncidenciaGerenteController,
    PeriodoPagoController,
    PrestamoPagoFijoController,
    ReciboRhController,
    SalidaPersonalController,
};

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
        Route::post('/', [CatalogoTurnoController::class, 'store'])->name('store');
        Route::put('/{turno}', [CatalogoTurnoController::class, 'update'])->name('update');
        Route::delete('/{turno}', [CatalogoTurnoController::class, 'destroy'])->name('destroy');
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
