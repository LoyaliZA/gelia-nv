import { useEffect, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronDown, FileUp, HelpCircle, PenLine, TrendingUp } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import {
    GELIA_BTN_OUTLINE,
    GELIA_MODAL_TITLE,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    geliaCardClass,
} from '../../utils/geliaTheme';
import EscalonamientoModal from './Partials/EscalonamientoModal';
import DialogosDocumentosEscalonamiento from './Partials/DialogosDocumentosEscalonamiento';
import VincularDevolucionEscalonamiento from './Partials/VincularDevolucionEscalonamiento';
import TablaClientesEscalonamiento from './Partials/TablaClientesEscalonamiento';
import TablaDocumentosEscalonamiento from './Partials/TablaDocumentosEscalonamiento';
import EscalonamientoIncidencias from './Partials/EscalonamientoIncidencias';
import { dinero, MESES } from './Partials/escalonamientoUi';

const TABS = [
    { id: 'clientes', label: 'Clientes' },
    { id: 'documentos', label: 'Documentos' },
    { id: 'devoluciones', label: 'Devoluciones pendientes' },
    { id: 'incidencias', label: 'Incidencias' },
    { id: 'cierre', label: 'Cierre e historial' },
];

function numeroTexto(valor) {
    return String(valor ?? 0);
}

