import React, { useEffect, useRef, useState } from 'react';
import { Search, Filter, AlertOctagon, SlidersHorizontal, X, Calendar, Workflow, Wallet, Layers, Clock3, CheckCircle2, ShieldCheck, TriangleAlert, Ban, Trash2, Loader2 } from 'lucide-react';
import CampoBusqueda from '@/Components/CampoBusqueda';
import useIconoHover from '@/hooks/useIconoHover';
import { normalizarFechaAlConfirmar } from '@/utils/fechaFiltro';
import RangoFechasPersonalizado from '@/Components/Filtros/RangoFechasPersonalizado';
import { CAMPO_BUSQUEDA_DEFECTO, PROPS_BUSQUEDA_SOLICITUD } from '@/hooks/useFiltrosSolicitudesPage';
import { GELIA_CHIP, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK_COMPACT, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '@/utils/geliaTheme';

export const CAMPOS_BUSQUEDA_SOLICITUD = [
    { id: 'folio', label: 'Folio', corto: 'Folio', placeholder: 'FOL-12 o 12…' },
    { id: 'numero_cliente', label: 'N.º de cliente', corto: 'N.º', placeholder: 'Número de cliente…' },
    { id: 'nombre_cliente', label: 'Cliente', corto: 'Cliente', placeholder: 'Nombre del cliente…' },
    { id: 'responsable', label: 'Responsable', corto: 'Resp.', placeholder: 'Nombre de quien registró…' },
];

const DEBOUNCE_BUSQUEDA_MS = 400;
const VISITA_BUSQUEDA = { replace: true, only: PROPS_BUSQUEDA_SOLICITUD };

const TABS = [
    { id: 'TODAS', label: 'Todas', icono: Layers },
    { id: 'PENDIENTES', label: 'Pendientes', icono: Clock3 },
    { id: 'RESPONDIDAS', label: 'Respondidas', icono: CheckCircle2 },
    { id: 'INCORRECTAS', label: 'Incorrectas', icono: TriangleAlert },
    { id: 'CANCELADAS', label: 'Canceladas', icono: Ban },
    { id: 'ELIMINADAS', label: 'Eliminadas', icono: Trash2 },
];

const ETIQUETA_PERIODO = {
    HOY: 'Hoy',
    AYER: 'Ayer',
    SEMANA: 'Esta semana',
    MES: 'Este mes',
    MES_ANTERIOR: 'Mes anterior',
    MES_ESPECIFICO: 'Mes específico',
    PERSONALIZADO: 'Rango personalizado',
};

const ETIQUETA_MOTIVO = {
    error_reportado: 'Reportadas (error)',
    vencimiento_pago: 'Pago vencido',
    pago_insuficiente: 'Pago insuficiente',
};

// Filtros extra de la variante tag-lista: [clave, etiqueta, opciones fijas, texto de «todos»]
const FILTROS_PAGO = [
    ['pago', 'Pago', [['confirmado', 'Pago confirmado'], ['pendiente', 'Pago pendiente'], ['vencido', 'Pago vencido']], 'Todos los pagos'],
    ['consulta', 'Consultas', [['pendiente', 'Con consulta pendiente'], ['sin_leer', 'Respuesta sin leer']], 'Con y sin consulta'],
    ['cancelacion', 'Cancelación', [['solicitada', 'Cancelación solicitada']], 'Con y sin cancelación'],
];

const ETIQUETA_EXTRA = {
    pago: { confirmado: 'Pago confirmado', pendiente: 'Pago pendiente', vencido: 'Pago vencido' },
    consulta: { pendiente: 'Consulta pendiente', sin_leer: 'Respuesta sin leer' },
    cancelacion: { solicitada: 'Cancelación solicitada' },
};

function EncabezadoGrupo({ icono: Icono, children }) {
    return (
        <h4 className="m-0 flex items-center gap-2 border-b theme-border pb-1.5 text-xs font-bold uppercase tracking-wide theme-text-muted">
            <Icono className="h-3.5 w-3.5" aria-hidden="true" />{children}
        </h4>
    );
}

const SIN_EXTRAS = {};

const FOCUS = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]';

/**
 * Filtros compartidos: módulo Solicitudes y Reportes de solicitudes financieras.
 */
