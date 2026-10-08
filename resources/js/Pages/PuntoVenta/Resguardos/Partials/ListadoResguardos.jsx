import React, { useState } from 'react';
import { Eye } from 'lucide-react';
import { GELIA_BADGE, geliaCardClass } from '../../../../utils/geliaTheme';
import {
    badgeEstadoResguardo,
    BTN_SECONDARY,
    claseTextoPlazo,
    formatearFechaOperativa,
    RESGUARDOS_BTN_ICON_TABLA,
    RESGUARDOS_TABLA_FILA,
    RESGUARDOS_TABLA_HEAD,
} from './resguardosStyles';
import {
    claseGridTarjetasResguardo,
    claseVistaTabla,
    claseVistaTarjetas,
    etiquetasClasificacionActivas,
    guardarVistaPorRecibir,
    leerVistaPorRecibir,
    mensajeVacioBandeja,
    plazosOperativosResguardo,
    referenciaCliente,
    faltaEvidenciaManual,
    VISTA_RESGUARDOS_POR_RECIBIR,
} from './resguardosUtils';
import { useVistaCompactaResguardo } from './useVistaCompactaResguardo';
import { resguardoAdmiteRecepcion } from './recepcionFisicaUtils';
import AccionReponerVencidoResguardo from './AccionReponerVencidoResguardo';
import TarjetaResguardoOperativa from './TarjetaResguardoOperativa';
import SelectorVistaResguardos from './SelectorVistaResguardos';
import { AccionEntregaResguardo } from './ModalEntregaResguardo';
import BotonConfirmarRecepcionResguardo, { BotonPasarARecepcionResguardo } from './BotonConfirmarRecepcionResguardo';
import { resguardoSeleccionableGerente } from './recepcionGerenteApi';
import { abrirDetalleResguardoModal } from './resguardoDetalleModalBridge';
import AccionEvidenciaRegistroManual from './AccionEvidenciaRegistroManual';

function abrirDetalle(resguardoId, resguardo) {
    abrirDetalleResguardoModal(resguardoId, resguardo);
}

function FechasOperativasResguardo({ resguardo, bandeja }) {
    const plazos = plazosOperativosResguardo(resguardo);

    if (plazos.length > 0) {
        return (
            <div className="space-y-0.5">
                {plazos.map(({ id, etiqueta, fecha, clasificacion }) => (
                    <p
                        key={id}
                        className={`text-[10px] m-0 ${claseTextoPlazo(clasificacion)}`}
                    >
                        {etiqueta}: {formatearFechaOperativa(fecha)}
                    </p>
                ))}
            </div>
        );
    }

    if (bandeja === 'en_custodia') {
        return (
            <p className="text-[10px] theme-text-muted m-0">
                Recepción: {formatearFechaOperativa(resguardo.recepcion_fisica_at)}
            </p>
        );
    }
    if (bandeja === 'incidencias') {
        return (
            <p className="text-[10px] theme-text-muted m-0">
                Salida CEDIS: {formatearFechaOperativa(resguardo.salida_cedis_at)}
            </p>
        );
    }
    return (
        <p className="text-[10px] theme-text-muted m-0">
            Salida CEDIS: {formatearFechaOperativa(resguardo.salida_cedis_at)}
        </p>
    );
}

