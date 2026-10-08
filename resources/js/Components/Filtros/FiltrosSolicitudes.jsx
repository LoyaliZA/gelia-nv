import React, { useEffect, useState } from 'react';
import { Search, Filter, AlertOctagon, SlidersHorizontal, X, Calendar } from 'lucide-react';
import RangoFechasPersonalizado from '@/Components/Filtros/RangoFechasPersonalizado';
import { GELIA_CHIP, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK_COMPACT, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '@/utils/geliaTheme';

const TABS = [
    { id: 'TODAS', label: 'Todas' },
    { id: 'PENDIENTES', label: 'Pendientes' },
    { id: 'RESPONDIDAS', label: 'Respondidas' },
    { id: 'INCORRECTAS', label: 'Incorrectas' },
    { id: 'CANCELADAS', label: 'Canceladas' },
    { id: 'ELIMINADAS', label: 'Eliminadas' },
];

const ETIQUETA_PERIODO = {
    HOY: 'Hoy',
    AYER: 'Ayer',
    SEMANA: 'Esta semana',
    MES: 'Este mes',
    PERSONALIZADO: 'Rango personalizado',
};

const ETIQUETA_MOTIVO = {
    error_reportado: 'Reportadas (error)',
    vencimiento_pago: 'Pago vencido',
    pago_insuficiente: 'Pago insuficiente',
};

const FOCUS = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]';

/**
 * Filtros compartidos: módulo Solicitudes y Reportes de solicitudes financieras.
 */
export default function FiltrosSolicitudes({
    tabActiva,
    busqueda,
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
}) {
    const tabs = mostrarEliminadas ? TABS : TABS.filter((tab) => tab.id !== 'ELIMINADAS');

    const [mostrarAdicionales, setMostrarAdicionales] = useState(
        filtrosActivos > 0 || tipoFecha !== 'TODAS'
    );
    const [busquedaLocal, setBusquedaLocal] = useState(busqueda);
    const [tipoFechaLocal, setTipoFechaLocal] = useState(tipoFecha);
    const [fechaInicioLocal, setFechaInicioLocal] = useState(fechaInicio);
    const [fechaFinLocal, setFechaFinLocal] = useState(fechaFin);
    const [vendedorLocal, setVendedorLocal] = useState(filtroVendedor);
    const [motivoLocal, setMotivoLocal] = useState(filtroMotivo);

    useEffect(() => {
        setBusquedaLocal(busqueda);
    }, [busqueda]);

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
        onAplicarFiltros({
            q: busquedaLocal,
            tipo_fecha: tipoFechaLocal,
            fecha_inicio: fechaInicioLocal,
            fecha_fin: fechaFinLocal,
            vendedor_id: vendedorLocal,
            motivo_incorrecta: motivoLocal,
            tab: motivoLocal ? 'INCORRECTAS' : undefined,
        });
    };

    const limpiarPanelAdicionales = () => {
        setTipoFechaLocal('TODAS');
        setFechaInicioLocal('');
        setFechaFinLocal('');
        setVendedorLocal('');
        setMotivoLocal('');
        onLimpiarAdicionales?.();
    };

    const quitarChip = (clave) => {
        if (clave === 'q') {
            setBusquedaLocal('');
            onAplicarFiltros({ q: '' });
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
        }
    };

    const nombreResponsable = vendedores.find((v) => String(v.id) === String(filtroVendedor))?.name;
    const etiquetaPeriodo = tipoFecha === 'PERSONALIZADO' && fechaInicio && fechaFin
        ? `${fechaInicio} – ${fechaFin}`
        : ETIQUETA_PERIODO[tipoFecha];

    const chips = [
        busqueda ? { clave: 'q', etiqueta: `Búsqueda: ${busqueda}` } : null,
        tipoFecha && tipoFecha !== 'TODAS' ? { clave: 'periodo', etiqueta: etiquetaPeriodo || 'Periodo' } : null,
        filtroVendedor ? { clave: 'vendedor', etiqueta: nombreResponsable || 'Responsable comercial' } : null,
        filtroMotivo ? { clave: 'motivo', etiqueta: ETIQUETA_MOTIVO[filtroMotivo] || filtroMotivo } : null,
    ].filter(Boolean);

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className={`gelia-segment ${GELIA_SEGMENT_TABS_SCROLL} w-full p-1 lg:w-auto`}>
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
                        <div className="theme-field-with-icon relative min-w-0 flex-1">
                            <Search className="theme-field-icon" aria-hidden="true" />
                            <input
                                type="search"
                                placeholder="Buscar folio o cliente…"
                                value={busquedaLocal}
                                onChange={(e) => setBusquedaLocal(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        aplicarConsulta();
                                    }
                                }}
                                enterKeyHint="search"
                                autoComplete="off"
                                aria-label="Buscar folio o cliente"
                                className={`${THEME_INPUT} w-full`}
                            />
                        </div>
                        <button
                            type="button"
                            onClick={aplicarConsulta}
                            className={`inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white sm:w-auto ${FOCUS}`}
                            style={{ backgroundColor: 'var(--color-primario)' }}
                            aria-label={etiquetaBuscar}
                        >
                            <Search className="h-4 w-4 shrink-0" aria-hidden="true" />
                            Buscar
                        </button>
                    </div>
                    <button
                        type="button"
                        onClick={() => setMostrarAdicionales((v) => !v)}
                        aria-expanded={mostrarAdicionales}
                        className={`inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors sm:w-auto ${FOCUS} ${mostrarAdicionales || filtrosActivos > 0 || tipoFecha !== 'TODAS' ? 'border-[var(--color-primario)] text-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)]' : 'theme-border theme-element theme-text-muted hover:border-[var(--color-primario)]'}`}
                    >
                        <SlidersHorizontal className="h-4 w-4 shrink-0" aria-hidden="true" />
                        <span>Filtros</span>
                        {filtrosActivos > 0 && (
                            <span className="flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-semibold text-white tabular-nums" style={{ backgroundColor: 'var(--color-primario)' }}>
                                {filtrosActivos}
                            </span>
                        )}
                    </button>
                </div>
            </div>

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
                <div className="theme-surface space-y-4 rounded-xl border theme-border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <p className="m-0 flex items-center gap-2 text-sm font-medium theme-text-main">
                            <Filter className="h-3.5 w-3.5" aria-hidden="true" /> Filtros
                        </p>
                        <p className="m-0 hidden text-xs theme-text-muted sm:block">
                            Los cambios se aplican al pulsar Buscar
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
                                <AlertOctagon className="h-3 w-3" aria-hidden="true" /> Motivo de incidencia
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
                </div>
            )}
        </div>
    );
}
