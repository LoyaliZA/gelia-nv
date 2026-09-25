import React, { useState } from 'react';
import { Eye } from 'lucide-react';
import {
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    geliaCardClass,
} from '../../../../utils/geliaTheme';
import {
    badgeEstadoResguardo,
    BTN_SECONDARY,
    claseTextoPlazo,
    formatearFechaOperativa,
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
} from './resguardosUtils';
import { resguardoAdmiteRecepcion } from './recepcionFisicaUtils';
import AccionReponerVencidoResguardo from './AccionReponerVencidoResguardo';
import TarjetaResguardoOperativa from './TarjetaResguardoOperativa';
import SelectorVistaResguardos from './SelectorVistaResguardos';
import { AccionEntregaResguardo } from './ModalEntregaResguardo';
import BotonConfirmarRecepcionResguardo from './BotonConfirmarRecepcionResguardo';
import { resguardoSeleccionableGerente } from './recepcionGerenteApi';
import { abrirDetalleResguardoModal } from './resguardoDetalleModalBridge';

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
    puedeRecibir,
    puedeEntregar,
    seleccionable = false,
    seleccionado = false,
    onToggleSeleccion,
    onReponerExito,
    onEntregaExito,
    onRecepcionExito,
}) {
    const clasificaciones = etiquetasClasificacionActivas(resguardo, catalogos.antiguedades);

    return (
        <tr className="border-b theme-border hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
            {seleccionable && (
                <td className="px-4 py-3">
                    <input
                        type="checkbox"
                        className="h-5 w-5"
                        checked={seleccionado}
                        onChange={() => onToggleSeleccion?.(resguardo.id)}
                        aria-label={`Seleccionar ${resguardo.snapshot_folio || `resguardo ${resguardo.id}`} para entrega conjunta`}
                    />
                </td>
            )}
            <td className="px-4 py-3 text-sm font-black theme-text-main">
                {resguardo.snapshot_folio || `#${resguardo.id}`}
            </td>
            <td className="px-4 py-3 text-sm font-semibold theme-text-muted">
                {referenciaCliente(resguardo)}
            </td>
            <td className="px-4 py-3 text-sm font-semibold theme-text-muted tabular-nums">
                {resguardo.cantidad_bultos_esperada}
            </td>
            <td className="px-4 py-3">
                <span className={`inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase ${badgeEstadoResguardo(resguardo.estado)}`}>
                    {catalogos.estados?.[resguardo.estado] || resguardo.estado}
                </span>
            </td>
            <td className="px-4 py-3 text-[10px] theme-text-muted">
                {clasificaciones.length > 0 ? clasificaciones.join(' · ') : '—'}
            </td>
            <td className="px-4 py-3 text-[10px] theme-text-muted whitespace-nowrap">
                <FechasOperativasResguardo resguardo={resguardo} bandeja={bandeja} />
            </td>
            <td className="px-4 py-3 text-right">
                <div className="flex flex-wrap justify-end gap-2">
                    {puedeEntregar && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada && (
                        <AccionEntregaResguardo
                            resguardo={resguardo}
                            onExito={onEntregaExito}
                            className="inline-flex"
                        />
                    )}
                    {puedeRecibir && resguardoAdmiteRecepcion(resguardo) && (
                        <BotonConfirmarRecepcionResguardo
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
                        className={`${BTN_SECONDARY} inline-flex items-center gap-2`}
                    >
                        <Eye className="w-4 h-4" /> Detalle
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
    puedeRecibir = false,
    puedeConfirmarCustodia = false,
    paso = 'gerente',
    onPaso,
    puedeEntregar = false,
    idsSeleccionados = [],
    onToggleSeleccion,
    onReponerExito,
    onEntregaExito,
    onRecepcionExito,
}) {
    const items = resguardos?.data || [];
    const seleccionMasivaGerente = puedeRecibir && bandeja === 'por_recibir' && paso === 'gerente' && Boolean(onToggleSeleccion);
    const seleccionableEntrega = puedeEntregar && bandeja === 'en_custodia' && Boolean(onToggleSeleccion);
    const seleccionable = seleccionMasivaGerente || seleccionableEntrega;
    const [vistaPorRecibir, setVistaPorRecibir] = useState(leerVistaPorRecibir);

    const onCambiarVista = (nuevaVista) => {
        setVistaPorRecibir(nuevaVista);
        guardarVistaPorRecibir(nuevaVista);
    };

    const claseTarjetas = claseVistaTarjetas(bandeja, vistaPorRecibir);
    const claseTabla = claseVistaTabla(bandeja, vistaPorRecibir);
    const claseContenedorTarjetas = claseGridTarjetasResguardo(bandeja, vistaPorRecibir);
    const tituloBandeja = bandeja === 'en_custodia'
        ? 'Resguardos en custodia'
        : bandeja === 'incidencias'
            ? 'Resguardos con incidencias'
            : 'Pedidos a recibir';
    const pasos = [
        { id: 'gerente', etiqueta: 'Recepción gerente', visible: puedeRecibir },
        { id: 'recepcionista', etiqueta: 'Custodia recepción', visible: puedeConfirmarCustodia },
    ].filter((opcion) => opcion.visible);
    const mostrarPaso = bandeja === 'por_recibir' && pasos.length > 1;

    return (
        <div className={`${geliaCardClass()} overflow-hidden`}>
            <div className="flex flex-col gap-3 px-4 pt-4 pb-3 border-b theme-border">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                        {tituloBandeja}
                    </p>
                    <SelectorVistaResguardos vista={vistaPorRecibir} onCambiar={onCambiarVista} />
                </div>
                {mostrarPaso && (
                    <div className={GELIA_SEGMENT_TABS_SCROLL}>
                        <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1`} role="tablist" aria-label="Paso de recepción">
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
                                        className="gelia-segment-btn whitespace-nowrap"
                                    >
                                        {etiqueta}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            {items.length === 0 ? (
                <div className="p-10 md:p-16 text-center space-y-3">
                    <p className="text-sm theme-text-muted font-bold uppercase tracking-widest m-0">
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
                        puedeRecibir={puedeRecibir}
                        puedeConfirmarCustodia={puedeConfirmarCustodia}
                        puedeEntregar={puedeEntregar}
                        paso={paso}
                        seleccionable={
                            (seleccionMasivaGerente && resguardoSeleccionableGerente(resguardo))
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

            <div className={`${claseTabla} overflow-x-auto`}>
                <table className="w-full border-collapse min-w-[900px]">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            {(seleccionable ? ['', 'Folio', 'Cliente', 'Bultos', 'Estado', 'Antigüedad', 'Fecha operativa', ''] : ['Folio', 'Cliente', 'Bultos', 'Estado', 'Antigüedad', 'Fecha operativa', '']).map((col, idx) => (
                                <th
                                    key={`${col || 'acciones'}-${idx}`}
                                    className={`px-4 py-4 text-[9px] font-black uppercase tracking-widest theme-text-muted ${col === '' ? 'text-right' : 'text-left'}`}
                                >
                                    {col}
                                </th>
                            ))}
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
                            puedeRecibir={puedeRecibir}
                            puedeEntregar={puedeEntregar}
                            seleccionable={
                                (seleccionMasivaGerente && resguardoSeleccionableGerente(resguardo))
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