export default function FiltrosSolicitudes({
    tabActiva,
    busqueda,
    busquedaCampo = '',
    buscando = false,
    tipoFecha,
    fechaInicio,
    fechaFin,
    filtroVendedor,
    filtroMotivo,
    vendedores = [],
    filtrosActivos = 0,
    onCambiarTab,
    onAplicarFiltros,
    onLimpiarAdicionales,
    idPrefixFechas = 'filtro-fecha',
    etiquetaBuscar = 'Buscar solicitudes',
    mostrarEliminadas = false,
    variante,
    conteosTab = {},
    mostrarFiltrosClientes = false,
    filtroLista = '', filtroTipoCliente = '', filtroTag = '',
    listas = [], tiposCliente = [],
    procesos = [], filtrosExtra = SIN_EXTRAS,
}) {
    const compacto = variante === 'tag-lista';
    const filtrosClientes = compacto || mostrarFiltrosClientes;
    const tabsBase = filtrosClientes && !compacto
        ? [...TABS.slice(0, 3), { id: 'VIGENTES', label: 'Vigentes', icono: ShieldCheck }, ...TABS.slice(3)]
        : TABS;
    const tabs = (mostrarEliminadas && !compacto) ? tabsBase : tabsBase.filter((tab) => tab.id !== 'ELIMINADAS');

    const [mostrarAdicionales, setMostrarAdicionales] = useState(
        filtrosActivos > 0 || tipoFecha !== 'TODAS'
    );
    const [busquedaLocal, setBusquedaLocal] = useState(busqueda);
    const [tipoFechaLocal, setTipoFechaLocal] = useState(tipoFecha);
    const [fechaInicioLocal, setFechaInicioLocal] = useState(fechaInicio);
    const [fechaFinLocal, setFechaFinLocal] = useState(fechaFin);
    const [vendedorLocal, setVendedorLocal] = useState(filtroVendedor);
    const [motivoLocal, setMotivoLocal] = useState(filtroMotivo);

    const [listaLocal, setListaLocal] = useState(filtroLista);
    const [tipoClienteLocal, setTipoClienteLocal] = useState(filtroTipoCliente);
    const [tagLocal, setTagLocal] = useState(filtroTag);
    const [extraLocal, setExtraLocal] = useState(filtrosExtra);
    const [errorFechas, setErrorFechas] = useState('');
    const [verEliminadas, setVerEliminadas] = useState(tabActiva === 'ELIMINADAS');
    const campoInicial = compacto ? (busquedaCampo || CAMPO_BUSQUEDA_DEFECTO) : busquedaCampo;
    const [campoLocal, setCampoLocal] = useState(campoInicial);
    const [escribiendo, setEscribiendo] = useState(false);
    const debounceBusqueda = useRef(null);
    const ultimaBusqueda = useRef(`${busqueda}\0${campoInicial}`);
    const iconoBuscar = useIconoHover(Search, { size: 16, className: 'h-4 w-4 shrink-0' });
    const iconoFiltros = useIconoHover(SlidersHorizontal, { size: 16, className: 'h-4 w-4 shrink-0' });
    const iconoPanelFiltros = useIconoHover(Filter, { size: 14, className: 'h-3.5 w-3.5' });
    useEffect(() => { setExtraLocal(filtrosExtra); }, [filtrosExtra]);
    const cambiarExtra = (clave, valor) => setExtraLocal((actual) => ({ ...actual, [clave]: valor }));
    useEffect(() => { setListaLocal(filtroLista); setTipoClienteLocal(filtroTipoCliente); setTagLocal(filtroTag); }, [filtroLista, filtroTipoCliente, filtroTag]);
    useEffect(() => { setVerEliminadas(tabActiva === 'ELIMINADAS'); }, [tabActiva]);

    useEffect(() => {
        if (escribiendo) return;
        setBusquedaLocal(busqueda);
    }, [busqueda, escribiendo]);

    useEffect(() => {
        setTipoFechaLocal(tipoFecha);
        setFechaInicioLocal(fechaInicio);
        setFechaFinLocal(fechaFin);
        setVendedorLocal(filtroVendedor);
        setMotivoLocal(filtroMotivo);
    }, [tipoFecha, fechaInicio, fechaFin, filtroVendedor, filtroMotivo]);

    useEffect(() => {
        if (filtrosActivos > 0 || tipoFecha !== 'TODAS') {
            setMostrarAdicionales(true);
        }
    }, [filtrosActivos, tipoFecha]);

    const aplicarConsulta = () => {
        if (['PERSONALIZADO', 'MES_ESPECIFICO'].includes(tipoFechaLocal)) {
            const inicio = normalizarFechaAlConfirmar(fechaInicioLocal);
            const fin = normalizarFechaAlConfirmar(fechaFinLocal);
            if (!inicio.ok || !fin.ok || (inicio.valor && fin.valor && inicio.valor > fin.valor) || (tipoFechaLocal === 'MES_ESPECIFICO' && (!inicio.valor || !fin.valor))) {
                setErrorFechas('Selecciona fechas válidas. La fecha inicial debe ser anterior o igual a la final.');
                return;
            }
        }
        setErrorFechas('');
        ultimaBusqueda.current = `${(busquedaLocal || '').trim()}\0${campoLocal || ''}`;
        const tabConsulta = motivoLocal
            ? 'INCORRECTAS'
            : (compacto && mostrarEliminadas
                ? (verEliminadas ? 'ELIMINADAS' : (tabActiva === 'ELIMINADAS' ? 'TODAS' : undefined))
                : undefined);
        onAplicarFiltros({
            ...(filtrosClientes ? { lista_id: listaLocal, tipo_cliente_id: tipoClienteLocal, tag: tagLocal } : {}),
            ...(variante === 'tag-lista' ? extraLocal : {}),
            q: busquedaLocal,
            ...(compacto ? { q_campo: campoLocal } : {}),
            tipo_fecha: tipoFechaLocal,
            fecha_inicio: fechaInicioLocal,
            fecha_fin: fechaFinLocal,
            vendedor_id: vendedorLocal,
            motivo_incorrecta: motivoLocal,
            tab: tabConsulta,
        });
    };

    const limpiarPanelAdicionales = () => {
        setListaLocal(''); setTipoClienteLocal(''); setTagLocal(''); setErrorFechas('');
        setExtraLocal(Object.fromEntries(Object.keys(extraLocal).map((clave) => [clave, ''])));
        setTipoFechaLocal('TODAS');
        setFechaInicioLocal('');
        setFechaFinLocal('');
        setVendedorLocal('');
        setMotivoLocal('');
        setVerEliminadas(false);
        onLimpiarAdicionales?.();
    };

    const quitarChip = (clave) => {
        const facetas = { lista_id: setListaLocal, tipo_cliente_id: setTipoClienteLocal, tag: setTagLocal };
        if (clave.startsWith('extra:')) { const k = clave.slice(6); cambiarExtra(k, ''); onAplicarFiltros({ [k]: '' }); return; }
        if (facetas[clave]) { facetas[clave](''); onAplicarFiltros({ [clave]: '' }); return; }
        if (clave === 'q') {
            setBusquedaLocal('');
            ultimaBusqueda.current = `\0${campoLocal || ''}`;
            onAplicarFiltros({ q: '' }, compacto ? VISITA_BUSQUEDA : undefined);
            return;
        }
        if (clave === 'periodo') {
            setTipoFechaLocal('TODAS');
            setFechaInicioLocal('');
            setFechaFinLocal('');
            onAplicarFiltros({ tipo_fecha: 'TODAS', fecha_inicio: '', fecha_fin: '' });
            return;
        }
        if (clave === 'vendedor') {
            setVendedorLocal('');
            onAplicarFiltros({ vendedor_id: '' });
            return;
        }
        if (clave === 'motivo') {
            setMotivoLocal('');
            onAplicarFiltros({ motivo_incorrecta: '' });
            return;
        }
        if (clave === 'eliminadas' || clave === 'vigentes') {
            setVerEliminadas(false);
            onCambiarTab('TODAS');
        }
    };

    const nombreResponsable = vendedores.find((v) => String(v.id) === String(filtroVendedor))?.name;
    const etiquetaPeriodo = ['PERSONALIZADO', 'MES_ESPECIFICO'].includes(tipoFecha) && fechaInicio && fechaFin
        ? `${fechaInicio} – ${fechaFin}`
        : ETIQUETA_PERIODO[tipoFecha];

    const campoActivo = CAMPOS_BUSQUEDA_SOLICITUD.find((c) => c.id === campoLocal) || CAMPOS_BUSQUEDA_SOLICITUD[0];
    const placeholderBusqueda = compacto
        ? (campoActivo?.placeholder || 'Buscar folio o cliente…')
        : 'Buscar folio o cliente…';
    const etiquetaCampoActivo = CAMPOS_BUSQUEDA_SOLICITUD.find((c) => c.id === (busquedaCampo || campoLocal))?.label;
    const formatearConteo = (valor) => new Intl.NumberFormat('es-MX').format(valor ?? 0);

    const chips = [
        busqueda ? { clave: 'q', etiqueta: `${etiquetaCampoActivo || 'Búsqueda'}: ${busqueda}` } : null,
        tipoFecha && tipoFecha !== 'TODAS' ? { clave: 'periodo', etiqueta: etiquetaPeriodo || 'Periodo' } : null,
        filtroVendedor ? { clave: 'vendedor', etiqueta: nombreResponsable || 'Responsable comercial' } : null,
        filtroMotivo ? { clave: 'motivo', etiqueta: ETIQUETA_MOTIVO[filtroMotivo] || filtroMotivo } : null,
        filtrosClientes && filtroLista ? { clave: 'lista_id', etiqueta: `Lista: ${listas.find(l => String(l.id) === String(filtroLista))?.nombre || filtroLista}` } : null,
        filtrosClientes && filtroTipoCliente ? { clave: 'tipo_cliente_id', etiqueta: `Tipo: ${filtroTipoCliente === 'SIN_TIPO' ? 'Sin tipo registrado' : tiposCliente.find(t => String(t.id) === String(filtroTipoCliente))?.nombre || filtroTipoCliente}` } : null,
        filtrosClientes && filtroTag ? { clave: 'tag', etiqueta: filtroTag === 'con_tag' ? 'Con TAG actual' : 'Sin TAG actual' } : null,
        compacto && tabActiva === 'VIGENTES' ? { clave: 'vigentes', etiqueta: 'Vigentes' } : null,
        compacto && tabActiva === 'ELIMINADAS' ? { clave: 'eliminadas', etiqueta: 'Eliminadas' } : null,
        ...(variante === 'tag-lista' ? [
            filtrosExtra.proceso_id ? { clave: 'extra:proceso_id', etiqueta: `Operación: ${procesos.find((p) => String(p.id) === String(filtrosExtra.proceso_id))?.nombre || filtrosExtra.proceso_id}` } : null,
            filtrosExtra.cliente_numero ? { clave: 'extra:cliente_numero', etiqueta: `Cliente n.º ${filtrosExtra.cliente_numero}` } : null,
            filtrosExtra.es_heredado ? { clave: 'extra:es_heredado', etiqueta: filtrosExtra.es_heredado === '1' ? 'Cliente heredado' : 'Cliente no heredado' } : null,
            ...Object.keys(ETIQUETA_EXTRA).map((k) => (filtrosExtra[k] ? { clave: `extra:${k}`, etiqueta: ETIQUETA_EXTRA[k][filtrosExtra[k]] || filtrosExtra[k] } : null)),
        ] : []),
    ].filter(Boolean);

    useEffect(() => () => clearTimeout(debounceBusqueda.current), []);

    useEffect(() => {
        setCampoLocal(compacto ? (busquedaCampo || CAMPO_BUSQUEDA_DEFECTO) : busquedaCampo);
    }, [busquedaCampo, compacto]);

    const aplicarBusquedaInstantanea = (q, campo) => {
        const firma = `${(q || '').trim()}\0${campo || ''}`;
        if (ultimaBusqueda.current === firma) return;
        ultimaBusqueda.current = firma;
        onAplicarFiltros({ q, q_campo: campo }, VISITA_BUSQUEDA);
    };

    const programarBusqueda = (q, campo, inmediato = false) => {
        clearTimeout(debounceBusqueda.current);
        if (!compacto) return;
        if (inmediato) {
            aplicarBusquedaInstantanea(q, campo);
            return;
        }
        debounceBusqueda.current = setTimeout(() => aplicarBusquedaInstantanea(q, campo), DEBOUNCE_BUSQUEDA_MS);
    };

    const claseBuscar = compacto
        ? `gelia-tag-filtros-accion theme-btn-primary inline-flex shrink-0 items-center justify-center gap-2 rounded-xl px-3 text-sm font-semibold ${FOCUS}`
        : `text-white inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold sm:w-auto ${FOCUS}`;
    const claseFiltros = compacto
        ? `gelia-tag-filtros-accion inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-medium ${FOCUS} ${mostrarAdicionales || filtrosActivos > 0 || tipoFecha !== 'TODAS' ? 'border-[var(--color-primario)] text-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)]' : 'theme-border theme-element theme-text-muted hover:border-[var(--color-primario)]'}`
        : `inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors sm:w-auto ${FOCUS} ${mostrarAdicionales || filtrosActivos > 0 || tipoFecha !== 'TODAS' ? 'border-[var(--color-primario)] text-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)]' : 'theme-border theme-element theme-text-muted hover:border-[var(--color-primario)]'}`;

    const selectorCampo = compacto ? (
        <>
            <label htmlFor={`${idPrefixFechas}-campo`} className="sr-only">Buscar por</label>
            <span className="gelia-tag-filtros-campo-wrap" data-valor={campoActivo.corto || campoActivo.label}>
                <select
                    id={`${idPrefixFechas}-campo`}
                    value={campoLocal}
                    onChange={(e) => {
                        const siguiente = e.target.value;
                        setCampoLocal(siguiente);
                        if (busquedaLocal.trim()) programarBusqueda(busquedaLocal, siguiente, true);
                    }}
                    className={`gelia-tag-filtros-campo ${FOCUS}`}
                    aria-label="Campo de búsqueda"
                    title={campoActivo.label}
                >
                    {CAMPOS_BUSQUEDA_SOLICITUD.map((campo) => (
                        <option key={campo.id} value={campo.id}>{campo.corto || campo.label}</option>
                    ))}
                </select>
            </span>
            <span className="gelia-tag-filtros-separador" aria-hidden="true" />
        </>
    ) : null;
    const campoBusqueda = (
        <CampoBusqueda
            wrapperClassName={compacto ? 'gelia-tag-filtros-busqueda' : 'relative min-w-0 flex-1'}
            type="search"
            placeholder={placeholderBusqueda}
            value={busquedaLocal}
            onChange={(e) => {
                const valor = e.target.value;
                setBusquedaLocal(valor);
                if (compacto) programarBusqueda(valor, campoLocal);
            }}
            onFocus={() => compacto && setEscribiendo(true)}
            onBlur={() => compacto && setEscribiendo(false)}
            onKeyDown={(e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (compacto) programarBusqueda(busquedaLocal, campoLocal, true);
                    else aplicarConsulta();
                }
            }}
            enterKeyHint="search"
            autoComplete="off"
            aria-label={compacto ? placeholderBusqueda : 'Buscar folio o cliente'}
            aria-busy={compacto && buscando ? true : undefined}
            className={`${THEME_INPUT} w-full`}
            leading={selectorCampo}
            suffix={compacto && buscando ? (
                <Loader2 className="gelia-tag-filtros-busqueda-estado" aria-hidden="true" />
            ) : null}
        />
    );
    const botonBuscar = (
        <button
            type="button"
            {...iconoBuscar.triggerProps}
            onClick={aplicarConsulta}
            className={claseBuscar}
            style={compacto ? undefined : { backgroundColor: 'var(--color-primario)' }}
            aria-label={etiquetaBuscar}
        >
            {iconoBuscar.nodo}
            Buscar
        </button>
    );
    const botonFiltros = (
        <button
            type="button"
            {...iconoFiltros.triggerProps}
            onClick={() => setMostrarAdicionales((v) => !v)}
            aria-expanded={mostrarAdicionales}
            aria-controls={`${idPrefixFechas}-adicionales`}
            className={claseFiltros}
        >
            {iconoFiltros.nodo}
            <span>Filtros</span>
            {filtrosActivos > 0 && (
                <span className="flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-semibold text-white tabular-nums" style={{ backgroundColor: 'var(--color-primario)' }}>
                    {filtrosActivos}
                </span>
            )}
        </button>
    );

    return (
        <div className={`space-y-3 ${variante === 'tag-lista' ? 'gelia-tag-filtros' : ''}`}>
            {compacto ? (
                <div className="gelia-tag-filtros-toolbar">
                    <div className="gelia-tag-filtros-capsulas" role="group" aria-label="Estado de la solicitud">
                        {tabs.map((tab) => {
                            const Icono = tab.icono;
                            const conteo = conteosTab[tab.id];
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => onCambiarTab(tab.id)}
                                    className={`gelia-tag-capsula ${FOCUS}`}
                                    data-active={tabActiva === tab.id}
                                    aria-pressed={tabActiva === tab.id}
                                >
                                    {Icono ? <Icono aria-hidden="true" /> : null}
                                    <span>{tab.label}</span>
                                    <span className="gelia-tag-capsula-conteo tabular-nums">{formatearConteo(conteo)}</span>
                                </button>
                            );
                        })}
                    </div>
                    <div className="gelia-tag-filtros-consulta">
                        <div className="gelia-tag-filtros-buscar" role="search">
                            {campoBusqueda}
                        </div>
                        {botonFiltros}
                    </div>
                </div>
            ) : (
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className={`gelia-segment gelia-tag-filtros-tabs ${GELIA_SEGMENT_TABS_SCROLL} w-full p-1 shadow-sm lg:w-auto`}>
                    <div className={GELIA_SEGMENT_TABS_TRACK_COMPACT} role="group" aria-label="Estado de la solicitud">
                        {tabs.map((tab) => (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => onCambiarTab(tab.id)}
                                className={`gelia-segment-btn ${FOCUS}`}
                                data-active={tabActiva === tab.id}
                                aria-pressed={tabActiva === tab.id}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex w-full flex-col gap-2 sm:flex-row lg:max-w-xl lg:flex-1">
                    <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row">
                        {campoBusqueda}
                        {botonBuscar}
                    </div>
                    {botonFiltros}
                </div>
            </div>
            )}

            {chips.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {chips.map((chip) => (
                        <button
                            key={chip.clave}
                            type="button"
                            onClick={() => quitarChip(chip.clave)}
                            className={`${GELIA_CHIP} max-w-full gap-1.5 ${FOCUS}`}
                            title={chip.etiqueta}
                        >
                            <span className="truncate">{chip.etiqueta}</span>
                            <X className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                            <span className="sr-only">Quitar {chip.etiqueta}</span>
                        </button>
                    ))}
                </div>
            )}

            {mostrarAdicionales && (
                <div id={`${idPrefixFechas}-adicionales`} className="gelia-tag-filtros-panel theme-surface theme-no-blur space-y-4 rounded-xl border theme-border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <p className="m-0 flex items-center gap-2 text-sm font-medium theme-text-main" {...iconoPanelFiltros.triggerProps}>
                            {iconoPanelFiltros.nodo} Filtros
                        </p>
                        <p className="m-0 hidden text-xs theme-text-muted sm:block">
                            Los cambios se aplican al pulsar {variante === 'tag-lista' ? 'Aplicar filtros' : 'Buscar'}
                        </p>
                        {(filtrosActivos > 0 || tipoFecha !== 'TODAS') && (
                            <button
                                type="button"
                                onClick={limpiarPanelAdicionales}
                                className={`inline-flex shrink-0 items-center gap-1 text-sm theme-text-muted transition-colors hover:text-[var(--color-peligro)] ${FOCUS}`}
                            >
                                <X className="h-3.5 w-3.5" aria-hidden="true" /> Limpiar
                            </button>
                        )}
                    </div>

                    {variante === 'tag-lista' && <EncabezadoGrupo icono={Calendar}>Periodo y responsable</EncabezadoGrupo>}
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div className="flex flex-col gap-1.5">
                            <label htmlFor={`${idPrefixFechas}-tipo`} className={`${THEME_LABEL} flex items-center gap-1`}>
                                <Calendar className="h-3 w-3" aria-hidden="true" /> Periodo
                            </label>
                            <select
                                id={`${idPrefixFechas}-tipo`}
                                value={tipoFechaLocal}
                                onChange={(e) => setTipoFechaLocal(e.target.value)}
                                className={`${THEME_SELECT} w-full`}
                            >
                                <option value="TODAS">Histórico completo</option>
                                <option value="HOY">Solo hoy</option>
                                <option value="AYER">Ayer</option>
                                <option value="SEMANA">Esta semana</option>
                                <option value="MES">Este mes</option>
                                {filtrosClientes && <><option value="MES_ANTERIOR">Mes anterior</option><option value="MES_ESPECIFICO">Elegir mes</option></>}
                                <option value="PERSONALIZADO">Rango personalizado</option>
                            </select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label htmlFor={`${idPrefixFechas}-responsable`} className={THEME_LABEL}>Responsable comercial</label>
                            <select
                                id={`${idPrefixFechas}-responsable`}
                                value={vendedorLocal}
                                onChange={(e) => setVendedorLocal(e.target.value)}
                                className={`${THEME_SELECT} w-full`}
                            >
                                <option value="">Todos los responsables</option>
                                {vendedores.map((v) => (
                                    <option key={v.id} value={v.id}>{v.name}</option>
                                ))}
                            </select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label htmlFor={`${idPrefixFechas}-motivo`} className={`${THEME_LABEL} flex items-center gap-1`}>
                                <AlertOctagon className="h-3 w-3" aria-hidden="true" /> {variante === 'tag-lista' ? 'Motivo del error' : 'Motivo de incidencia'}
                            </label>
                            <select
                                id={`${idPrefixFechas}-motivo`}
                                value={motivoLocal}
                                onChange={(e) => setMotivoLocal(e.target.value)}
                                className={`${THEME_SELECT} w-full`}
                                aria-describedby={`${idPrefixFechas}-motivo-ayuda`}
                            >
                                <option value="">Todos los motivos</option>
                                <option value="error_reportado">Reportadas (error)</option>
                                <option value="vencimiento_pago">Pago vencido</option>
                                <option value="pago_insuficiente">Pago insuficiente</option>
                            </select>
                            <p id={`${idPrefixFechas}-motivo-ayuda`} className="m-0 text-xs theme-text-muted">
                                Al filtrar por motivo se muestran solicitudes incorrectas, incluyendo registros anteriores sin motivo asignado.
                            </p>
                        </div>
                    </div>

                    {filtrosClientes && <>
                        {variante === 'tag-lista' && <EncabezadoGrupo icono={Filter}>Lista y tipo de cliente</EncabezadoGrupo>}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            {[
                                ['lista', 'Lista de la solicitud', listaLocal, setListaLocal, listas, 'Todas las listas'],
                                ['cliente', 'Tipo registrado en la solicitud', tipoClienteLocal, setTipoClienteLocal, [...tiposCliente, { id: 'SIN_TIPO', nombre: 'Sin tipo registrado' }], 'Todos los tipos'],
                                ['tag', 'TAG actual del cliente', tagLocal, setTagLocal, [{ id: 'con_tag', nombre: 'Clientes con TAG' }, { id: 'sin_tag', nombre: 'Clientes sin TAG' }], 'Con y sin TAG'],
                            ].map(([key, label, value, setter, options, placeholder]) => <div key={key} className="flex flex-col gap-1.5"><label htmlFor={`${idPrefixFechas}-${key}`} className={THEME_LABEL}>{label}</label><select id={`${idPrefixFechas}-${key}`} value={value} onChange={event => setter(event.target.value)} className={`${THEME_SELECT} w-full`}><option value="">{placeholder}</option>{options.map(option => <option key={option.id} value={option.id}>{option.nombre}</option>)}</select></div>)}
                        </div>
                        {variante === 'tag-lista' && <>
                            <EncabezadoGrupo icono={Workflow}>Operación y origen del cliente</EncabezadoGrupo>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div className="flex flex-col gap-1.5">
                                    <label htmlFor={`${idPrefixFechas}-proceso`} className={THEME_LABEL}>Tipo de operación</label>
                                    <select id={`${idPrefixFechas}-proceso`} value={extraLocal.proceso_id || ''} onChange={(e) => cambiarExtra('proceso_id', e.target.value)} className={`${THEME_SELECT} w-full`}>
                                        <option value="">Todas las operaciones</option>
                                        {procesos.map((p) => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                    </select>
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <label htmlFor={`${idPrefixFechas}-cliente-numero`} className={THEME_LABEL}>Número de cliente</label>
                                    <input id={`${idPrefixFechas}-cliente-numero`} type="text" inputMode="numeric" autoComplete="off" value={extraLocal.cliente_numero || ''} onChange={(e) => cambiarExtra('cliente_numero', e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); aplicarConsulta(); } }} className={`${THEME_INPUT} w-full`} />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <label htmlFor={`${idPrefixFechas}-heredado`} className={THEME_LABEL}>Origen del cliente</label>
                                    <select id={`${idPrefixFechas}-heredado`} value={extraLocal.es_heredado || ''} onChange={(e) => cambiarExtra('es_heredado', e.target.value)} className={`${THEME_SELECT} w-full`}>
                                        <option value="">Todos</option>
                                        <option value="1">Clientes heredados</option>
                                        <option value="0">Clientes no heredados</option>
                                    </select>
                                </div>
                            </div>
                            <EncabezadoGrupo icono={Wallet}>Pago y seguimiento</EncabezadoGrupo>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                {FILTROS_PAGO.map(([clave, etiqueta, opciones, todos]) => (
                                    <div key={clave} className="flex flex-col gap-1.5">
                                        <label htmlFor={`${idPrefixFechas}-${clave}`} className={THEME_LABEL}>{etiqueta}</label>
                                        <select id={`${idPrefixFechas}-${clave}`} value={extraLocal[clave] || ''} onChange={(e) => cambiarExtra(clave, e.target.value)} className={`${THEME_SELECT} w-full`}>
                                            <option value="">{todos}</option>
                                            {opciones.map(([valor, nombre]) => <option key={valor} value={valor}>{nombre}</option>)}
                                        </select>
                                    </div>
                                ))}
                            </div>
                            {mostrarEliminadas && (
                                <>
                                    <EncabezadoGrupo icono={Trash2}>Papelera</EncabezadoGrupo>
                                    <div className="flex max-w-sm flex-col gap-1.5">
                                        <label htmlFor={`${idPrefixFechas}-eliminadas`} className={THEME_LABEL}>Solicitudes eliminadas</label>
                                        <select
                                            id={`${idPrefixFechas}-eliminadas`}
                                            value={verEliminadas ? '1' : ''}
                                            onChange={(e) => setVerEliminadas(e.target.value === '1')}
                                            className={`${THEME_SELECT} w-full`}
                                        >
                                            <option value="">Ocultar eliminadas</option>
                                            <option value="1">Mostrar eliminadas{conteosTab.ELIMINADAS != null ? ` (${formatearConteo(conteosTab.ELIMINADAS)})` : ''}</option>
                                        </select>
                                        <p className="m-0 text-xs theme-text-muted">La papelera se consulta desde este filtro, no desde las cápsulas de estado.</p>
                                    </div>
                                </>
                            )}
                        </>}
                        <p className="m-0 text-xs theme-text-muted">El periodo corresponde a la creación de la solicitud. Para consultar listas aprobadas, combina la lista con el indicador Vigentes. El TAG refleja la asignación actual del cliente.</p>
                    </>}
                    {tipoFechaLocal === 'MES_ESPECIFICO' && <div className="flex flex-col gap-1.5 max-w-sm"><label htmlFor={`${idPrefixFechas}-mes`} className={THEME_LABEL}>Mes y año</label><input id={`${idPrefixFechas}-mes`} type="month" value={fechaInicioLocal?.slice(0, 7) || ''} className={THEME_INPUT} onChange={event => {
                        const mes = event.target.value;
                        setFechaInicioLocal(mes ? `${mes}-01` : '');
                        const [year, month] = mes.split('-').map(Number);
                        setFechaFinLocal(mes ? `${mes}-${String(new Date(year, month, 0).getDate()).padStart(2, '0')}` : '');
                    }} /></div>}
                    {tipoFechaLocal === 'PERSONALIZADO' && (
                        <RangoFechasPersonalizado
                            idPrefix={idPrefixFechas}
                            fechaInicio={fechaInicioLocal}
                            fechaFin={fechaFinLocal}
                            mostrarBotonAplicar={false}
                            onCambio={({ fecha_inicio, fecha_fin }) => {
                                if (fecha_inicio !== undefined) setFechaInicioLocal(fecha_inicio);
                                if (fecha_fin !== undefined) setFechaFinLocal(fecha_fin);
                            }}
                        />
                    )}
                    {errorFechas && <p role="alert" className="text-sm theme-text-peligro">{errorFechas}</p>}
                    {variante === 'tag-lista' && <div className="flex justify-end"><button type="button" className="theme-btn-primary" onClick={aplicarConsulta}>Aplicar filtros</button></div>}
                </div>
            )}
        </div>
    );
}