function FilaTablaResguardo({
    resguardo,
    bandeja,
    catalogos,
    permisos = {},
    puedeConfirmarLlegada = false,
    puedeEnviarACustodia = false,
    puedeEntregar,
    seleccionable = false,
    seleccionado = false,
    onToggleSeleccion,
    onReponerExito,
    onEntregaExito,
    onRecepcionExito,
}) {
    const clasificaciones = etiquetasClasificacionActivas(resguardo, catalogos.antiguedades);

    const folio = resguardo.snapshot_folio || `#${resguardo.id}`;

    return (
        <tr className={RESGUARDOS_TABLA_FILA}>
            {seleccionable && (
                <td className="px-4 py-3">
                    <input
                        type="checkbox"
                        className="h-5 w-5"
                        checked={seleccionado}
                        onChange={() => onToggleSeleccion?.(resguardo.id)}
                        aria-label={`Seleccionar ${folio}`}
                    />
                </td>
            )}
            <td className="px-3 md:px-4 py-2.5 md:py-3 text-sm font-bold theme-text-main tabular-nums">
                {folio}
            </td>
            <td className="px-4 py-3 text-sm font-semibold theme-text-muted">
                {referenciaCliente(resguardo)}
            </td>
            <td className="px-4 py-3 text-sm font-semibold theme-text-muted tabular-nums">
                {resguardo.cantidad_bultos_esperada}
            </td>
            <td className="px-4 py-3">
                <span className={`${GELIA_BADGE} ${badgeEstadoResguardo(resguardo.estado)}`}>
                    {catalogos.estados?.[resguardo.estado] || resguardo.estado}
                </span>
            </td>
            <td className="px-4 py-3 text-[10px] theme-text-muted">
                {clasificaciones.length > 0 ? clasificaciones.join(' · ') : '—'}
            </td>
            <td className="px-4 py-3 text-[10px] theme-text-muted whitespace-nowrap">
                <FechasOperativasResguardo resguardo={resguardo} bandeja={bandeja} />
            </td>
            <td className="px-2 md:px-4 py-2.5 md:py-3 text-right">
                <div className="flex flex-wrap justify-end gap-1.5 md:gap-2">
                    {puedeEntregar && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada && (
                        <AccionEntregaResguardo
                            resguardo={resguardo}
                            onExito={onEntregaExito}
                            className="inline-flex"
                        />
                    )}
                    {faltaEvidenciaManual(resguardo) && permisos.registrar_manual && (
                        <AccionEvidenciaRegistroManual
                            resguardo={resguardo}
                            onExito={onRecepcionExito}
                            className={`${BTN_SECONDARY} inline-flex items-center gap-2`}
                        />
                    )}
                    {puedeConfirmarLlegada && !faltaEvidenciaManual(resguardo) && resguardoAdmiteRecepcion(resguardo) && (
                        <BotonConfirmarRecepcionResguardo
                            resguardo={resguardo}
                            onExito={onRecepcionExito}
                            className="inline-flex w-auto"
                        />
                    )}
                    {puedeEnviarACustodia && !faltaEvidenciaManual(resguardo) && resguardo.estado === 'recibido' && (
                        <BotonPasarARecepcionResguardo
                            resguardo={resguardo}
                            onExito={onRecepcionExito}
                            className="inline-flex w-auto"
                        />
                    )}
                    <AccionReponerVencidoResguardo
                        resguardo={resguardo}
                        permisos={permisos}
                        onExito={onReponerExito}
                    />
                    <button
                        type="button"
                        onClick={() => abrirDetalle(resguardo.id, resguardo)}
                        className={`${RESGUARDOS_BTN_ICON_TABLA} md:min-w-0 md:px-3 md:gap-2`}
                        aria-label={`Ver detalle de ${folio}`}
                    >
                        <Eye className="w-4 h-4 shrink-0" aria-hidden />
                        <span className="hidden md:inline text-xs font-semibold">Detalle</span>
                    </button>
                </div>
            </td>
        </tr>
    );
}