export default function Index({
    auth,
    periodo,
    periodoOperativo = null,
    periodos = [],
    resumenPeriodo = null,
    mensajeDocumentos = null,
    clientes = { data: [], paginacion: {} },
    documentos = { data: [], paginacion: {} },
    filtrosClientes = {},
    opcionesListas = [],
    clienteSeleccionado = null,
    puedeOperar = false,
    puedeOperarDocumentos = false,
    revisionCierrePrevio = null,
    sugerencia,
    incidencias = [],
    exclusionesInformativas = [],
    filtros_incidencias = {},
    opcionesIncidencias = {},
    previsualizacion = null,
    devolucionesPendientes = [],
    aplicacionesActivas = [],
}) {
    const { flash } = usePage().props;
    const [abrirPeriodo, setAbrirPeriodo] = useState(false);
    const [abrirHistorico, setAbrirHistorico] = useState(false);
    const [importar, setImportar] = useState(Boolean(previsualizacion));
    const [capturar, setCapturar] = useState(false);
    const [ayuda, setAyuda] = useState(false);
    const tabActiva = filtrosClientes.tab || 'clientes';

    const formPeriodo = useForm({
        anio: sugerencia?.anio || new Date().getFullYear(),
        mes: sugerencia?.mes || new Date().getMonth() + 1,
    });
    const formHistorico = useForm({
        anio: sugerencia?.anio || new Date().getFullYear(),
        mes: Math.max(1, (sugerencia?.mes || new Date().getMonth() + 1) - 1),
    });

    useEffect(() => {
        if (previsualizacion) setImportar(true);
    }, [previsualizacion]);

    const cambiarPeriodoVista = (event) => {
        const valor = event.target.value;
        router.get(route('escalonamiento.index'), valor ? { periodo_id: valor, tab: tabActiva } : { tab: tabActiva }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const irTab = (id) => {
        router.get(route('escalonamiento.index'), {
            periodo_id: periodo?.id,
            tab: id,
            q: filtrosClientes.q || undefined,
            filtro_rapido: filtrosClientes.filtro_rapido || undefined,
        }, { preserveState: true, preserveScroll: true });
    };

    const enviarPeriodo = (event) => {
        event.preventDefault();
        formPeriodo.post(route('escalonamiento.periodos.abrir'), {
            onSuccess: () => {
                setAbrirPeriodo(false);
                formPeriodo.reset();
            },
        });
    };

    const enviarHistorico = (event) => {
        event.preventDefault();
        formHistorico.post(route('escalonamiento.periodos.historial'), {
            onSuccess: () => {
                setAbrirHistorico(false);
                formHistorico.reset();
            },
        });
    };

    const contadoresTab = {
        documentos: resumenPeriodo?.documentos_registrados ?? 0,
        devoluciones: resumenPeriodo?.pendientes_vinculo ?? devolucionesPendientes.length,
        incidencias: resumenPeriodo?.incidencias_abiertas ?? incidencias.length,
    };

    const historial = periodo?.consulta_historial;

    const cerrarPeriodo = () => {
        setAbrirPeriodo(false);
        formPeriodo.clearErrors();
    };

    const cerrarHistorico = () => {
        setAbrirHistorico(false);
        formHistorico.clearErrors();
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Escalonamiento" />
            <GeliaPageShell className="space-y-6 pb-10">
                <GeliaTituloCard
                    eyebrow="Finanzas"
                    title="Escalonamiento"
                    description="Compras del mes, listas y cierre"
                    icon={TrendingUp}
                >
                    <div className="flex flex-wrap items-end gap-2">
                        {periodos.length > 0 && (
                            <label className="space-y-1 w-full sm:w-auto sm:min-w-[12rem] sm:max-w-[18rem]">
                                <span className={THEME_LABEL}>Período</span>
                                <select className={`${THEME_INPUT} w-full`} value={periodo?.id || ''} onChange={cambiarPeriodoVista}>
                                    {periodos.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {MESES[p.mes - 1]} {p.anio} ({p.estado})
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        {puedeOperarDocumentos && !historial && (
                            <>
                                <button type="button" className={THEME_BTN_PRIMARY} onClick={() => setImportar(true)}>
                                    <FileUp className="w-4 h-4 shrink-0" aria-hidden />
                                    Importar
                                </button>
                                <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => setCapturar(true)}>
                                    <PenLine className="w-4 h-4 shrink-0" aria-hidden />
                                    Registrar documento
                                </button>
                            </>
                        )}
                        <Link href={route('escalonamiento.cierre')} className={GELIA_BTN_OUTLINE}>
                            Preparar cierre
                        </Link>
                    </div>
                    {periodo ? (
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm theme-text-muted">
                            <span>
                                Estado: <strong className="theme-text-main">{periodo.estado}</strong>
                                {historial && ' · Consulta de historial'}
                            </span>
                            <span title={periodo.corte || undefined}>
                                Última carga: {periodo.corte_legible || 'sin corte'}
                            </span>
                            {periodoOperativo && periodoOperativo.id !== periodo.id && (
                                <span>
                                    Carga operativa: {MESES[periodoOperativo.mes - 1]} {periodoOperativo.anio}
                                </span>
                            )}
                            <button
                                type="button"
                                className="inline-flex items-center gap-1 text-sm font-semibold theme-text-main"
                                onClick={() => setAyuda((v) => !v)}
                                aria-expanded={ayuda}
                            >
                                <HelpCircle className="w-4 h-4" aria-hidden />
                                Ayuda
                                <ChevronDown className={`w-4 h-4 transition-transform ${ayuda ? 'rotate-180' : ''}`} aria-hidden />
                            </button>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <p className="theme-text-main m-0 text-sm">No hay un período abierto.</p>
                            {puedeOperar && (
                                <button type="button" className={THEME_BTN_PRIMARY} onClick={() => setAbrirPeriodo(true)}>
                                    Abrir período
                                </button>
                            )}
                        </div>
                    )}
                </GeliaTituloCard>

                {flash?.success && (
                    <p className={`${geliaCardClass('p-3')} text-sm theme-text-exito m-0`} role="status">{flash.success}</p>
                )}
                {flash?.error && (
                    <p className={`${geliaCardClass('p-3')} text-sm theme-text-peligro m-0`} role="alert">{flash.error}</p>
                )}

                {revisionCierrePrevio && (
                    <p className={`${geliaCardClass('p-3')} text-sm theme-text-main m-0`}>
                        Revisión del cierre de {revisionCierrePrevio.periodo}: {revisionCierrePrevio.cambios_registrados} cambio(s),
                        {revisionCierrePrevio.divergencias_operativa} divergencia(s) con lista operativa.
                    </p>
                )}

                {ayuda && (
                    <p className={`${geliaCardClass('p-4')} text-sm theme-text-muted m-0`}>
                        El acumulado proviene de documentos del período. Las devoluciones descuentan solo tras vincular la compra nueva.
                        Las solicitudes de tag no modifican este monto. Corte técnico: {periodo?.corte || '—'} ({periodo?.zona_horaria}).
                    </p>
                )}

                {resumenPeriodo && (
                    <section className={`${geliaCardClass()} p-4 md:p-6`} aria-label="Resumen del período">
                        <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                            <Indicador etiqueta="Ventas elegibles" valor={dinero(resumenPeriodo.ventas_elegibles)} />
                            <Indicador etiqueta="Devoluciones aplicadas" valor={dinero(resumenPeriodo.devoluciones_aplicadas)} />
                            <Indicador etiqueta="Venta neta" valor={dinero(resumenPeriodo.venta_neta)} />
                            <Indicador etiqueta="Cambios propuestos" valor={numeroTexto(resumenPeriodo.clientes_con_cambios_propuestos)} />
                            <Indicador
                                etiqueta="Incidencias abiertas"
                                valor={numeroTexto(resumenPeriodo.incidencias_abiertas)}
                                onClick={() => irTab('incidencias')}
                            />
                            <Indicador
                                etiqueta="Devoluciones pendientes"
                                valor={numeroTexto(resumenPeriodo.pendientes_vinculo)}
                                onClick={() => irTab('devoluciones')}
                            />
                        </div>
                        <p className="m-0 mt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">
                            Totales del período en MXN. Los filtros de la tabla no modifican este resumen.
                        </p>
                    </section>
                )}

                <div className={`${GELIA_SEGMENT_TABS_SCROLL} overflow-x-auto`}>
                    <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 h-14 shadow-sm min-w-max`} role="tablist" aria-label="Secciones de escalonamiento">
                        {TABS.map((tab) => (
                            <button
                                key={tab.id}
                                type="button"
                                role="tab"
                                aria-selected={tabActiva === tab.id}
                                data-active={tabActiva === tab.id}
                                className="gelia-segment-btn whitespace-nowrap gap-1.5"
                                onClick={() => irTab(tab.id)}
                            >
                                {tab.label}
                                {contadoresTab[tab.id] > 0 && (
                                    <span className="text-[9px] font-black px-1.5 py-0.5 rounded-md theme-element border theme-border">
                                        {contadoresTab[tab.id]}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                </div>

                {tabActiva === 'clientes' && (
                    <TablaClientesEscalonamiento
                        periodo={periodo}
                        clientes={clientes}
                        filtros={filtrosClientes}
                        opcionesListas={opcionesListas}
                        mensajeDocumentos={mensajeDocumentos}
                    />
                )}

                {tabActiva === 'documentos' && (
                    <TablaDocumentosEscalonamiento
                        periodo={periodo}
                        documentos={documentos}
                        busqueda={filtrosClientes.q_doc || ''}
                        filtros={filtrosClientes}
                        clienteSeleccionado={clienteSeleccionado}
                    />
                )}

                {tabActiva === 'devoluciones' && (
                    <section className={geliaCardClass('p-4')}>
                        <VincularDevolucionEscalonamiento
                            pendientes={devolucionesPendientes}
                            aplicaciones={aplicacionesActivas}
                            puedeOperar={puedeOperar}
                        />
                    </section>
                )}

                {tabActiva === 'incidencias' && (
                    <section className={geliaCardClass('p-4')}>
                        <EscalonamientoIncidencias
                            incidencias={incidencias}
                            exclusiones={exclusionesInformativas}
                            filtros={filtros_incidencias}
                            opciones={opcionesIncidencias}
                            periodo={periodo}
                            puedeOperar={puedeOperar}
                            clienteSeleccionado={clienteSeleccionado}
                        />
                    </section>
                )}

                {tabActiva === 'cierre' && (
                    <section className={`${geliaCardClass('p-4')} space-y-3 text-sm`}>
                        <p className="theme-text-muted m-0">
                            Simula y autoriza el cierre del período, consulta el reporte de ajustes y revisa cierres anteriores.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('escalonamiento.cierre', periodo ? { periodo_id: periodo.id } : {})} className={THEME_BTN_PRIMARY}>
                                Comparación de cierre
                            </Link>
                            <Link href={route('escalonamiento.conciliacion')} className={GELIA_BTN_OUTLINE}>
                                Conciliación de solicitudes
                            </Link>
                            <Link href={route('escalonamiento.metricas', periodo ? { periodo_id: periodo.id } : {})} className={GELIA_BTN_OUTLINE}>
                                Métricas detalladas
                            </Link>
                        </div>
                        {puedeOperar && (
                            <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => setAbrirHistorico(true)}>
                                Abrir período histórico
                            </button>
                        )}
                    </section>
                )}
            </GeliaPageShell>

            <EscalonamientoModal abierto={abrirPeriodo} onClose={cerrarPeriodo} labelledBy="titulo-abrir-periodo">
                <form onSubmit={enviarPeriodo} className="p-8 md:p-10 space-y-5">
                    <h2 id="titulo-abrir-periodo" className={GELIA_MODAL_TITLE}>Abrir período</h2>
                    <label className="block space-y-1">
                        <span className={THEME_LABEL}>Año</span>
                        <input type="number" className={THEME_INPUT} value={formPeriodo.data.anio} onChange={(e) => formPeriodo.setData('anio', e.target.value)} />
                        {formPeriodo.errors.anio && <span className="block text-xs theme-text-peligro">{formPeriodo.errors.anio}</span>}
                    </label>
                    <label className="block space-y-1">
                        <span className={THEME_LABEL}>Mes</span>
                        <select className={THEME_INPUT} value={formPeriodo.data.mes} onChange={(e) => formPeriodo.setData('mes', e.target.value)}>
                            {MESES.map((nombre, indice) => (
                                <option key={nombre} value={indice + 1}>{nombre}</option>
                            ))}
                        </select>
                        {formPeriodo.errors.mes && <span className="block text-xs theme-text-peligro">{formPeriodo.errors.mes}</span>}
                    </label>
                    <div className="flex justify-end gap-2">
                        <button type="button" className={THEME_BTN_SECONDARY} onClick={cerrarPeriodo}>Cerrar</button>
                        <button type="submit" className={THEME_BTN_PRIMARY} disabled={formPeriodo.processing}>Abrir</button>
                    </div>
                </form>
            </EscalonamientoModal>

            <EscalonamientoModal abierto={abrirHistorico} onClose={cerrarHistorico} labelledBy="titulo-abrir-historico">
                <form onSubmit={enviarHistorico} className="p-8 md:p-10 space-y-5">
                    <h2 id="titulo-abrir-historico" className={GELIA_MODAL_TITLE}>Abrir período histórico</h2>
                    <label className="block space-y-1">
                        <span className={THEME_LABEL}>Año</span>
                        <input type="number" className={THEME_INPUT} value={formHistorico.data.anio} onChange={(e) => formHistorico.setData('anio', e.target.value)} />
                        {formHistorico.errors.anio && <span className="block text-xs theme-text-peligro">{formHistorico.errors.anio}</span>}
                    </label>
                    <label className="block space-y-1">
                        <span className={THEME_LABEL}>Mes</span>
                        <select className={THEME_INPUT} value={formHistorico.data.mes} onChange={(e) => formHistorico.setData('mes', e.target.value)}>
                            {MESES.map((nombre, indice) => (
                                <option key={nombre} value={indice + 1}>{nombre}</option>
                            ))}
                        </select>
                        {formHistorico.errors.mes && <span className="block text-xs theme-text-peligro">{formHistorico.errors.mes}</span>}
                    </label>
                    <div className="flex justify-end gap-2">
                        <button type="button" className={THEME_BTN_SECONDARY} onClick={cerrarHistorico}>Cerrar</button>
                        <button type="submit" className={THEME_BTN_PRIMARY} disabled={formHistorico.processing}>Preparar</button>
                    </div>
                </form>
            </EscalonamientoModal>

            <DialogosDocumentosEscalonamiento
                importar={importar}
                onCerrarImportar={() => setImportar(false)}
                previsualizacion={previsualizacion}
                capturar={capturar}
                onCerrarCaptura={() => setCapturar(false)}
                periodo={periodo}
                periodoOperativo={periodoOperativo}
            />
        </AppLayout>
    );
}

function Indicador({ etiqueta, valor, onClick }) {
    const contenido = (
        <>
            <span className="block text-[10px] font-black uppercase tracking-widest theme-text-muted">{etiqueta}</span>
            <span className="block text-2xl font-black italic tracking-tighter theme-text-main tabular-nums mt-1">{valor}</span>
        </>
    );
    const clase = 'rounded-2xl border theme-border theme-element p-4 text-left w-full';
    if (onClick) {
        return (
            <button type="button" className={`${clase} hover:border-[var(--color-primario)] transition-colors`} onClick={onClick}>
                {contenido}
            </button>
        );
    }
    return <div className={clase}>{contenido}</div>;
}
