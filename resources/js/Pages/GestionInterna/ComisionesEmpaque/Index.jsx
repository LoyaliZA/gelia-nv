import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { lazy, Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { AlertCircle, CalendarDays, CheckCircle2, Clock, History, Layers, RefreshCw, Settings, Wallet } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import { GELIA_ICON_BOX, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL } from '@/utils/geliaTheme';
import { emitirToastGelia } from '@/utils/geliaToast';
import {
    bloquearConsultas, cancelarConsulta, confirmarAntesDeConsultar, invalidarPrecargas, ir, momentoLegible,
    observarConsultas, precargarComisiones,
} from './compartido';
import NavegacionComisiones, {
    itemsConfiguracion, itemsLiquidacion, itemsPrincipales, seccionConfiguracionActiva,
} from './NavegacionComisiones';
import Reporte from './Reporte';
import useActualizacionComisiones, { ProteccionEdiciones } from './useActualizacionComisiones';

const Acumulado = lazy(() => import('./Empaques').then((m) => ({ default: m.Acumulado })));
const Cortes = lazy(() => import('./Liquidaciones').then((m) => ({ default: m.Cortes })));
const Pagos = lazy(() => import('./Liquidaciones').then((m) => ({ default: m.Pagos })));
const Preparar = lazy(() => import('./Liquidaciones').then((m) => ({ default: m.Preparar })));
const Periodos = lazy(() => import('./Periodos'));
const Configuracion = lazy(() => import('./Configuracion'));
const Productividad = lazy(() => import('./Productividad'));

function avisosDePagina({ sin_areas, flash }) {
    const items = [];
    if (sin_areas) items.push({ mensaje: 'No hay áreas asignadas.', tipo: 'aviso' });
    if (flash?.success) items.push({ mensaje: flash.success, tipo: 'success' });
    if (flash?.error) items.push({ mensaje: flash.error, tipo: 'error' });
    if (flash?.warning) items.push({ mensaje: flash.warning, tipo: 'aviso' });
    const vistos = new Set();
    return items.filter((item) => {
        const texto = String(item.mensaje).trim();
        if (!texto || vistos.has(texto)) return false;
        vistos.add(texto);
        item.mensaje = texto;
        return true;
    });
}

function estadoActualizacion(sincronizacion) {
    const fecha = sincronizacion?.finalizado_at ? momentoLegible(sincronizacion.finalizado_at) : null;
    const estados = {
        en_curso: ['Procesando cambios', Clock],
        cambios_pendientes: ['Cambios pendientes', Clock],
        error: ['Error recuperable', AlertCircle],
        rango_vencido: ['Rango de incorporación vencido', AlertCircle],
        sin_actividad: ['Servicio sin actividad reciente', Clock],
        sin_acceso: ['El acceso al área cambió', AlertCircle],
        omitida: ['Sincronización sin configurar', Clock],
        pendiente: ['Pendiente de primera sincronización', Clock],
        ok: ['Datos actualizados', CheckCircle2],
    };
    const [etiqueta, Icono] = estados[sincronizacion?.estado] || estados.pendiente;
    const detalle = sincronizacion?.estado === 'ok'
        ? (sincronizacion.automatizacion_reciente ? 'Automatización activa' : fecha)
        : ['error', 'rango_vencido', 'omitida'].includes(sincronizacion?.estado) ? sincronizacion.mensaje : null;

    return { etiqueta, detalle, Icono, fecha };
}

const FILTROS_EMPAQUES = ['buscar', 'cliente', 'remision', 'pedido', 'empacador', 'empacador_id', 'empacador_nombre', 'situacion', 'vista'];

function ProgresoConsulta({ consulta }) {
    const [visible, setVisible] = useState(0);
    useEffect(() => {
        setVisible(0);
        if (!consulta?.pendiente) return undefined;
        const estado = window.setTimeout(() => setVisible(1), 100);
        const esqueleto = window.setTimeout(() => setVisible(2), 1000);
        return () => { window.clearTimeout(estado); window.clearTimeout(esqueleto); };
    }, [consulta]);
    if (consulta?.error) return <div role="alert" className="flex flex-wrap items-center gap-2 border-b theme-border pb-2">
        <p className="m-0 text-sm theme-text-peligro">No se pudo cargar la consulta.</p>
        <button type="button" className={THEME_BTN_SECONDARY} onClick={consulta.reintentar}>Reintentar</button>
    </div>;
    if (!consulta?.pendiente || !visible) return null;

    return <div role="status" className="space-y-1 border-b theme-border pb-2">
        <p className="m-0 text-sm theme-text-muted">Cargando consulta…</p>
        {visible === 2 && <div aria-hidden="true" className="grid grid-cols-3 gap-2">
            {[0, 1, 2].map((i) => <span key={i} className="theme-element h-2 rounded" />)}
        </div>}
    </div>;
}

export function Index({ tab, reporte, origen_simple = false, contexto_simple = {}, puede, areas = [], fecha_inicio, fecha_fin, sincronizacion, resumen, situaciones = {}, filtros = {}, acumulado, totales, preview, cortes, corte, saldos = [], tarifas = [], tipos_caja = [], sin_areas = false, captura_nombre = '', alcances = [], periodos, periodos_opciones = [], equipo, paqueterias = [], carga_revision = null, productividad = [], motivos_comerciales = {}, repartos = [] }) {
    const { flash } = usePage().props;
    const simple = tab === 'reporte' || origen_simple;
    const areaId = filtros.area_id || '';
    const [actualizando, setActualizando] = useState(false);
    const peticionManual = useRef(null);
    const [mutacion, setMutacion] = useState(false);
    const [consulta, setConsulta] = useState(null);
    const avisos = useMemo(() => avisosDePagina({ sin_areas, flash }), [sin_areas, flash]);
    const avisosPrevios = useRef('');
    const filtrosGuardados = useRef(new Map());
    const contexto = [areaId, filtros.periodo_id, filtros.desde, filtros.hasta].join('|');
    useEffect(() => {
        const firma = avisos.map((aviso) => `${aviso.tipo}:${aviso.mensaje}`).join('\n');
        if (!firma || firma === avisosPrevios.current) return;
        avisosPrevios.current = firma;
        avisos.forEach((aviso) => emitirToastGelia(aviso.mensaje, aviso.tipo));
    }, [avisos]);
    useEffect(() => {
        if (tab !== 'acumulado') return;
        const valores = Object.fromEntries(FILTROS_EMPAQUES.filter((clave) => filtros[clave] != null).map((clave) => [clave, filtros[clave]]));
        const memoria = filtrosGuardados.current;
        memoria.delete(contexto);
        memoria.set(contexto, valores);
        if (memoria.size > 6) memoria.delete(memoria.keys().next().value);
    }, [tab, contexto, filtros]);

    const actualizacionDiscreta = useActualizacionComisiones({ estado: sincronizacion, areaId, tab, filtros, activo: !sin_areas, ocupado: mutacion || consulta?.pendiente });
    useEffect(() => confirmarAntesDeConsultar(actualizacionDiscreta.confirmarSalida), [actualizacionDiscreta.confirmarSalida]);
    useEffect(() => {
        const quitarObservador = observarConsultas(setConsulta);
        const irAtras = () => { cancelarConsulta(); invalidarPrecargas(); };
        window.addEventListener('popstate', irAtras);
        let escribiendo;
        const quitarInicio = router.on?.('start', ({ detail: { visit } }) => {
            if (visit.method === 'get') return;
            if (!visit.url?.pathname?.startsWith(new URL(route('gestion_interna.comisiones_empaque.index'), window.location.href).pathname)) return;
            escribiendo = visit.url.href;
            bloquearConsultas(true);
            cancelarConsulta();
            invalidarPrecargas();
            setMutacion(true);
        });
        const quitarFin = router.on?.('finish', ({ detail: { visit } }) => {
            if (visit.method === 'get' || visit.url?.href !== escribiendo) return;
            escribiendo = null;
            bloquearConsultas(false);
            invalidarPrecargas();
            setMutacion(false);
        });
        return () => {
            quitarObservador();
            peticionManual.current?.abort();
            quitarInicio?.();
            quitarFin?.();
            window.removeEventListener('popstate', irAtras);
            bloquearConsultas(false);
            cancelarConsulta();
            invalidarPrecargas();
        };
    }, []);

    const observado = actualizacionDiscreta.observado;
    const actualizacion = estadoActualizacion(observado);
    const IconoActualizacion = actualizacion.Icono;
    const liquidaciones = ['periodos', 'preparar', 'cortes', 'pagos'].includes(tab);
    const configuracion = ['equipo', 'ajustes', 'configuracion'].includes(tab);
    const principal = liquidaciones ? 'periodos' : configuracion ? 'configuracion' : tab;
    const seccionConfig = seccionConfiguracionActiva(tab, filtros, carga_revision);
    const identidad = observado?.contexto && [observado.contexto, observado.revision, contexto].join('/');
    const opcionesPeriodo = periodos_opciones.filter((p) => String(p.area_id) === String(areaId));

    const parametros = (destino, cambios = {}) => ({
        area_id: areaId || 'todas', periodo_id: filtros.periodo_id, desde: filtros.desde, hasta: filtros.hasta,
        ...(destino === 'acumulado' && filtrosGuardados.current.get(contexto)),
        ...(destino === 'preparar' || filtros.retorno_corte ? {
            limite_desde: filtros.limite_desde, limite_hasta: filtros.limite_hasta, incluir_tardias: filtros.incluir_tardias,
        } : {}),
        origen: filtros.origen, retorno_corte: filtros.retorno_corte, retorno_periodo_id: filtros.retorno_periodo_id, tab: destino, ...cambios,
    });
    const navegar = (destino, cambios) => {
        if (destino === tab && !cambios) return;
        actualizacionDiscreta.cancelarRecarga();
        ir(parametros(destino, cambios), { identidad: cambios ? undefined : identidad });
    };
    const volverAlReporte = (modo) => navegar('reporte', {
        area_id: contexto_simple.area_id || areaId || 'todas',
        periodo_id: contexto_simple.periodo_id,
        desde: contexto_simple.desde || filtros.desde,
        hasta: contexto_simple.hasta || filtros.hasta,
        origen: undefined, retorno_corte: undefined, retorno_periodo_id: undefined, modo,
    });
    const abrirDesdeReporte = (destino, cambios = {}) => navegar(destino, { origen: 'simple', ...cambios });
    const revisarEmpaques = (area, vista) => abrirDesdeReporte('acumulado', {
        ...Object.fromEntries(FILTROS_EMPAQUES.map((clave) => [clave, undefined])),
        area_id: area || areaId || 'todas',
        periodo_id: !area || String(area) === String(areaId) ? filtros.periodo_id : undefined,
        vista,
    });
    const configurar = (seccion, area, editar = false) => abrirDesdeReporte('configuracion', {
        editar_equipo: editar ? 1 : undefined,
        seccion, area_id: area || areaId || 'todas',
        periodo_id: !area || String(area) === String(areaId) ? filtros.periodo_id : undefined,
    });
    useEffect(() => {
        if (origen_simple && tab === 'acumulado' && filtros.vista === 'pendientes'
            && flash?.success && resumen?.por_revisar === 0 && !mutacion) volverAlReporte();
    // El guardado y los totales de la bandeja se reciben juntos.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [origen_simple, tab, filtros.vista, flash?.success, resumen?.por_revisar, mutacion]);
    const precargar = (destino) => {
        if (!simple && destino !== tab && !consulta?.pendiente && !mutacion && !actualizando && !sin_areas && !filtros.retorno_corte) precargarComisiones(parametros(destino), identidad);
    };
    useEffect(() => {
        if (simple || !identidad || !['acumulado', 'periodos', 'productividad'].includes(tab)) return undefined;
        if (actualizando || mutacion || consulta?.pendiente || sin_areas || filtros.retorno_corte) return undefined;
        const preparar = () => {
            if (document.visibilityState !== 'visible' || !navigator.onLine) return;
            if (tab !== 'productividad') precargarComisiones(parametros('productividad'), identidad);
            if (tab !== 'periodos' && areaId) precargarComisiones(parametros('periodos'), identidad);
        };
        const espera = window.setTimeout(preparar, 700);
        return () => window.clearTimeout(espera);
    // Los parámetros de precarga están definidos por la identidad y la sección.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [simple, identidad, tab, actualizando, mutacion, consulta?.pendiente, sin_areas, filtros.retorno_corte]);
    useEffect(() => {
        invalidarPrecargas();
    }, [contexto]);

    const actualizarManual = async () => {
        if (peticionManual.current) return;
        const peticion = new AbortController();
        peticionManual.current = peticion;
        invalidarPrecargas();
        setActualizando(true);
        try {
            const { data } = await axios.post(route('gestion_interna.comisiones_empaque.sincronizar'), { area_id: areaId || undefined }, {
                headers: { Accept: 'application/json' }, signal: peticion.signal,
            });
            emitirToastGelia(data.mensaje, data.estado === 'ok' ? 'success' : 'aviso');
        } catch {
            if (!peticion.signal.aborted) emitirToastGelia('No se pudo actualizar. Intente nuevamente.', 'error');
        } finally {
            peticionManual.current = null;
            if (!peticion.signal.aborted) {
                invalidarPrecargas();
                actualizacionDiscreta.comprobar();
                setActualizando(false);
            }
        }
    };

    return (
        <AppLayout title="Comisiones de empaque">
            <Head title="Comisiones de empaque" />
            <GeliaPageShell className="min-w-0 space-y-4 pt-4 pb-8 md:pt-0">
                <ProteccionEdiciones.Provider value={actualizacionDiscreta.registrarEdicion}>
                <div className="theme-card theme-card--solid theme-surface theme-no-blur theme-border min-w-0 space-y-4 !rounded-2xl p-3 md:p-5 [&_.theme-input]:!min-h-11 [&_.theme-label]:!text-xs [&_.theme-label]:!font-semibold">
                <header className="flex flex-wrap items-start justify-between gap-x-5 gap-y-3 border-b theme-border pb-3 md:pb-4">
                    <div className="min-w-0">
                        <div className="flex items-start gap-3">
                            <span className={`${GELIA_ICON_BOX} rounded-2xl p-3 theme-text-primario`} style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 10%, transparent)', borderColor: 'color-mix(in srgb, var(--color-primario) 30%, transparent)' }}>
                                <Wallet className="size-6" aria-hidden="true" />
                            </span>
                            <div className="min-w-0">
                                <h1 className="m-0 text-2xl font-black uppercase italic tracking-tighter theme-text-main md:text-3xl">Comisiones de empaque_</h1>
                                <p className="m-0 mt-1 text-[11px] font-bold uppercase tracking-widest theme-text-muted">Revise empaques, cierre periodos y liquide comisiones.</p>
                            </div>
                        </div>
                        <p role="status" className="m-0 mt-3 flex items-start gap-2 text-sm theme-text-muted">
                            <IconoActualizacion className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <span title={actualizacion.fecha ? `Última sincronización ${actualizacion.fecha}` : undefined}>{simple && observado?.estado !== 'ok' ? 'Actualización pendiente' : actualizacion.etiqueta}{!simple && actualizacion.detalle && ` · ${actualizacion.detalle}`}</span>
                        </p>
                        {actualizacionDiscreta.pendiente && <p className="m-0 mt-1 text-xs theme-text-aviso" role="status">Hay datos nuevos. Se conserva la revisión actual.</p>}
                    </div>
                    {!sin_areas && <div className="flex flex-wrap items-center gap-2">
                        <div className="gelia-segment p-1" role="group" aria-label="Modo de comisiones">
                            {['simple', 'avanzado'].map((modo) => <button type="button" key={modo}
                                className="gelia-segment-btn min-h-11 px-3 motion-reduce:transition-none focus-visible:!outline-2 focus-visible:outline-offset-2" data-active={simple === (modo === 'simple')}
                                aria-pressed={simple === (modo === 'simple')}
                                onClick={() => {
                                    if (modo === 'simple' && !simple) navegar('reporte', {
                                        modo, origen: undefined, retorno_corte: undefined, retorno_periodo_id: undefined,
                                    });
                                    if (modo === 'avanzado' && simple) navegar(tab === 'reporte' ? 'acumulado' : tab, { origen: undefined, modo });
                                }}>{modo === 'simple' ? 'Simple' : 'Avanzado'}</button>)}
                        </div>
                        {tab === 'reporte' && <button type="button" className={`${THEME_BTN_SECONDARY} inline-flex min-h-11 items-center gap-2 !normal-case focus-visible:!outline-2 focus-visible:outline-offset-2`} onClick={() => abrirDesdeReporte('cortes')}>
                            <History aria-hidden="true" className="size-4" /> Historial
                        </button>}
                        {tab === 'reporte' && puede.configurar && <div className="group relative">
                            <button type="button" aria-label="Abrir configuración" className={`${THEME_BTN_SECONDARY} grid size-11 place-items-center !p-0 focus-visible:!outline-2 focus-visible:outline-offset-2`}
                                onClick={() => configurar('tarifas')}>
                                <Settings aria-hidden="true" className="size-4" />
                            </button>
                            <span role="tooltip" className="pointer-events-none invisible absolute right-0 top-full z-10 mt-1 whitespace-nowrap rounded-md border theme-border theme-element px-2 py-1 text-xs group-hover:visible group-focus-within:visible">Abrir configuración</span>
                        </div>}
                    </div>}
                </header>
                    {!sin_areas && !simple && (!configuracion || ['areas', 'accesos', 'sincronizacion'].includes(filtros.seccion)) && <div className="grid w-full min-w-0 grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-end gap-2 sm:flex sm:w-auto sm:flex-wrap">
                        <label className="min-w-0 sm:min-w-32"><span className={THEME_LABEL}><span className="inline-flex items-center gap-1.5"><Layers className="size-4" aria-hidden="true" />Área</span></span><select className={THEME_INPUT} value={areaId} onChange={(e) => navegar(tab === 'preparar' ? 'periodos' : tab, { area_id: e.target.value || 'todas', periodo_id: undefined, seccion: filtros.seccion, retorno_corte: undefined, retorno_periodo_id: undefined, limite_desde: undefined, limite_hasta: undefined,
                            desde: filtros.periodo_id ? undefined : filtros.desde, hasta: filtros.periodo_id ? undefined : filtros.hasta })}>
                            <option value="">Todas</option>{areas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                        </select></label>
                        <label className="min-w-0 max-w-full"><span className={THEME_LABEL}><span className="inline-flex items-center gap-1.5"><CalendarDays className="size-4" aria-hidden="true" />Periodo</span></span><select className={THEME_INPUT} value={filtros.periodo_id || ''} onChange={(e) => navegar(tab, { periodo_id: e.target.value || undefined, desde: undefined, hasta: undefined, limite_desde: filtros.limite_desde, limite_hasta: filtros.limite_hasta })}>
                            <option value="">{filtros.desde || filtros.hasta ? [filtros.desde, filtros.hasta].filter(Boolean).join(' al ') : 'Sin periodo'}</option>
                            {opcionesPeriodo.map((p) => <option key={p.id} value={p.id}>{p.nombre} · {p.desde} a {p.hasta}</option>)}
                        </select></label>
                        <div className="group relative">
                            <button type="button" className={`${THEME_BTN_SECONDARY} size-11 !p-0`} disabled={actualizando} aria-label="Actualizar empaques de Gelia" aria-describedby="actualizar-comisiones-ayuda" onClick={actualizarManual}>
                                <RefreshCw className={`size-4 ${actualizando ? 'motion-safe:animate-spin' : ''}`} aria-hidden="true" />
                            </button>
                            <span id="actualizar-comisiones-ayuda" role="tooltip" className="pointer-events-none invisible absolute right-0 top-full z-10 mt-1 whitespace-nowrap rounded-md border theme-border theme-element px-2 py-1 text-xs group-hover:visible group-focus-within:visible">Actualizar empaques de Gelia</span>
                        </div>
                    </div>}
                {observado?.estado === 'sin_acceso' ? <div className="space-y-2">
                    <p role="alert" className="theme-text-aviso">Consulte las áreas con acceso disponible.</p>
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={() => navegar(simple ? 'reporte' : 'acumulado', { area_id: 'todas', periodo_id: undefined, desde: undefined, hasta: undefined })}>Consultar áreas disponibles</button>
                </div> : sin_areas ? <p role="alert" className="theme-text-aviso">Solicite al administrador acceso a un área de empaque.</p> : <>
                    {origen_simple && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => volverAlReporte()}>Volver al cálculo</button>}
                    {!!filtros.retorno_corte && tab !== 'preparar' && <button type="button" className={THEME_BTN_SECONDARY} onClick={() => navegar('preparar', { periodo_id: filtros.periodo_id || filtros.retorno_periodo_id, retorno_corte: undefined, retorno_periodo_id: undefined })}>Volver a preparar el corte</button>}
                    {!simple && <NavegacionComisiones ariaLabel="Secciones de comisiones" items={itemsPrincipales({ principal, puedeConfigurar: puede.configurar, onNavegar: navegar, onPrecargar: precargar })} />}
                    {!simple && liquidaciones && <NavegacionComisiones compacto ariaLabel="Cierre de periodo" items={itemsLiquidacion({ tab, filtros, onNavegar: navegar })} />}
                    {configuracion && puede.configurar && <NavegacionComisiones compacto ariaLabel="Configuración de comisiones" items={itemsConfiguracion({ seccion: seccionConfig, simple, onSeccion: (seccion) => navegar('configuracion', { seccion }) })} />}
                    <section aria-label="Contenido de comisiones" aria-busy={consulta?.pendiente || undefined} className="min-w-0 space-y-3">
                    <ProgresoConsulta consulta={consulta} />
                    {tab === 'reporte' && reporte && <Reporte reporte={reporte} areaId={areaId} filtros={filtros}
                        areas={areas} periodos={opcionesPeriodo} puede={puede} ocupado={Boolean(consulta)}
                        onConsultar={(cambios) => navegar('reporte', cambios)} onEmpaques={revisarEmpaques}
                        onConfigurar={configurar} onCorte={(area, corte) => abrirDesdeReporte('cortes', {
                            area_id: area, corte, periodo_id: String(area) === String(areaId) ? filtros.periodo_id : undefined,
                        })} />}
                    <Suspense fallback={<p className="m-0 text-sm theme-text-muted" role="status">Cargando sección…</p>}>
                    {tab === 'acumulado' && <Acumulado key={`${areaId}|${filtros.periodo_id || ''}`} resumen={resumen} filtros={filtros} areas={areas} situaciones={situaciones} acumulado={acumulado} totales={totales} puede={puede} tipos={tipos_caja} capturaNombre={captura_nombre} paqueterias={paqueterias} cargaRevision={carga_revision} motivos={motivos_comerciales} />}
                    {tab === 'periodos' && <Periodos key={areaId} periodos={periodos?.data || []} paginacion={periodos} equipo={equipo} areaId={areaId} puede={puede} />}
                    {tab === 'preparar' && <Preparar key={`${contexto}|${filtros.limite_desde || ''}|${filtros.limite_hasta || ''}`} areas={areas} filtros={filtros} preview={preview} puede={puede} />}
                    {tab === 'cortes' && <Cortes cortes={cortes} corte={corte} puede={puede} filtros={filtros} />}
                    {tab === 'pagos' && <Pagos key={areaId} saldos={saldos} puede={puede} areaId={areaId} />}
                    {tab === 'productividad' && <Productividad key={contexto} filas={productividad} filtros={filtros} puede={puede} />}
                    {configuracion && puede.configurar && <Configuracion key={areaId || 'todas'} areas={areas} areaId={areaId} fechaInicio={fecha_inicio} fechaFin={fecha_fin} tarifas={tarifas} alcances={alcances} equipo={equipo} puede={puede} fondo={resumen?.importe_por_cortar} repartos={repartos} sincronizacion={observado} cargaRevision={carga_revision} simple={simple} editarEquipo={String(filtros.editar_equipo) === '1'} onAjustes={() => navegar('cortes')} inicial={tab === 'equipo' ? 'equipo' : filtros.seccion} onSeccion={(seccion, area) => navegar('configuracion', area ? { seccion, area_id: area, periodo_id: undefined } : { seccion })} />}
                    </Suspense>
                    </section>
                </>}
                </div>
                </ProteccionEdiciones.Provider>
            </GeliaPageShell>
        </AppLayout>
    );
}

// El layout persistente conserva el espacio de trabajo también al volver en el historial.
function PaginaComisionesEmpaque() {
    return null;
}

PaginaComisionesEmpaque.layout = Index;
export default PaginaComisionesEmpaque;