export default function ListadoResguardos({
    resguardos,
    bandeja,
    catalogos = {},
    permisos = {},
    hayFiltrosActivos = false,
    onLimpiarFiltros,
    puedeConfirmarLlegada = false,
    puedeEnviarACustodia = false,
    puedeConfirmarCustodia = false,
    paso = 'gerente',
    onPaso,
    puedeEntregar = false,
    idsSeleccionados = [],
    onToggleSeleccion,
    onReponerExito,
    onEntregaExito,
    onRecepcionExito,
    onSeleccionarPagina,
    paginaSeleccionada = false,
    idsSeleccionablesPagina = [],
}) {
    const items = resguardos?.data || [];
    const vistaCompacta = useVistaCompactaResguardo();
    const puedePasoLlegada = puedeConfirmarLlegada || puedeEnviarACustodia;
    const seleccionMasivaGerente = puedePasoLlegada && bandeja === 'por_recibir' && paso === 'gerente' && Boolean(onToggleSeleccion);
    const seleccionableEntrega = puedeEntregar && bandeja === 'en_custodia' && Boolean(onToggleSeleccion);
    const seleccionable = seleccionMasivaGerente || seleccionableEntrega;
    const [vistaPorRecibir, setVistaPorRecibir] = useState(leerVistaPorRecibir);

    const onCambiarVista = (nuevaVista) => {
        setVistaPorRecibir(nuevaVista);
        guardarVistaPorRecibir(nuevaVista);
    };

    const vistaEfectiva = vistaCompacta ? VISTA_RESGUARDOS_POR_RECIBIR.CARD : vistaPorRecibir;
    const claseTarjetas = claseVistaTarjetas(bandeja, vistaEfectiva);
    const claseTabla = claseVistaTabla(bandeja, vistaEfectiva);
    const claseContenedorTarjetas = claseGridTarjetasResguardo(bandeja, vistaEfectiva);
    const tituloBandeja = bandeja === 'en_custodia'
        ? 'Resguardos en custodia'
        : bandeja === 'incidencias'
            ? 'Resguardos con incidencias'
            : 'Pedidos a recibir';
    const pasos = [
        { id: 'gerente', etiqueta: 'Confirmar llegada', visible: puedePasoLlegada },
        { id: 'recepcionista', etiqueta: 'Custodia recepción', visible: puedeConfirmarCustodia },
    ].filter((opcion) => opcion.visible);
    const mostrarPaso = bandeja === 'por_recibir' && pasos.length > 1;

    return (
        <div className={`${geliaCardClass()} overflow-hidden min-w-0 max-w-full`}>
            <div className="flex flex-col gap-3 px-3 sm:px-4 pt-3 sm:pt-4 pb-3 border-b theme-border">
                <div className="flex items-start justify-between gap-2 sm:gap-3">
                    <div className="min-w-0">
                        <p className="text-sm sm:text-base font-bold theme-text-main m-0">
                            {tituloBandeja}
                        </p>
                        {vistaCompacta && (
                            <p className="text-xs theme-text-muted m-0 mt-0.5">
                                Vista de tarjetas optimizada para este dispositivo
                            </p>
                        )}
                    </div>
                    {!vistaCompacta && (
                        <SelectorVistaResguardos
                            vista={vistaPorRecibir}
                            onCambiar={onCambiarVista}
                            className="shrink-0"
                        />
                    )}
                </div>
                {mostrarPaso && (
                    <div className="w-full min-w-0 max-w-full">
                        <div className="gelia-segment flex w-full min-w-0 p-1" role="tablist" aria-label="Paso de recepción">
                            {pasos.map(({ id, etiqueta }) => {
                                const activa = paso === id;
                                return (
                                    <button
                                        key={id}
                                        type="button"
                                        role="tab"
                                        aria-selected={activa}
                                        data-active={activa}
                                        onClick={() => onPaso?.(id)}
                                        className="gelia-segment-btn flex-1 min-w-0 px-2 text-[0.625rem] sm:text-xs leading-tight"
                                    >
                                        <span className="truncate block">{etiqueta}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            {items.length === 0 ? (
                <div className="p-8 sm:p-10 md:p-16 text-center space-y-3">
                    <p className="text-sm theme-text-muted font-semibold m-0 max-w-md mx-auto leading-relaxed">
                        {mensajeVacioBandeja(bandeja, catalogos.bandejas, hayFiltrosActivos)}
                    </p>
                    {hayFiltrosActivos && onLimpiarFiltros && (
                        <button type="button" onClick={onLimpiarFiltros} className={`${BTN_SECONDARY} text-xs`}>
                            Limpiar filtros
                        </button>
                    )}
                </div>
            ) : (
            <>
            <div className={`${claseTarjetas} ${claseContenedorTarjetas}`}>
                {items.map((resguardo) => (
                    <TarjetaResguardoOperativa
                        key={resguardo.id}
                        resguardo={resguardo}
                        bandeja={bandeja}
                        catalogos={catalogos}
                        permisos={permisos}
                        puedeConfirmarLlegada={puedeConfirmarLlegada}
                        puedeEnviarACustodia={puedeEnviarACustodia}
                        puedeConfirmarCustodia={puedeConfirmarCustodia}
                        puedeEntregar={puedeEntregar}
                        paso={paso}
                        seleccionable={
                            (seleccionMasivaGerente && resguardoSeleccionableGerente(resguardo, {
                                confirmarLlegada: puedeConfirmarLlegada,
                                enviarACustodia: puedeEnviarACustodia,
                            }))
                            || (seleccionableEntrega && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada)
                        }
                        seleccionado={idsSeleccionados.includes(resguardo.id)}
                        onToggleSeleccion={onToggleSeleccion}
                        onRecepcionExito={onRecepcionExito}
                        onEntregaExito={onEntregaExito}
                        onReponerExito={onReponerExito}
                    />
                ))}
            </div>

            <div className={`${claseTabla} overflow-x-auto overscroll-x-contain`}>
                <table className="w-full border-collapse min-w-[720px] lg:min-w-[900px]">
                    <thead className={RESGUARDOS_TABLA_HEAD}>
                        <tr>
                            {seleccionable && (
                                <th scope="col" className="px-3 py-3 w-12">
                                    <input
                                        type="checkbox"
                                        className="h-5 w-5"
                                        checked={paginaSeleccionada}
                                        disabled={idsSeleccionablesPagina.length === 0}
                                        onChange={() => onSeleccionarPagina?.()}
                                        aria-label="Seleccionar todos los resguardos de esta página"
                                    />
                                </th>
                            )}
                            {['Folio', 'Cliente', 'Bultos', 'Estado', 'Antigüedad', 'Fecha operativa'].map((col) => (
                                <th
                                    key={col}
                                    scope="col"
                                    className="px-3 md:px-4 py-3 text-left text-xs font-semibold theme-text-muted whitespace-nowrap"
                                >
                                    {col}
                                </th>
                            ))}
                            <th scope="col" className="px-2 md:px-4 py-3 text-right text-xs font-semibold theme-text-muted">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((resguardo) => (
                        <FilaTablaResguardo
                            key={resguardo.id}
                            resguardo={resguardo}
                            bandeja={bandeja}
                            catalogos={catalogos}
                            permisos={permisos}
                            puedeConfirmarLlegada={puedeConfirmarLlegada}
                            puedeEnviarACustodia={puedeEnviarACustodia}
                            puedeEntregar={puedeEntregar}
                            seleccionable={
                                (seleccionMasivaGerente && resguardoSeleccionableGerente(resguardo, {
                                confirmarLlegada: puedeConfirmarLlegada,
                                enviarACustodia: puedeEnviarACustodia,
                            }))
                                || (seleccionableEntrega && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada)
                            }
                            seleccionado={idsSeleccionados.includes(resguardo.id)}
                            onToggleSeleccion={onToggleSeleccion}
                            onReponerExito={onReponerExito}
                            onEntregaExito={onEntregaExito}
                            onRecepcionExito={onRecepcionExito}
                        />
                        ))}
                    </tbody>
                </table>
            </div>
            </>
            )}
        </div>
    );
}
